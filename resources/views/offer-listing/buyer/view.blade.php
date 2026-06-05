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
    $str = function($key) use ($meta) { $v = $meta[$key] ?? ''; return is_array($v) ? implode(', ', array_map(fn($e) => is_array($e) ? json_encode($e) : (string)$e, $v)) : (string)$v; };
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
   Design tokens — shared across all offer-listing view pages
   ============================================================ */
:root {
    --viho-primary:       #2563EB;
    --viho-primary-hover: #1D4ED8;
    --viho-page-bg:       #F8FAFC;
    --viho-card-bg:       #FFFFFF;
    --viho-heading:       #0F172A;
    --viho-text:          #334155;
    --viho-label:         #64748B;
    --viho-border:        #E2E8F0;
    --viho-success:       #16A34A;
    --viho-seller:        #2563EB;
    --viho-buyer:         #7C3AED;
    --viho-landlord:      #0F766E;
    --viho-tenant:        #0891B2;
}

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
.bol-view-page .bol-hero-carousel-wrap {
    position: relative; height: 100%; min-height: 280px; overflow: hidden; background: #0f172a;
}
.bol-view-page .bol-hero-photo {
    position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: block;
}
.bol-view-page .bol-hero-arrow {
    position: absolute; top: 50%; transform: translateY(-50%);
    background: rgba(0,0,0,.45); color: #fff; border: none; border-radius: 50%;
    width: 40px; height: 40px; font-size: 1.55rem; line-height: 1; cursor: pointer;
    z-index: 10; display: flex; align-items: center; justify-content: center;
    transition: background .15s; padding: 0; flex-shrink: 0;
}
.bol-view-page .bol-hero-arrow:hover { background: rgba(0,0,0,.72); }
.bol-view-page .bol-hero-arrow-prev { left: 12px; }
.bol-view-page .bol-hero-arrow-next { right: 12px; }
.bol-view-page .bol-hero-carousel-counter {
    position: absolute; bottom: 12px; right: 14px;
    background: rgba(0,0,0,.50); color: #fff; font-size: .76rem; font-weight: 600;
    padding: 3px 10px; border-radius: 20px; z-index: 10; pointer-events: none;
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
.bol-view-page .bol-nav-tabs li a:hover,
.bol-view-page .bol-nav-tabs li a.bol-nav-active { color: #2563eb; border-bottom-color: #2563eb; }

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
.bol-view-page .bol-action-outline { background: #fff; color: #334155; border-color: #cbd5e1; }
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

/* Hero CTA row */
.bol-view-page .bol-hero-ctas {
    display: flex; flex-wrap: wrap; gap: 0.4rem;
    margin-top: 0.9rem; padding-top: 0.9rem;
    border-top: 1px solid #f1f5f9;
}
.bol-view-page .bol-hero-ctas .btn {
    font-size: 0.8rem; font-weight: 600;
    padding: 0.42rem 0.75rem; border-radius: 8px;
    white-space: nowrap; flex-shrink: 0;
}
.bol-view-page .bol-hero-ctas .btn-primary {
    background-color: #2563eb !important; border-color: #2563eb !important; color: #fff !important;
}
.bol-view-page .bol-hero-ctas .btn-primary:hover,
.bol-view-page .bol-hero-ctas .btn-primary:focus {
    background-color: #1d4ed8 !important; border-color: #1d4ed8 !important; color: #fff !important;
}

/* Contact CTA row */
.bol-view-page .bol-contact-cta-row {
    display: flex; flex-wrap: wrap; gap: 0.5rem;
    margin-top: 1rem; padding-top: 1rem;
    border-top: 1px solid #f1f5f9;
}

/* Modal header */
.bol-view-page .bol-modal-header {
    background: linear-gradient(135deg, #1e293b, #334155);
    color: #fff; border-radius: 0.75rem 0.75rem 0 0;
    padding: 1.25rem 1.5rem; border-bottom: none;
}
.bol-view-page .bol-modal-header .btn-close { filter: invert(1); }

/* Mobile bar: highlight the Respond button */
.bol-mobile-bar-btn.bol-mobile-bar-respond {
    background: #2563eb !important; color: #fff !important; border-radius: 10px;
}
.bol-mobile-bar-btn.bol-mobile-bar-respond i { color: #fff !important; }
.bol-mobile-bar-btn.bol-mobile-bar-respond:hover,
.bol-mobile-bar-btn.bol-mobile-bar-respond:active {
    background: #1d4ed8 !important; color: #fff !important;
}

/* ============================================================
   bol-interaction-hub — six-panel action hub
   ============================================================ */
.bol-view-page .bol-interaction-hub {
    margin-bottom: 1.75rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    padding: 1.25rem 1rem;
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
}
.bol-view-page .bol-interaction-hub-label {
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.07em; color: #94a3b8;
    margin-bottom: 0.9rem; padding-bottom: 0.6rem; border-bottom: 1px solid #e2e8f0;
}
.bol-view-page .bol-interaction-grid {
    display: grid; grid-template-columns: repeat(6, 1fr); gap: 0.75rem;
}
@media (max-width: 1199.98px) { .bol-view-page .bol-interaction-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 767.98px)  { .bol-view-page .bol-interaction-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 479.98px)  { .bol-view-page .bol-interaction-grid { grid-template-columns: 1fr; } }
.bol-view-page .bol-interaction-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 0.75rem;
    padding: 1rem 0.85rem 0.9rem; display: flex; flex-direction: column;
    gap: 0.4rem; transition: box-shadow .15s, border-color .15s, transform .15s; min-height: 0;
}
.bol-view-page .bol-interaction-card:hover {
    box-shadow: 0 4px 18px rgba(37,99,235,.12); border-color: #bfdbfe; transform: translateY(-2px);
}
.bol-view-page .bol-interaction-card-icon { font-size: 1.45rem; color: #2563eb; margin-bottom: 0.1rem; line-height: 1; }
.bol-view-page .bol-interaction-card-label { font-size: 0.83rem; font-weight: 700; color: #1e293b; letter-spacing: -0.01em; line-height: 1.2; }
.bol-view-page .bol-interaction-card-helper { font-size: 0.74rem; color: #64748b; line-height: 1.45; flex: 1; }
.bol-view-page .bol-interaction-cta {
    display: inline-flex; align-items: center; gap: 5px; font-size: 0.75rem; font-weight: 700;
    padding: 0.38rem 0.7rem; border-radius: 7px; border: none; cursor: pointer;
    transition: background .15s, color .15s; white-space: nowrap;
    margin-top: 0.25rem; align-self: flex-start; text-decoration: none;
}
.bol-view-page .bol-interaction-cta:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
.bol-view-page .bol-interaction-cta-primary { background: #2563eb; color: #fff; }
.bol-view-page .bol-interaction-cta-primary:hover { background: #1d4ed8; color: #fff; }
.bol-view-page .bol-interaction-cta-outline { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
.bol-view-page .bol-interaction-cta-outline:hover { background: #f8fafc; color: #1e293b; border-color: #94a3b8; }
.bol-view-page .bol-interaction-cta-muted { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; cursor: default; font-weight: 600; opacity: .75; }
.bol-view-page .bol-interaction-share-row { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.25rem; }
.bol-view-page .bol-interaction-activity-row { display: flex; justify-content: space-between; font-size: 0.72rem; color: #64748b; padding: 0.18rem 0; border-bottom: 1px solid #f1f5f9; }
.bol-view-page .bol-interaction-activity-row:last-child { border-bottom: none; }
.bol-view-page .bol-interaction-activity-val { font-weight: 700; color: #94a3b8; font-size: 0.72rem; }
.bol-view-page .bol-interaction-ai-chips { display: flex; flex-direction: column; gap: 0.28rem; margin-bottom: 0.35rem; }
.bol-view-page .bol-interaction-ai-chip {
    font-size: 0.69rem; color: #3b82f6; background: #eff6ff; border: 1px solid #bfdbfe;
    border-radius: 20px; padding: 0.2rem 0.5rem; white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis; max-width: 100%; cursor: default;
}

/* ---- Ask AI Suggestion Chips ---- */
.ask-ai-chip {
    display: inline-flex;
    align-items: center;
    font-size: 0.78rem;
    font-weight: 600;
    padding: 0.5rem 0.85rem;
    min-height: 44px;
    border-radius: 20px;
    border: 1px solid #bfdbfe;
    background: #eff6ff;
    color: #1d4ed8;
    cursor: pointer;
    transition: background .15s, border-color .15s, color .15s;
    text-align: left;
    line-height: 1.35;
    max-width: 100%;
    white-space: normal;
    word-break: break-word;
}
.ask-ai-chip:hover, .ask-ai-chip:focus {
    background: #dbeafe;
    border-color: #93c5fd;
    color: #1e40af;
    outline: none;
}
.ask-ai-chip:focus-visible {
    outline: 2px solid #2563eb;
    outline-offset: 2px;
}
.ask-ai-chip:active {
    background: #bfdbfe;
    border-color: #60a5fa;
    color: #1e40af;
}
.ask-ai-chip-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}
@media (max-width: 767.98px) {
    .ask-ai-chip-wrap {
        flex-wrap: nowrap;
        overflow-x: auto;
        scrollbar-width: none;
        -ms-overflow-style: none;
        padding-bottom: 4px;
    }
    .ask-ai-chip-wrap::-webkit-scrollbar { display: none; }
    .ask-ai-chip { flex-shrink: 0; }
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
        @if(auth()->check() && auth()->id() === $auction->user_id)
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
            ['show' => $badgePreApproved,   'label' => 'Pre-Approved',      'icon' => 'fa-solid fa-circle-check',        'color' => 'green',  'strong' => true],
            ['show' => $badgeCash,          'label' => 'Cash Buyer',        'icon' => 'fa-solid fa-money-bill-wave',      'color' => 'green',  'strong' => true],
            ['show' => $badgeAssumable,     'label' => 'Assumable Mortgage','icon' => 'fa-solid fa-hand-holding-dollar',  'color' => 'teal',   'strong' => true],
            ['show' => $badgeSellerFin,     'label' => 'Seller Financing',  'icon' => 'fa-solid fa-hand-holding-dollar',  'color' => 'teal',   'strong' => false],
            ['show' => $badgeLeaseOpt,      'label' => 'Lease Option',      'icon' => 'fa-solid fa-key',                 'color' => 'purple', 'strong' => false],
            ['show' => $badgeLeasePur,      'label' => 'Lease Purchase',    'icon' => 'fa-solid fa-key',                 'color' => 'purple', 'strong' => false],
            ['show' => $badgeCrypto,        'label' => 'Crypto Accepted',   'icon' => 'fa-brands fa-bitcoin',            'color' => 'amber',  'strong' => false],
            ['show' => $badgeExchange,      'label' => 'Exchange / Trade',  'icon' => 'fa-solid fa-arrows-rotate',       'color' => 'amber',  'strong' => false],
            ['show' => $badgeFinancing && !$badgeCash && !$badgeAssumable && !$badgeSellerFin && !$badgeLeaseOpt && !$badgeLeasePur && !$badgeCrypto && !$badgeExchange,
                       'label' => 'Flexible Financing', 'icon' => 'fa-solid fa-hand-holding-dollar', 'color' => 'blue',   'strong' => false],
            ['show' => (bool)$heroPropType, 'label' => (string)$heroPropType, 'icon' => 'fa-solid fa-tag',              'color' => 'blue',   'strong' => false],
        ];
        $heroBadgesDisplay = array_slice(array_values(array_filter($heroBadges, fn($b) => $b['show'])), 0, 5);
    @endphp

    {{-- HERO --}}
    @php
        /* Hero photos: decode property_photos JSON array */
        $bolPropertyPhotos = $meta['property_photos'] ?? [];
        if (is_string($bolPropertyPhotos)) {
            $bolDecoded = json_decode($bolPropertyPhotos, true);
            $bolPropertyPhotos = is_array($bolDecoded) ? $bolDecoded : [];
        }
        $bolHeroPhotoUrls = [];
        $bolCoverPhotoIdx = 0;
        $_bolHIdx = 0;
        foreach ($bolPropertyPhotos as $_bolPh) {
            $_bolFn = is_array($_bolPh) ? ($_bolPh['filename'] ?? '') : $_bolPh;
            if (!$_bolFn) continue;
            $bolHeroPhotoUrls[] = asset('storage/auction/images/' . $_bolFn);
            if (is_array($_bolPh) && !empty($_bolPh['is_cover'])) $bolCoverPhotoIdx = $_bolHIdx;
            $_bolHIdx++;
        }
    @endphp
    @php
        // Bidding Period countdown — uses expiration_date when available; falls back to created_at + auction_time for legacy records
        $hasBPTimer = false;
        $timerRemainingSeconds = 0;
        $_auctionType = trim($str('auction_type'));
        if ($_auctionType === '') {
            $_auctionType = trim((string)($auction->auction_type ?? ''));
        }
        if (in_array(strtolower($_auctionType), ['bidding period', 'auction (timer)'])) {
            $_timerEnd = null;
            $_expDateStr = trim($str('expiration_date'));
            if ($_expDateStr !== '') {
                $_timerEnd = \Carbon\Carbon::parse($_expDateStr);
            } else {
                $_aTime = trim($str('auction_time'));
                if ($_aTime === '') {
                    $_aTime = trim((string)($auction->auction_time ?? $auction->auction_length ?? ''));
                }
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
            }
            if (!empty($_timerEnd)) {
                $timerRemainingSeconds = (int)\Carbon\Carbon::now()->diffInSeconds($_timerEnd, false);
                $hasBPTimer = true;
            }
        }
    @endphp
    <div class="bol-hero mb-4">
        <div class="row g-0" style="min-height:280px;">
            <div class="col-lg-8">
                <div class="bol-hero-carousel-wrap">
                    @if(count($bolHeroPhotoUrls))
                        <img id="bolHeroCarouselImg"
                             src="{{ $bolHeroPhotoUrls[$bolCoverPhotoIdx] }}"
                             alt="Buyer listing photo"
                             class="bol-hero-photo"
                             style="cursor:pointer;"
                             onerror="this.style.display='none';var ph=document.getElementById('bolHeroCarouselPlaceholder');if(ph)ph.style.display='flex'">
                        <div id="bolHeroCarouselPlaceholder" class="bol-hero-placeholder" style="display:none;">
                            <i class="fa-solid fa-magnifying-glass-dollar"></i>
                        </div>
                        @if(count($bolHeroPhotoUrls) > 1)
                        <button class="bol-hero-arrow bol-hero-arrow-prev" id="bolHeroCarouselPrev" aria-label="Previous photo">&#8249;</button>
                        <button class="bol-hero-arrow bol-hero-arrow-next" id="bolHeroCarouselNext" aria-label="Next photo">&#8250;</button>
                        <div class="bol-hero-carousel-counter" id="bolHeroCarouselCounter">{{ $bolCoverPhotoIdx + 1 }} / {{ count($bolHeroPhotoUrls) }}</div>
                        @endif
                    @else
                    @php
                        $_bSnapRows = [];
                        if ($heroPrice) $_bSnapRows[] = ['icon'=>'fa-solid fa-dollar-sign','label'=>'Max Budget','val'=>$heroPrice];
                        $_bLocParts = array_filter([$str('property_city') ?: null]);
                        if (empty($_bLocParts)) {
                            $_bLocCities = $arr('cities') ?: [];
                            if (count($_bLocCities)) $_bLocParts[] = implode(', ', array_slice($_bLocCities, 0, 2));
                        }
                        $_bLocState = $str('state') ?: $str('property_state') ?: null;
                        if ($_bLocState) $_bLocParts[] = $_bLocState;
                        $_bSnapLoc = implode(', ', array_filter($_bLocParts));
                        if ($_bSnapLoc) $_bSnapRows[] = ['icon'=>'fa-solid fa-location-dot','label'=>'Location','val'=>$_bSnapLoc];
                        if ($heroPropType) $_bSnapRows[] = ['icon'=>'fa-solid fa-tag','label'=>'Property Type','val'=>$heroPropType];
                        if ($heroBeds) $_bSnapRows[] = ['icon'=>'fa-solid fa-bed','label'=>'Min. Beds','val'=>$heroBeds];
                        if ($heroBaths) $_bSnapRows[] = ['icon'=>'fa-solid fa-bath','label'=>'Min. Baths','val'=>$heroBaths];
                        if ($heroSqft) $_bSnapRows[] = ['icon'=>'fa-solid fa-ruler-combined','label'=>'Min. Sq Ft','val'=>number_format((int)preg_replace('/[^0-9]/','',$heroSqft)).'+ sq ft'];
                        $_bPurpose = $str('purchase_purpose');
                        if ($_bPurpose) $_bSnapRows[] = ['icon'=>'fa-solid fa-bullseye','label'=>'Purpose','val'=>$_bPurpose];
                        if ($badgePreApproved) $_bSnapRows[] = ['icon'=>'fa-solid fa-circle-check','label'=>'Pre-Approved','val'=>'Yes'];
                        if (count($heroOfFin)) $_bSnapRows[] = ['icon'=>'fa-solid fa-hand-holding-dollar','label'=>'Financing','val'=>implode(', ', array_slice($heroOfFin, 0, 2))];
                        if ($heroStatus) $_bSnapRows[] = ['icon'=>'fa-solid fa-circle','label'=>'Status','val'=>$heroStatus];
                    @endphp
                    <div style="height:100%;min-height:280px;padding:1.5rem 1.25rem;background:#ffffff;border:1px solid #e2e8f0;display:flex;flex-direction:column;justify-content:center;">
                        <div style="font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;margin-bottom:.5rem;">Buyer Criteria Snapshot</div>
                        @foreach($_bSnapRows as $_bsr)
                        <div style="display:flex;align-items:center;gap:.4rem;padding:3px 0;border-bottom:1px solid #f1f5f9;">
                            <i class="{{ $_bsr['icon'] }}" style="font-size:.68rem;color:#2563eb;min-width:13px;text-align:center;"></i>
                            <span style="font-size:.7rem;color:#64748b;white-space:nowrap;">{{ $_bsr['label'] }}</span>
                            <span style="font-size:.78rem;font-weight:700;color:#0f172a;flex:1;text-align:right;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $_bsr['val'] }}">{{ $_bsr['val'] }}</span>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
                <script>var _bolHeroPhotos={!! json_encode($bolHeroPhotoUrls) !!};var _bolHeroStartIdx={{ $bolCoverPhotoIdx }};</script>
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

                    @php
                        $bolStandoutParts = array_values(array_map(
                            fn($b) => $b['label'],
                            array_filter($heroBadgesDisplay, fn($b) => !empty($b['strong']))
                        ));
                    @endphp
                    @if(count($bolStandoutParts) >= 2)
                    <div style="margin-top:10px;padding:10px 14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;">
                        <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#1d4ed8;margin-bottom:3px;">Why This Buyer Stands Out</div>
                        @php
                            $bSlice = array_slice($bolStandoutParts, 0, -1);
                            $bLast  = end($bolStandoutParts);
                        @endphp
                        <div style="font-size:0.88rem;color:#1e3a5f;">{{ count($bolStandoutParts) > 1 ? implode(', ', $bSlice) . ' and ' . $bLast : $bLast }}.</div>
                    </div>
                    @endif

                    @if($hasBPTimer)
                    <div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
                        <span class="text-muted fw-semibold" style="font-size:.8rem;"><i class="fa-regular fa-clock me-1"></i>Bidding Period:</span>
                        @if($timerRemainingSeconds <= 0)
                            <span class="badge bg-secondary" style="font-size:.8rem;">Expired</span>
                        @else
                            <span class="badge bg-info text-dark bol-bp-timer"
                                  data-seconds="{{ $timerRemainingSeconds }}"
                                  style="font-size:.8rem;font-variant-numeric:tabular-nums;">
                                @php
                                    $_bs = $timerRemainingSeconds;
                                    if ($_bs < 60) { echo $_bs . 's Remaining'; }
                                    else {
                                        $_bd = intdiv($_bs, 86400); $_bs %= 86400;
                                        $_bh = intdiv($_bs, 3600);  $_bs %= 3600;
                                        $_bi = intdiv($_bs, 60);
                                        $_bp = [];
                                        if ($_bd) $_bp[] = $_bd . 'd';
                                        if ($_bh) $_bp[] = $_bh . 'h';
                                        if ($_bi) $_bp[] = $_bi . 'm';
                                        echo implode(' ', $_bp) . ' Remaining';
                                    }
                                @endphp
                            </span>
                        @endif
                    </div>
                    @endif

                    <div class="bol-hero-ctas">
                        <form method="POST" action="{{ route('offers.store') }}" style="display:contents;">
                            @csrf
                            <input type="hidden" name="offer_auction_id" value="{{ $auction->id }}">
                            <input type="hidden" name="role" value="buyer">
                            <button type="submit" class="btn btn-primary" aria-label="Respond to this Buyer Criteria listing">
                                <i class="fa-solid fa-reply me-1"></i>Respond to Buyer Criteria
                            </button>
                        </form>
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#bolShowingModal" aria-label="Schedule a showing">
                            <i class="fa-solid fa-calendar-days me-1"></i>Schedule Showing
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#bolQuestionModal" aria-label="Ask a question about this listing">
                            <i class="fa-solid fa-circle-question me-1"></i>Ask a Question
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== INTERACTION HUB ===== --}}
    <div class="bol-interaction-hub" id="bol-interaction-hub">
        <div class="bol-interaction-hub-label"><i class="fa-solid fa-bolt me-1"></i>Quick Actions &amp; Listing Info</div>
        <div class="bol-interaction-grid">

            {{-- 1. Respond to Buyer Criteria --}}
            <div class="bol-interaction-card">
                <div class="bol-interaction-card-icon"><i class="fa-solid fa-reply"></i></div>
                <div class="bol-interaction-card-label">Respond to Buyer Criteria</div>
                <div class="bol-interaction-card-helper">Submit a property that matches this buyer's criteria.</div>
                <form method="POST" action="{{ route('offers.store') }}">
                    @csrf
                    <input type="hidden" name="offer_auction_id" value="{{ $auction->id }}">
                    <input type="hidden" name="role" value="buyer">
                    <button type="submit" class="bol-interaction-cta bol-interaction-cta-primary"
                            aria-label="Respond to this Buyer Criteria listing">
                        <i class="fa-solid fa-reply"></i>Respond
                    </button>
                </form>
            </div>

            {{-- 2. Schedule Showing --}}
            <div class="bol-interaction-card">
                <div class="bol-interaction-card-icon"><i class="fa-solid fa-calendar-days"></i></div>
                <div class="bol-interaction-card-label">Schedule Showing</div>
                <div class="bol-interaction-card-helper">Request an in-person or virtual showing.</div>
                <button type="button" class="bol-interaction-cta bol-interaction-cta-outline"
                        data-bs-toggle="modal" data-bs-target="#bolShowingModal"
                        aria-label="Schedule a showing">
                    <i class="fa-solid fa-calendar-plus"></i>Request Showing
                </button>
            </div>

            {{-- 3. Ask AI --}}
            <div class="bol-interaction-card">
                <div class="bol-interaction-card-icon"><i class="fa-solid fa-robot"></i></div>
                <div class="bol-interaction-card-label">Ask AI</div>
                <div class="bol-interaction-ai-chips">
                    <span class="bol-interaction-ai-chip">What financing does this buyer prefer?</span>
                    <span class="bol-interaction-ai-chip">Is this buyer pre-approved?</span>
                    <span class="bol-interaction-ai-chip">What property features are required?</span>
                    <span class="bol-interaction-ai-chip">What is the buyer's timeline?</span>
                    <span class="bol-interaction-ai-chip">What contingencies does the buyer need?</span>
                </div>
                <input type="text" class="form-control form-control-sm"
                       placeholder="Ask a question about this buyer…"
                       aria-label="AI question input" disabled
                       style="font-size:.73rem;border-radius:6px;background:#f8fafc;cursor:default;">
                <button type="button" class="bol-interaction-cta bol-interaction-cta-outline"
                        data-bs-toggle="modal" data-bs-target="#bolAiModal"
                        aria-label="Ask AI a question about this buyer listing">
                    <i class="fa-solid fa-robot"></i>Ask AI
                </button>
            </div>

            {{-- 4. Ask a Question --}}
            <div class="bol-interaction-card">
                <div class="bol-interaction-card-icon"><i class="fa-solid fa-circle-question"></i></div>
                <div class="bol-interaction-card-label">Ask a Question</div>
                <div class="bol-interaction-card-helper">Send a direct question to the listing contact.</div>
                <button type="button" class="bol-interaction-cta bol-interaction-cta-outline"
                        data-bs-toggle="modal" data-bs-target="#bolQuestionModal"
                        aria-label="Ask a question about this listing">
                    <i class="fa-solid fa-paper-plane"></i>Send Question
                </button>
            </div>

            {{-- 5. Share Listing --}}
            <div class="bol-interaction-card">
                <div class="bol-interaction-card-icon"><i class="fa-solid fa-share-nodes"></i></div>
                <div class="bol-interaction-card-label">Share Listing</div>
                <div class="bol-interaction-card-helper">Share this listing with friends, family, or your network.</div>
                <div class="bol-interaction-share-row">
                    <button type="button" class="bol-interaction-cta bol-interaction-cta-outline" id="bolHubCopyBtn"
                            aria-label="Copy listing link to clipboard">
                        <i class="fa-solid fa-link"></i>Copy Link
                    </button>
                    <button type="button" class="bol-interaction-cta bol-interaction-cta-outline" id="bolHubNativeShareBtn"
                            style="display:none;" aria-label="Share this listing via your device's share sheet">
                        <i class="fa-solid fa-share-nodes"></i>Share
                    </button>
                </div>
                <div class="bol-interaction-share-row" style="margin-top:.15rem;">
                    <span class="bol-interaction-cta bol-interaction-cta-muted" aria-label="QR Code — coming soon">
                        <i class="fa-solid fa-qrcode"></i>QR Code
                    </span>
                    <span class="bol-interaction-cta bol-interaction-cta-muted" aria-label="Embed widget — coming soon">
                        <i class="fa-solid fa-code"></i>Embed
                    </span>
                </div>
            </div>

            {{-- 6. Hire an Agent --}}
            <div class="bol-interaction-card">
                <div class="bol-interaction-card-icon"><i class="fa-solid fa-user-tie"></i></div>
                <div class="bol-interaction-card-label">Hire an Agent</div>
                <div class="bol-interaction-card-helper">Need representation? Connect with a licensed real estate agent.</div>
                <button type="button" class="bol-interaction-cta bol-interaction-cta-outline"
                        data-bs-toggle="modal" data-bs-target="#bolHireAgentModal"
                        aria-label="Find and hire a real estate agent">
                    <i class="fa-solid fa-user-tie"></i>Find an Agent
                </button>
            </div>

            {{-- Activity — hidden until live data is available --}}
            @if(false)
            <div class="bol-interaction-card">
                <div class="bol-interaction-card-icon"><i class="fa-solid fa-chart-simple"></i></div>
                <div class="bol-interaction-card-label">Activity</div>
                <div style="margin-top:.1rem;">
                    <div class="bol-interaction-activity-row">
                        <span>Views</span><span class="bol-interaction-activity-val">Coming Soon</span>
                    </div>
                    <div class="bol-interaction-activity-row">
                        <span>Saves</span><span class="bol-interaction-activity-val">Coming Soon</span>
                    </div>
                    <div class="bol-interaction-activity-row">
                        <span>Questions</span><span class="bol-interaction-activity-val">Coming Soon</span>
                    </div>
                    <div class="bol-interaction-activity-row">
                        <span>Bids</span><span class="bol-interaction-activity-val">Coming Soon</span>
                    </div>
                </div>
            </div>
            @endif

        </div>{{-- /bol-interaction-grid --}}
    </div>{{-- /bol-interaction-hub --}}

    {{-- TWO-COLUMN LAYOUT --}}
    <div class="row g-4 align-items-start">

        {{-- Main content column --}}
        <div class="col-lg-9 bol-main-content-wrap">

    {{-- Section visibility flags — computed once, reused by nav tabs AND section guards --}}
    @php
        $hasCriteriaContent = $str('property_type') || $str('bedrooms') || $str('bathrooms')
            || $str('minimum_heated_square') || $str('minimum_heated_sqft') || $str('minimum_leaseable')
            || $str('min_acreage') || $str('total_acreage') || $str('maximum_budget') || $str('buyer_budget')
            || $str('max_purchase_price') || $str('purchase_price') || $str('target_closing_date')
            || $str('desired_agent_hire_date') || $str('working_with_agent') || $str('sale_provision')
            || $str('buyer_sell_contract') || $str('number_occupant') || $str('purchase_purpose')
            || count($arr('cities')) || count($arr('counties')) || $str('property_state') || $str('state')
            || count($arr('condition_prop_buyer')) || count($arr('property_items'));

        $hasFinancingContent = count($arr('offered_financing')) || $str('pre_approved')
            || $str('pre_approval_amount') || $str('cash_budget') || $str('down_payment_amount')
            || $str('interest_rate') || $str('assumable_terms') || $str('lease_option_price')
            || $str('lease_purchase_price') || $str('cryptocurrency_type') || $str('exchange_item_value')
            || $str('nft_description') || $str('sale_provision_assignment');

        $hasFeaturesContent = $str('garage_needed') || $str('carport_needed') || $str('pool_needed')
            || count($arr('view_preference')) || count($arr('non_negotiable_amenities'))
            || $str('leasing_space') || $str('leasing_55_plus') || $str('real_estate_purchase')
            || $str('minimum_annual_net_income') || $str('minimum_cap_rate')
            || count($arr('number_of_unit_type')) || $str('number_of_unit') || $str('property_criteria');

        $hasPurchaseTermsContent = $str('earnest_money_amount') || $str('earnest_money_timing')
            || $str('due_diligence_yn') || $str('inspection_contingency_buyer')
            || $str('appraisal_contingency_buyer') || $str('financing_contingency_buyer')
            || $str('home_sale_contingency') || $str('seller_contribution')
            || $str('possession_preference') || $str('home_warranty_requested')
            || $str('as_is_purchase') || $str('property_inclusions')
            || $str('property_exclusions') || $str('closing_cost_responsibility')
            || $str('additional_purchase_terms');

        $contactName = trim(($str('first_name') . ' ' . $str('last_name')));
        $hasContact  = $contactName || $str('email') || $str('phone_number')
            || $str('agent_brokerage') || $str('agent_license_number') || $str('agent_nar_member_id');

        $hasPurchaseTermsContent =
            $str('earnest_money_amount') || $str('earnest_money_timing')
            || $str('due_diligence_yn') || $str('inspection_period_days') || $str('inspection_period_other')
            || $str('inspection_contingency_buyer') || $str('appraisal_contingency_buyer')
            || $str('appraisal_contingency_days') || $str('financing_contingency_buyer')
            || $str('financing_contingency_period') || $str('home_sale_contingency')
            || $str('home_sale_contingency_address') || $str('home_sale_contingency_date')
            || $str('home_sale_contingency_under_contract') || $str('home_sale_contingency_details')
            || $str('seller_contribution') || $str('seller_contribution_details')
            || $str('possession_preference') || $str('possession_preference_other')
            || $str('possession_details') || $str('closing_cost_responsibility')
            || $str('home_warranty_requested') || $str('home_warranty_details')
            || $str('as_is_purchase') || $str('property_inclusions') || $str('property_exclusions')
            || $str('additional_purchase_terms');
    @endphp

    {{-- SMOOTH-SCROLL NAV TABS --}}
    <div class="bol-nav-tabs-wrap">
        <ul class="bol-nav-tabs" id="bolNavTabs">
            <li><a href="#section-overview">Overview</a></li>
            @if($hasCriteriaContent)<li><a href="#section-criteria">Purchase Criteria</a></li>@endif
            @if($hasFinancingContent)<li><a href="#section-financing">Financing</a></li>@endif
            @if($hasPurchaseTermsContent)<li><a href="#section-purchase-terms">Purchase Terms</a></li>@endif
            @if($hasFeaturesContent)<li><a href="#section-features">Property Features</a></li>@endif
            @if($hasContact)<li><a href="#section-contact">Contact</a></li>@endif
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
                        <span class="badge bg-info text-dark bol-bp-timer"
                              data-seconds="{{ $timerRemainingSeconds }}"
                              style="font-size:.85rem;font-variant-numeric:tabular-nums;">
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
    @if($hasCriteriaContent)
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

    @endif {{-- /hasCriteriaContent --}}

    {{-- Financing Details --}}
    @if($hasFinancingContent)
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

    @endif {{-- /hasFinancingContent --}}

    {{-- Desired Property Features --}}
    @if($hasFeaturesContent)
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

    @endif {{-- /hasFeaturesContent --}}

    {{-- Additional Purchase Terms --}}
    @if($hasPurchaseTermsContent)
    <div class="card section-card" id="section-purchase-terms">
        <div class="card-header"><i class="fa-solid fa-file-signature me-2"></i>Additional Purchase Terms</div>
        <div class="card-body">

            {{-- Earnest Money --}}
            @php
                $_emType = $str('earnest_money_type');
                $_emAmt  = $str('earnest_money_amount');
                $_emFmt  = null;
                if ($_emAmt !== '') {
                    $_emFmt = ($_emType === '%') ? $fmtPercent($_emAmt) : $fmtMoney($_emAmt);
                }
            @endphp
            @if($_emFmt || $str('earnest_money_timing'))
            <h6 class="fw-semibold mb-2">Earnest Money / EMD</h6>
            <div class="row">
                <div class="col-md-6">{!! $row('EMD Amount', $_emFmt) !!}</div>
                <div class="col-md-6">{!! $row('EMD Timing', $str('earnest_money_timing')) !!}</div>
            </div>
            <hr>
            @endif

            {{-- Inspections & Contingencies --}}
            <h6 class="fw-semibold mb-2">Inspections & Contingencies</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Due Diligence / Inspection Period', $str('due_diligence_yn')) !!}
                    @if($str('due_diligence_yn') === 'Yes')
                        {!! $row('Inspection Period Duration', $str('inspection_period_days') === 'Other' ? ($str('inspection_period_other') ?: 'Other') : $str('inspection_period_days')) !!}
                    @endif
                    {!! $row('Inspection Contingency', $str('inspection_contingency_buyer')) !!}
                    {!! $row('Appraisal Contingency', $str('appraisal_contingency_buyer')) !!}
                    @if($str('appraisal_contingency_buyer') === 'Yes' && $str('appraisal_contingency_days') !== '')
                        {!! $row('Appraisal Contingency Period', $str('appraisal_contingency_days') . ' days') !!}
                    @endif
                </div>
                <div class="col-md-6">
                    {!! $row('Financing Contingency', $str('financing_contingency_buyer')) !!}
                    @if($str('financing_contingency_buyer') === 'Yes' && $str('financing_contingency_period') !== '')
                        {!! $row('Financing Contingency Period', $str('financing_contingency_period') . ' days') !!}
                    @endif
                </div>
            </div>

            {{-- Home Sale Contingency --}}
            @if($str('home_sale_contingency') !== '')
            <hr>
            @if($str('home_sale_contingency') === 'Yes')
            <h6 class="fw-semibold mb-2">Home Sale Contingency</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Home Sale Contingency', $str('home_sale_contingency')) !!}
                    {!! $row('Property Address', $str('home_sale_contingency_address')) !!}
                    {!! $row('Target Date', $fmtDate($str('home_sale_contingency_date'))) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Under Contract', $str('home_sale_contingency_under_contract')) !!}
                    {!! $row('Details', $str('home_sale_contingency_details')) !!}
                </div>
            </div>
            @else
            <div class="row">
                <div class="col-md-6">{!! $row('Home Sale Contingency', $str('home_sale_contingency')) !!}</div>
            </div>
            @endif
            @endif

            {{-- Possession & Closing --}}
            @php
                $_hasPossClosing = $str('seller_contribution') || $str('possession_preference') || $str('closing_cost_responsibility');
            @endphp
            @if($_hasPossClosing)
            <hr>
            <h6 class="fw-semibold mb-2">Possession & Closing</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Seller Contribution', $str('seller_contribution')) !!}
                    @if($str('seller_contribution') !== '' && $str('seller_contribution') !== 'None')
                        {!! $row('Seller Contribution Details', $str('seller_contribution_details')) !!}
                    @endif
                    {!! $row('Possession Preference', $str('possession_preference') === 'Other' ? ($str('possession_preference_other') ?: $str('possession_preference')) : $str('possession_preference')) !!}
                    @if($str('possession_preference') !== '' && $str('possession_preference') !== 'At Closing' && $str('possession_preference') !== 'Other')
                        {!! $row('Possession Details', $str('possession_details')) !!}
                    @endif
                </div>
                <div class="col-md-6">
                    {!! $row('Closing Cost Responsibility', $str('closing_cost_responsibility')) !!}
                </div>
            </div>
            @endif

            {{-- Property & Warranty --}}
            @php
                $_hasPropWarranty = $str('home_warranty_requested') || $str('as_is_purchase') || $str('property_inclusions') || $str('property_exclusions');
            @endphp
            @if($_hasPropWarranty)
            <hr>
            <h6 class="fw-semibold mb-2">Property & Warranty</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Home Warranty Requested', $str('home_warranty_requested')) !!}
                    @if($str('home_warranty_requested') !== '' && $str('home_warranty_requested') !== 'No')
                        {!! $row('Home Warranty Details', $str('home_warranty_details')) !!}
                    @endif
                    {!! $row('As-Is Purchase', $str('as_is_purchase')) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Property Inclusions', $str('property_inclusions')) !!}
                    {!! $row('Property Exclusions', $str('property_exclusions')) !!}
                </div>
            </div>
            @endif

            {{-- Additional Notes --}}
            @if($str('additional_purchase_terms'))
            <hr>
            <div class="row">
                <div class="col-md-12">{!! $row('Additional Purchase Terms', $str('additional_purchase_terms')) !!}</div>
            </div>
            @endif

        </div>
    </div>
    @endif {{-- /hasPurchaseTermsContent --}}

    {{-- Broker Compensation & Agency Agreement Terms --}}
    @php
        $hasBrokerComp = !empty($val('commission_structure')) || !empty($val('purchase_fee_type'));
    @endphp
    @if($hasBrokerComp)
    <div class="card section-card" id="section-broker-compensation">
        <div class="card-header"><i class="fa-solid fa-handshake me-2"></i>Broker Compensation & Agency Agreement Terms</div>
        <div class="card-body">

            @if(!empty($val('commission_structure')))
            <h6 class="fw-semibold mb-2">Buyer's Broker Compensation</h6>
            <div class="row">
                <div class="col-md-12">
                    {!! $row("Buyer's Broker Commission Structure", $str('commission_structure')) !!}
                </div>
            </div>
            @endif

            @if(!empty($val('purchase_fee_type')))
            @php
                $bPurchaseFeeType = $str('purchase_fee_type');
                $bPurchaseFeeCombined = null;
                if ($bPurchaseFeeType === 'Flat Fee') {
                    $bPurchaseFeeCombined = $fmtMoney($str('purchase_fee_flat'));
                } elseif ($bPurchaseFeeType === 'Percentage of the Total Purchase Price') {
                    $pct = $str('purchase_fee_percentage');
                    $bPurchaseFeeCombined = $pct ? ($fmtPercent($pct) . ' of Total Purchase Price') : null;
                } elseif ($bPurchaseFeeType === 'Percentage of the Total Purchase Price + Flat Fee') {
                    $parts = array_filter([
                        $fmtMoney($str('purchase_fee_flat_combo')),
                        $str('purchase_fee_percentage_combo') ? ($fmtPercent($str('purchase_fee_percentage_combo')) . ' of Total Purchase Price') : null,
                    ]);
                    $bPurchaseFeeCombined = $parts ? implode(' + ', $parts) : null;
                } elseif ($bPurchaseFeeType === 'other') {
                    $bPurchaseFeeCombined = $str('purchase_fee_other') ?: null;
                }
            @endphp
            @if(!empty($val('commission_structure')))
            <hr>
            @endif
            <div class="row">
                <div class="col-md-6">
                    {!! $row("Purchase Fee Type", $bPurchaseFeeType) !!}
                </div>
                @if($bPurchaseFeeCombined)
                <div class="col-md-6">
                    {!! $row("Purchase Fee Amount", $bPurchaseFeeCombined) !!}
                </div>
                @endif
            </div>
            @endif

        </div>
    </div>
    @endif

    {{-- Contact --}}
    @if($hasContact)
    <div class="card section-card" id="section-contact">
        <div class="card-header"><i class="fa-solid fa-address-card me-2"></i>Contact Information</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
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
            <div class="bol-contact-cta-row">
                @if($str('email'))
                    <a href="mailto:{{ $str('email') }}" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-envelope me-1"></i>Contact Listing Owner
                    </a>
                @endif
                <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#bolQuestionModal">
                    <i class="fa-solid fa-circle-question me-1"></i>Ask a Question
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Edit Button (bottom, owner only) --}}
    @if(auth()->check() && auth()->id() === $auction->user_id)
    <div class="text-end mt-2 mb-4">
        <a href="{{ route('offer.listing.buyer.edit', ['auctionId' => $auction->id]) }}"
           class="btn btn-primary">
            <i class="fa-solid fa-pen-to-square me-1"></i> Edit Listing
        </a>
    </div>
    @endif

        </div>{{-- /col-lg-9 --}}

        {{-- Sticky sidebar --}}
        <div class="col-lg-3 d-none d-lg-block">
            <div class="bol-sticky-card">
                <div class="bol-sticky-title"><i class="fa-solid fa-bolt me-1"></i>Quick Actions</div>

                <form method="POST" action="{{ route('offers.store') }}">
                    @csrf
                    <input type="hidden" name="offer_auction_id" value="{{ $auction->id }}">
                    <input type="hidden" name="role" value="buyer">
                    <button type="submit" class="bol-action-btn bol-action-primary">
                        <i class="fa-solid fa-reply"></i>Respond to Buyer Criteria
                    </button>
                </form>
                {{-- Option A: Ask AI added to sidebar to match Seller view --}}
                <button class="bol-action-btn bol-action-outline" data-bs-toggle="modal" data-bs-target="#bolAiModal">
                    <i class="fa-solid fa-robot"></i>Ask AI About Criteria
                </button>
                <button class="bol-action-btn bol-action-outline" data-bs-toggle="modal" data-bs-target="#bolQuestionModal">
                    <i class="fa-solid fa-circle-question"></i>Ask a Question
                </button>
                <button type="button" class="bol-action-btn bol-action-outline" id="bolShareBtn">
                    <i class="fa-solid fa-share-nodes"></i>Share Listing
                </button>
                <button type="button" class="bol-action-btn bol-action-outline"
                        data-bs-toggle="modal" data-bs-target="#bolHireAgentModal"
                        style="border-color:#0f766e;color:#0f766e;">
                    <i class="fa-solid fa-user-tie"></i>Hire an Agent
                </button>
                <button type="button" class="bol-action-btn bol-action-outline" disabled style="cursor:default;opacity:.6;">
                    <i class="fa-regular fa-bookmark"></i>Save Listing
                </button>

                <div style="margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid #f1f5f9;">

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

                @if($hasBPTimer)
                @php
                    $_bolSidebarEndDate = null;
                    $_bolExpDateStr = $str('expiration_date');
                    if ($_bolExpDateStr) {
                        $_bolSidebarEndDate = $fmtDate($_bolExpDateStr);
                    } elseif (!empty($_timerEnd) && $_timerEnd instanceof \Carbon\Carbon) {
                        $_bolSidebarEndDate = $_timerEnd->format('M j, Y');
                    }
                @endphp
                @if($_bolSidebarEndDate)
                <div class="mb-3 pb-3" style="border-bottom:1px solid #f1f5f9;">
                    <div class="d-flex justify-content-between" style="font-size:.78rem;color:#64748b;">
                        <span><i class="fa-regular fa-clock me-1"></i>Bidding Ends</span>
                        <span style="font-weight:700;color:#475569;">{{ $_bolSidebarEndDate }}</span>
                    </div>
                </div>
                @endif
                @endif

                <a href="{{ route('offer.listing.buyer.searchListing') }}"
                   class="bol-action-btn bol-action-outline" style="justify-content:center;text-align:center;">
                    <i class="fa-solid fa-arrow-left"></i>Back to Search
                </a>

                {{-- Activity section hidden until live data is available --}}
                @if(false)
                <div style="margin-top:1rem;padding-top:0.75rem;border-top:1px solid #f1f5f9;">
                    <div style="font-size:0.74rem;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:0.5rem;">Activity</div>
                    <div class="d-flex justify-content-between mb-1" style="font-size:.78rem;color:#64748b;">
                        <span>Views</span><span style="font-weight:700;color:#94a3b8;">Coming Soon</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1" style="font-size:.78rem;color:#64748b;">
                        <span>Saves</span><span style="font-weight:700;color:#94a3b8;">Coming Soon</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1" style="font-size:.78rem;color:#64748b;">
                        <span>Questions</span><span style="font-weight:700;color:#94a3b8;">Coming Soon</span>
                    </div>
                    @if($heroUpdDate)
                    <div class="d-flex justify-content-between" style="font-size:.78rem;color:#64748b;margin-top:.3rem;padding-top:.3rem;border-top:1px solid #f1f5f9;">
                        <span>Updated</span><span style="font-weight:700;color:#475569;">{{ $auction->updated_at ? \Carbon\Carbon::parse($auction->updated_at)->format('M j, Y') : '' }}</span>
                    </div>
                    @endif
                </div>
                @endif

                </div>{{-- /data summary panel --}}
            </div>
        </div>{{-- /col-lg-3 --}}

    </div>{{-- /row --}}

    {{-- ===== MODALS ===== --}}

    {{-- Modal: Ask a Question --}}
    <div class="modal fade" id="bolQuestionModal" tabindex="-1" aria-labelledby="bolQuestionModalLabel" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content" style="border-radius:.85rem;overflow:hidden;border:none;">
                <div class="modal-header bol-modal-header">
                    <h5 class="modal-title fw-bold" id="bolQuestionModalLabel"><i class="fa-solid fa-circle-question me-2"></i>Ask a Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1);"></button>
                </div>
                <form method="POST" action="{{ route('offer.listing.buyer.question', ['auction' => $auction->id]) }}">
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
                            <input type="text" class="form-control @error('name', 'bolQuestionInquiry') is-invalid @enderror" name="name" placeholder="Jane Smith" value="{{ old('name') }}" required>
                            @error('name', 'bolQuestionInquiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email', 'bolQuestionInquiry') is-invalid @enderror" name="email" placeholder="jane@example.com" value="{{ old('email') }}" required>
                            @error('email', 'bolQuestionInquiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Phone Number</label>
                            <input type="tel" class="form-control" name="phone" placeholder="(555) 000-0000" value="{{ old('phone') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Your Question <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('question', 'bolQuestionInquiry') is-invalid @enderror" name="question" rows="4" placeholder="What would you like to know about this Buyer Criteria listing?" required>{{ old('question') }}</textarea>
                            @error('question', 'bolQuestionInquiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
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


    {{-- Modal: Schedule a Showing (UI only — route offer.listing.buyer.showing does not exist. Wiring pending.) --}}
    <div class="modal fade" id="bolShowingModal" tabindex="-1" aria-labelledby="bolShowingModalLabel" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content" style="border-radius:.85rem;overflow:hidden;border:none;">
                <div class="modal-header bol-modal-header">
                    <h5 class="modal-title fw-bold" id="bolShowingModalLabel"><i class="fa-solid fa-calendar-days me-2"></i>Schedule a Showing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1);"></button>
                </div>
                {{-- Route offer.listing.buyer.showing does not exist. Wiring pending. --}}
                <form action="#" method="POST">
                @csrf
                <input type="text" name="website" value="" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;" tabindex="-1" autocomplete="off" aria-hidden="true">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Your Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" placeholder="Jane Smith" value="{{ old('name') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" placeholder="jane@example.com" value="{{ old('email') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Phone Number</label>
                            <input type="tel" class="form-control" name="phone" placeholder="(555) 000-0000" value="{{ old('phone') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Preferred Date</label>
                            <input type="date" class="form-control" name="preferred_date" value="{{ old('preferred_date') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Preferred Time</label>
                            <input type="time" class="form-control" name="preferred_time" value="{{ old('preferred_time') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Message (Optional)</label>
                            <textarea class="form-control" name="message" rows="3" placeholder="Any special requests or notes…">{{ old('message') }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-secondary" disabled>
                        <i class="fa-solid fa-calendar-check me-1"></i>Coming Soon
                    </button>
                </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Photo lightbox modal --}}
    <div class="modal fade" id="bolPhotoModal" tabindex="-1" aria-label="Buyer listing photo" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark border-0">
                <div class="modal-header border-0 pb-0">
                    <span class="text-white small" id="bolPhotoModalCounter"></span>
                    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-2">
                    <img id="bolPhotoModalImg" src="" alt="Buyer listing photo" style="max-width:100%;max-height:70vh;object-fit:contain;border-radius:6px;">
                </div>
                <div class="modal-footer border-0 justify-content-center gap-3 pt-0">
                    <button type="button" class="btn btn-outline-light btn-sm px-4" id="bolPhotoModalPrev">&#8249; Prev</button>
                    <button type="button" class="btn btn-outline-light btn-sm px-4" id="bolPhotoModalNext">Next &#8250;</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: Ask AI About This Buyer Listing --}}
    <div class="modal fade" id="bolAiModal" tabindex="-1" aria-labelledby="bolAiModalLabel" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content" style="border-radius:.85rem;overflow:hidden;border:none;">
                <div class="modal-header bol-modal-header">
                    <h5 class="modal-title fw-bold" id="bolAiModalLabel"><i class="fa-solid fa-robot me-2"></i>Ask AI About This Buyer Listing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1);"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted mb-3" style="font-size:.875rem;">Get instant AI-powered answers about this buyer's criteria. Try asking:</p>
                    <div id="bolAiExamples" class="mb-3 p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0;min-height:60px;">
                        <span class="text-muted fst-italic" style="font-size:.875rem;" id="bolAiExampleText"></span>
                    </div>
                    @php $__bolAiSuggestions = app(\App\Services\AskAi\AskAiSuggestedQuestionsService::class)->forListing('buyer'); @endphp
                    @if(!empty($__bolAiSuggestions))
                    <div id="bolAiSuggestions"
                         aria-label="Suggested questions for this listing"
                         aria-live="polite"
                         class="mb-3">
                        <div class="text-muted mb-2" style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Suggested Questions</div>
                        <div class="ask-ai-chip-wrap">
                            @foreach($__bolAiSuggestions as $__sq)
                            @php $__sqLabel = mb_strlen($__sq['question']) > 80 ? mb_substr($__sq['question'], 0, 79) . '…' : $__sq['question']; @endphp
                            <button type="button"
                                    role="button"
                                    class="ask-ai-chip"
                                    data-question="{{ $__sq['question'] }}"
                                    aria-label="{{ $__sq['question'] }}"
                                    title="{{ $__sq['question'] }}">
                                <span class="text-muted me-2" style="font-size:.7rem;"><i class="fa-solid {{ $__sq['category_icon'] }} me-1" aria-hidden="true"></i>{{ $__sq['category_label'] }}</span>{{ $__sqLabel }}
                            </button>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    <p class="text-muted mb-3" style="font-size:.73rem;border-top:1px solid #f1f5f9;padding-top:.6rem;line-height:1.45;">
                        <i class="fa-solid fa-shield-halved me-1 text-secondary" aria-hidden="true"></i>Ask AI provides informational summaries based on listing data and platform content only. It is not a licensed real estate broker, attorney, lender, tax advisor, or financial advisor. Nothing here constitutes professional advice. Always consult a qualified professional before making real estate decisions.
                    </p>
                    <label class="form-label fw-semibold" style="font-size:.85rem;">Your Question</label>
                    <textarea class="form-control" rows="4" id="bolAiTextarea"
                              placeholder="What would you like to know?"
                              maxlength="1000"></textarea>
                    <div id="bolAiResult" style="display:none;margin-top:1rem;"></div>
                </div>
                <div class="modal-footer border-0 pb-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="bolAiSubmitBtn">
                        <span id="bolAiSubmitSpinner" class="spinner-border spinner-border-sm me-1" role="status" style="display:none;"></span>
                        <i class="fa-solid fa-robot me-1" id="bolAiSubmitIcon"></i><span id="bolAiSubmitLabel">Ask AI</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>{{-- /container --}}

{{-- Mobile sticky bottom bar --}}
<div class="bol-mobile-bar d-lg-none">
    <a href="{{ route('offer.listing.buyer.searchListing') }}" class="bol-mobile-bar-btn">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Back</span>
    </a>
    <button type="button" class="bol-mobile-bar-btn"
            data-bs-toggle="modal" data-bs-target="#bolHireAgentModal">
        <i class="fa-solid fa-user-tie"></i>
        <span>Agent</span>
    </button>
    <button class="bol-mobile-bar-btn" data-bs-toggle="modal" data-bs-target="#bolQuestionModal">
        <i class="fa-solid fa-circle-question"></i>
        <span>Ask</span>
    </button>
    <button class="bol-mobile-bar-btn" data-bs-toggle="modal" data-bs-target="#bolAiModal">
        <i class="fa-solid fa-robot"></i>
        <span>Ask AI</span>
    </button>
    <form method="POST" action="{{ route('offers.store') }}" style="display:contents;">
        @csrf
        <input type="hidden" name="offer_auction_id" value="{{ $auction->id }}">
        <input type="hidden" name="role" value="buyer">
        <button type="submit" class="bol-mobile-bar-btn bol-mobile-bar-respond">
            <i class="fa-solid fa-reply"></i>
            <span>Respond</span>
        </button>
    </form>
    {{-- Option A: Ask AI added to mobile bar to match Seller view --}}
    <button class="bol-mobile-bar-btn" data-bs-toggle="modal" data-bs-target="#bolAiModal">
        <i class="fa-solid fa-robot"></i>
        <span>Ask AI</span>
    </button>
    @if(auth()->check() && auth()->id() === $auction->user_id)
    <a href="{{ route('offer.listing.buyer.edit', ['auctionId' => $auction->id]) }}" class="bol-mobile-bar-btn">
        <i class="fa-solid fa-pen-to-square"></i>
        <span>Edit</span>
    </a>
    @endif
</div>

{{-- ===== HIRE AGENT MODAL ===== --}}
<x-hire-agent-modal
    listing-id="{{ $auction->id }}"
    listing-type="buyer_offer"
    listing-role="buyer"
    :listing-title="$listingTitle ?? ''"
    prefill-prop-type="{{ $meta['property_type'] ?? '' }}"
    modal-id="bolHireAgentModal"
/>

@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    /* ---- Bidding period countdown timer ---- */
    (function () {
        function bolBpFormat(s) {
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
        document.querySelectorAll('.bol-bp-timer[data-seconds]').forEach(function (el) {
            var secs = parseInt(el.getAttribute('data-seconds'), 10) || 0;
            el.textContent = bolBpFormat(secs);
            if (secs <= 0) { el.classList.replace('bg-info', 'bg-secondary'); return; }
            var iv = setInterval(function () {
                secs--;
                el.textContent = bolBpFormat(secs);
                if (secs <= 0) {
                    clearInterval(iv);
                    el.classList.remove('bg-info', 'text-dark');
                    el.classList.add('bg-secondary');
                }
            }, 1000);
        });
    }());

    /* ---- Smooth-scroll + active-section highlighting ---- */
    var BOL_OFFSET = 82;
    var bolNavLinks = Array.from(document.querySelectorAll('#bolNavTabs a[href^="#"]'));
    var bolSections  = bolNavLinks.map(function (a) { return document.querySelector(a.getAttribute('href')); }).filter(Boolean);
    bolSections.sort(function (a, b) { return a.offsetTop - b.offsetTop; });

    bolNavLinks.forEach(function (a) {
        a.addEventListener('click', function (e) {
            var target = document.querySelector(a.getAttribute('href'));
            if (!target) return;
            e.preventDefault();
            var top = target.getBoundingClientRect().top + window.scrollY - BOL_OFFSET;
            window.scrollTo({ top: top, behavior: 'smooth' });
        });
    });

    function bolOnScroll() {
        var scrollY = window.scrollY + BOL_OFFSET + 10;
        var active = null;
        bolSections.forEach(function (s) {
            if (s && s.offsetTop <= scrollY) active = s;
        });
        bolNavLinks.forEach(function (a) { a.classList.remove('bol-nav-active'); });
        if (active) {
            var link = document.querySelector('#bolNavTabs a[href="#' + active.id + '"]');
            if (link) link.classList.add('bol-nav-active');
        }
    }
    window.addEventListener('scroll', bolOnScroll, { passive: true });
    bolOnScroll();

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

    /* ---- Interaction Hub ---- */
    (function () {
        var bolAiExamples = [
            'What financing type does this buyer prefer?',
            'Is this buyer pre-approved for a mortgage?',
            'What property features are required vs. preferred?',
            'What is this buyer\'s ideal timeline to close?',
            'What contingencies does this buyer need?',
            'How many bedrooms and bathrooms does this buyer require?',
            'What locations or neighborhoods is this buyer targeting?'
        ];
        var bolAiIdx = 0;
        var bolAiEl = document.getElementById('bolAiExampleText');
        var bolAiModal = document.getElementById('bolAiModal');
        if (bolAiEl && bolAiModal) {
            bolAiEl.textContent = bolAiExamples[0];
            bolAiModal.addEventListener('show.bs.modal', function () {
                bolAiEl.textContent = bolAiExamples[bolAiIdx % bolAiExamples.length];
                bolAiIdx++;
            });
        }
        var hubNativeBtn = document.getElementById('bolHubNativeShareBtn');
        if (navigator.share && hubNativeBtn) { hubNativeBtn.style.display = ''; }
        var hubCopyBtn = document.getElementById('bolHubCopyBtn');
        if (hubCopyBtn) {
            hubCopyBtn.addEventListener('click', function () {
                var url = window.location.href;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(function () {
                        alert('Link copied to clipboard!');
                    }).catch(function () { alert('Share: ' + url); });
                } else { alert('Share: ' + url); }
            });
        }
        if (hubNativeBtn) { hubNativeBtn.addEventListener('click', shareHandler); }
    }());

    /* Auto-reopen question modal after validation failure */
    @if(session('open_modal') === 'question')
    (function () {
        var el = document.getElementById('bolQuestionModal');
        if (el && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(el).show();
        }
    }());
    @endif

    /* ---- Ask AI modal — live submit ---- */
    (function () {
        var submitBtn  = document.getElementById('bolAiSubmitBtn');
        var textarea   = document.getElementById('bolAiTextarea');
        var resultDiv  = document.getElementById('bolAiResult');
        var spinner    = document.getElementById('bolAiSubmitSpinner');
        var icon       = document.getElementById('bolAiSubmitIcon');
        var label      = document.getElementById('bolAiSubmitLabel');
        var modalEl    = document.getElementById('bolAiModal');
        var listingId  = {{ $auction->id }};
        var csrfToken  = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '';

        function resetResult() {
            if (resultDiv) { resultDiv.style.display = 'none'; resultDiv.innerHTML = ''; }
        }

        if (resultDiv) {
            resultDiv.addEventListener('click', function (e) {
                var chip = e.target.closest ? e.target.closest('.ask-ai-chip') : (e.target.classList && e.target.classList.contains('ask-ai-chip') ? e.target : null);
                if (!chip) return;
                var q = chip.getAttribute('data-question');
                if (q && textarea) { textarea.value = q; textarea.focus(); }
            });
        }

        if (modalEl) {
            modalEl.addEventListener('hidden.bs.modal', function () {
                if (textarea) textarea.value = '';
                resetResult();
            });
        }

        function escHtml(str) {
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function renderResult(data) {
            if (!resultDiv) return;
            var html = '';
            var status = data.status || 'failed';
            if (status === 'ready' && data.answer) {
                html += '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:.5rem;padding:.9rem 1rem;margin-bottom:.5rem;">';
                html += '<div style="font-size:.8rem;font-weight:700;color:#15803d;margin-bottom:.35rem;"><i class="fa-solid fa-robot me-1"></i>AI Answer</div>';
                html += '<div style="font-size:.875rem;color:#1e293b;white-space:pre-wrap;">' + escHtml(data.answer) + '</div>';
                html += '</div>';
                if (data.disclosures) {
                    var disc = Array.isArray(data.disclosures) ? data.disclosures.join(' ') : String(data.disclosures);
                    if (disc.trim()) {
                        html += '<div style="font-size:.75rem;color:#64748b;margin-top:.4rem;padding:.5rem .75rem;background:#f8fafc;border-radius:.4rem;border:1px solid #e2e8f0;">' + escHtml(disc) + '</div>';
                    }
                }
                if (data.source_attribution) {
                    var src = Array.isArray(data.source_attribution) ? data.source_attribution.join(', ') : String(data.source_attribution);
                    if (src.trim()) {
                        html += '<div style="font-size:.72rem;color:#94a3b8;margin-top:.3rem;">Source: ' + escHtml(src) + '</div>';
                    }
                }
                if (Array.isArray(data.follow_up_questions) && data.follow_up_questions.length > 0) {
                    html += '<div style="margin-top:.75rem;padding-top:.6rem;border-top:1px solid #e2e8f0;">';
                    html += '<div style="font-size:.72rem;font-weight:700;color:#64748b;margin-bottom:.45rem;text-transform:uppercase;letter-spacing:.04em;">Follow-up questions</div>';
                    html += '<div class="ask-ai-chip-wrap" id="bolAiFollowUpChips">';
                    data.follow_up_questions.forEach(function (chip) {
                        html += '<button type="button" class="ask-ai-chip" data-question="' + escHtml(chip.question) + '">' + escHtml(chip.label) + '</button>';
                    });
                    html += '</div></div>';
                }
            } else if (status === 'blocked' && data.refusal_message) {
                html += '<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:.5rem;padding:.9rem 1rem;">';
                html += '<div style="font-size:.8rem;font-weight:700;color:#b45309;margin-bottom:.35rem;"><i class="fa-solid fa-shield-halved me-1"></i>Notice</div>';
                html += '<div style="font-size:.875rem;color:#1e293b;">' + escHtml(data.refusal_message) + '</div>';
                html += '</div>';
            } else if ((status === 'unsupported' || status === 'insufficient_context') && data.answer) {
                html += '<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:.5rem;padding:.9rem 1rem;margin-bottom:.5rem;">';
                html += '<div style="font-size:.8rem;font-weight:700;color:#0369a1;margin-bottom:.35rem;"><i class="fa-solid fa-circle-info me-1"></i>Notice</div>';
                html += '<div style="font-size:.875rem;color:#1e293b;">' + escHtml(data.answer) + '</div>';
                html += '</div>';
                if (data.disclosures) {
                    var disc2 = Array.isArray(data.disclosures) ? data.disclosures.join(' ') : String(data.disclosures);
                    if (disc2.trim()) {
                        html += '<div style="font-size:.75rem;color:#64748b;margin-top:.4rem;padding:.5rem .75rem;background:#f8fafc;border-radius:.4rem;border:1px solid #e2e8f0;">' + escHtml(disc2) + '</div>';
                    }
                }
            } else {
                html += '<div style="background:#fff1f2;border:1px solid #fecdd3;border-radius:.5rem;padding:.9rem 1rem;">';
                html += '<div style="font-size:.875rem;color:#be123c;">Ask AI could not generate a response right now. Please try again later.</div>';
                html += '</div>';
            }
            resultDiv.innerHTML = html;
            resultDiv.style.display = '';
        }

        function setLoading(on) {
            if (!submitBtn) return;
            submitBtn.disabled = on;
            if (spinner) spinner.style.display = on ? 'inline-block' : 'none';
            if (icon)    icon.style.display    = on ? 'none' : '';
            if (label)   label.textContent      = on ? 'Thinking…' : 'Ask AI';
        }

        if (submitBtn) {
            submitBtn.addEventListener('click', function () {
                var question = textarea ? textarea.value.trim() : '';
                if (!question) { if (textarea) textarea.focus(); return; }
                resetResult();
                setLoading(true);
                fetch('/ask-ai/listing-question', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ listing_type: 'buyer', listing_id: listingId, question: question })
                })
                .then(function (r) { return r.json(); })
                .then(function (data) { setLoading(false); renderResult(data); })
                .catch(function () {
                    setLoading(false);
                    renderResult({ status: 'failed' });
                });
            });
        }
    }());

})();

/* ---- Buyer hero carousel + photo lightbox ---- */
(function () {
    var photos = (typeof _bolHeroPhotos !== 'undefined') ? _bolHeroPhotos : [];
    if (!photos.length) return;

    var heroIdx     = (typeof _bolHeroStartIdx !== 'undefined') ? _bolHeroStartIdx : 0;
    var heroImg     = document.getElementById('bolHeroCarouselImg');
    var heroPrev    = document.getElementById('bolHeroCarouselPrev');
    var heroNext    = document.getElementById('bolHeroCarouselNext');
    var heroCounter = document.getElementById('bolHeroCarouselCounter');

    /* Lightbox elements */
    var lbImg     = document.getElementById('bolPhotoModalImg');
    var lbCounter = document.getElementById('bolPhotoModalCounter');
    var lbPrev    = document.getElementById('bolPhotoModalPrev');
    var lbNext    = document.getElementById('bolPhotoModalNext');
    var lbModalEl = document.getElementById('bolPhotoModal');
    var lbModal   = lbModalEl && typeof bootstrap !== 'undefined'
                    ? bootstrap.Modal.getOrCreateInstance(lbModalEl) : null;
    var lbIdx = 0;

    function showLb(idx) {
        if (idx < 0) idx = photos.length - 1;
        if (idx >= photos.length) idx = 0;
        lbIdx = idx;
        if (lbImg)     lbImg.src = photos[lbIdx];
        if (lbCounter) lbCounter.textContent = (lbIdx + 1) + ' / ' + photos.length;
    }
    window._bolShowPhoto = function (idx) { showLb(idx); };
    if (lbPrev) lbPrev.addEventListener('click', function () { showLb(lbIdx - 1); });
    if (lbNext) lbNext.addEventListener('click', function () { showLb(lbIdx + 1); });

    function updateHero(idx) {
        if (idx < 0) idx = photos.length - 1;
        if (idx >= photos.length) idx = 0;
        heroIdx = idx;
        if (heroImg)     heroImg.src = photos[heroIdx];
        if (heroCounter) heroCounter.textContent = (heroIdx + 1) + ' / ' + photos.length;
    }
    if (heroPrev) heroPrev.addEventListener('click', function () { updateHero(heroIdx - 1); });
    if (heroNext) heroNext.addEventListener('click', function () { updateHero(heroIdx + 1); });
    if (heroImg) {
        heroImg.addEventListener('click', function () {
            showLb(heroIdx);
            if (lbModal) lbModal.show();
        });
    }
    updateHero(heroIdx);
}());

/* ---- Ask AI Suggestion Chips ---- */
(function () {
    var STORAGE_KEY = 'askAiUsedQuestions';
    var modalEl   = document.getElementById('bolAiModal');
    var chipsWrap = document.getElementById('bolAiSuggestions');
    if (!chipsWrap) return;

    function getUsed() {
        try { return JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]'); } catch (e) { return []; }
    }
    function markUsed(q) {
        var used = getUsed();
        if (used.indexOf(q) === -1) used.push(q);
        try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify(used)); } catch (e) {}
    }
    function applyUsed() {
        var used = getUsed();
        chipsWrap.querySelectorAll('.ask-ai-chip').forEach(function (btn) {
            if (used.indexOf(btn.getAttribute('data-question')) !== -1) btn.style.display = 'none';
        });
    }

    if (modalEl) modalEl.addEventListener('show.bs.modal', function () { applyUsed(); });

    chipsWrap.querySelectorAll('.ask-ai-chip').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var q  = btn.getAttribute('data-question');
            var ta = document.getElementById('bolAiTextarea');
            if (ta) { ta.value = q; ta.focus(); }
            markUsed(q);
            btn.style.display = 'none';
        });
        btn.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); btn.click(); }
        });
    });
}());
</script>
@endpush
