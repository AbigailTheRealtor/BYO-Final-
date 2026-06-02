@extends('layouts.main')

@php
    /* ─────────────────────────────────────────────────────────────
       Helper closures — all $meta-aware, declared once at top.
       ───────────────────────────────────────────────────────────── */

    /** Format a numeric value as "$1,234" — returns null when empty */
    $fmtMoney = function ($v): ?string {
        if ($v === null || $v === '') return null;
        $raw = preg_replace('/[^0-9.]/', '', (string) $v);
        if ($raw === '' || !is_numeric($raw)) return null;
        return '$' . number_format((float) $raw, 0);
    };

    /** Format a numeric value as "12.5%" — returns null when empty */
    $fmtPercent = function ($v): ?string {
        if ($v === null || $v === '') return null;
        $raw = preg_replace('/[^0-9.]/', '', (string) $v);
        if ($raw === '' || !is_numeric($raw)) return null;
        $num = (float) $raw;
        return (floor($num) == $num ? (string)(int)$num : (string)$num) . '%';
    };

    /** Parse a date string and return "Month D, YYYY" — null on failure */
    $fmtDate = function ($v): ?string {
        if ($v === null || $v === '') return null;
        try { return \Carbon\Carbon::parse((string)$v)->format('F j, Y'); }
        catch (\Exception $e) { return null; }
    };

    /** Return meta value as a plain string (array → comma list, nested arrays → JSON) */
    $str = function (string $key) use ($meta): string {
        $v = $meta[$key] ?? '';
        return is_array($v) ? implode(', ', array_map(fn($e) => is_array($e) ? json_encode($e) : (string)$e, $v)) : (string) $v;
    };

    /** Return meta value as an array (double-decode JSON strings) */
    $arr = function (string $key) use ($meta): array {
        $v = $meta[$key] ?? [];
        if (is_string($v)) {
            $d = json_decode($v, true);
            if (is_string($d)) $d = json_decode($d, true);
            return is_array($d) ? $d : [];
        }
        return is_array($v) ? $v : [];
    };

    /**
     * "Other" companion pattern:
     *   - primary === 'Other' AND companion non-empty → return companion
     *   - primary === 'Other' AND companion empty     → return '' (suppress)
     *   - anything else                               → return primary
     */
    $orOther = function (string $primary, string $companion): string {
        if ($primary === 'Other') return $companion;   // empty companion = empty string → suppressed by $row
        return $primary;
    };

    /**
     * Array "Other" companion pattern:
     *   - replaces the literal string 'Other' in $items with $companion
     *   - if $companion is empty the 'Other' item is DROPPED (not rendered)
     */
    $subOther = function (array $items, string $companion): array {
        $out = [];
        foreach ($items as $v) {
            if ((string)$v === 'Other') {
                if ($companion !== '') $out[] = $companion;
            } else {
                $out[] = $v;
            }
        }
        return $out;
    };

    /** Coerce a raw 1/0/true/false/yes/no value to "Yes" / "No" / original */
    $yesNo = function ($v): string {
        return match (strtolower((string)$v)) {
            '1', 'true', 'yes' => 'Yes',
            '0', 'false', 'no' => 'No',
            default            => (string)$v,
        };
    };

    /**
     * Render a label-value pair as a two-column Bootstrap row.
     * Returns '' when value is null / '' / false so callers can use {!! !!} safely.
     */
    $row = function ($label, $value): string {
        if ($value === null || $value === '' || $value === false) return '';
        return '<div class="row mb-2">'
            . '<div class="col-md-5 text-muted fw-semibold" style="font-size:.875rem;">' . e($label) . '</div>'
            . '<div class="col-md-7" style="overflow-wrap:break-word;word-break:break-word;font-size:.925rem;">' . e($value) . '</div>'
            . '</div>';
    };

    /** Same as $row but accepts a pre-built array and joins with ", " */
    $listRow = function ($label, array $items) use ($row): string {
        $items = array_values(array_filter($items, fn($v) => $v !== '' && $v !== null));
        if (!count($items)) return '';
        return $row($label, implode(', ', $items));
    };
@endphp

@push('styles')
<style>
/* ============================================================
   lol-view-page — Landlord Offer Listing detail page styles
   ============================================================ */
.lol-view-page .section-card {
    margin-bottom: 1.75rem;
    border-radius: 0.75rem;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 6px rgba(0,0,0,.06);
    overflow: hidden;
}
.lol-view-page .section-card .card-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    font-weight: 700;
    font-size: 1.05rem;
    padding: 0.9rem 1.25rem;
    border-bottom: 1px solid #e2e8f0;
    letter-spacing: -0.01em;
    color: #1e293b;
}
.lol-view-page .section-card .card-body { padding: 1.25rem 1.5rem; }
.lol-view-page hr { border-color: #e9ecef; opacity: 0.6; margin: 1rem 0; }
.lol-view-page .row.mb-2 { margin-bottom: 0.65rem !important; }

/* Hero */
.lol-view-page .lol-hero { border-radius: 1rem; overflow: hidden; margin-bottom: 1.5rem; box-shadow: 0 4px 24px rgba(0,0,0,.10); background: #1e293b; }
.lol-view-page .lol-hero-carousel-wrap { position: relative; height: 100%; min-height: 280px; overflow: hidden; background: #0f172a; }
.lol-view-page .lol-hero-photo { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: block; }
.lol-view-page .lol-hero-photo-placeholder { min-height: 280px; background: linear-gradient(135deg, #0f4c3a, #0f172a); display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 3rem; }
.lol-view-page .lol-hero-summary { background: #fff; padding: 1.6rem 1.4rem; display: flex; flex-direction: column; justify-content: space-between; height: 100%; min-height: 280px; gap: 0.1rem; }
.lol-view-page .lol-hero-price { font-size: 1.85rem; font-weight: 800; color: #1e293b; letter-spacing: -0.03em; line-height: 1.15; }
.lol-view-page .lol-hero-address { color: #475569; font-size: 0.9rem; margin-top: 0.3rem; word-break: break-word; overflow-wrap: anywhere; line-height: 1.45; }
.lol-view-page .lol-hero-meta { display: flex; flex-wrap: wrap; gap: 0.4rem 0.9rem; margin-top: 0.65rem; font-size: 0.84rem; color: #334155; }
.lol-view-page .lol-hero-meta-item i { color: #0f766e; margin-right: 4px; }
.lol-view-page .lol-hero-badges { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.75rem; }
.lol-view-page .lol-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 0.73rem; font-weight: 600; padding: 0.25rem 0.55rem; border-radius: 20px; border: 1px solid; white-space: nowrap; }
.lol-view-page .lol-badge-teal   { background:#f0fdfa; color:#0f766e; border-color:#99f6e4; }
.lol-view-page .lol-badge-green  { background:#f0fdf4; color:#15803d; border-color:#bbf7d0; }
.lol-view-page .lol-badge-blue   { background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe; }
.lol-view-page .lol-badge-purple { background:#faf5ff; color:#7c3aed; border-color:#ddd6fe; }
.lol-view-page .lol-badge-amber  { background:#fffbeb; color:#b45309; border-color:#fde68a; }
.lol-view-page .lol-hero-status { display:inline-flex;align-items:center;gap:6px;font-size:.8rem;font-weight:700;padding:.28rem .7rem;border-radius:20px;background:#f0fdfa;color:#0f766e;border:1px solid #99f6e4;margin-top:.6rem;white-space:nowrap; }
.lol-view-page .lol-hero-dates { font-size:.76rem;color:#94a3b8;margin-top:.4rem; }
.lol-view-page .lol-hero-ctas { display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.9rem;padding-top:.9rem;border-top:1px solid #f1f5f9; }
.lol-view-page .lol-hero-ctas .btn { font-size:.8rem;font-weight:600;padding:.42rem .75rem;border-radius:8px;white-space:nowrap;flex-shrink:0; }
.lol-view-page .lol-hero-arrow { position:absolute;top:50%;transform:translateY(-50%);background:rgba(0,0,0,.45);color:#fff;border:none;border-radius:50%;width:40px;height:40px;font-size:1.55rem;line-height:1;cursor:pointer;z-index:10;display:flex;align-items:center;justify-content:center;transition:background .15s;padding:0;flex-shrink:0; }
.lol-view-page .lol-hero-arrow:hover { background:rgba(0,0,0,.72); }
.lol-view-page .lol-hero-arrow-prev { left:12px; }
.lol-view-page .lol-hero-arrow-next { right:12px; }
.lol-view-page .lol-hero-carousel-counter { position:absolute;bottom:12px;right:14px;background:rgba(0,0,0,.50);color:#fff;font-size:.76rem;font-weight:600;padding:3px 10px;border-radius:20px;z-index:10;pointer-events:none; }

/* Photo thumbnails */
.lol-view-page .photo-thumb { width:120px;height:90px;object-fit:cover;border-radius:10px;border:2px solid #e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,.10);transition:transform .2s ease,box-shadow .2s ease;display:block; }
.lol-view-page .photo-thumb:hover { transform:scale(1.06);box-shadow:0 6px 20px rgba(0,0,0,.18); }
.lol-view-page .cover-badge { font-size:.68rem;background:#0f766e;color:#fff;border-radius:4px;padding:2px 6px;font-weight:600; }

/* Nav tabs */
.lol-view-page .lol-nav-tabs-wrap { background:#fff;border-bottom:2px solid #e2e8f0;margin-bottom:1.75rem;box-shadow:0 2px 8px rgba(0,0,0,.06); }
.lol-view-page .lol-nav-tabs { display:flex;overflow-x:auto;scrollbar-width:none;-ms-overflow-style:none;gap:0;list-style:none;padding:0;margin:0; }
.lol-view-page .lol-nav-tabs::-webkit-scrollbar { display:none; }
.lol-view-page .lol-nav-tabs li a { display:block;padding:.75rem 1.1rem;font-size:.82rem;font-weight:600;color:#64748b;text-decoration:none;white-space:nowrap;border-bottom:2px solid transparent;margin-bottom:-2px;transition:color .15s,border-color .15s; }
.lol-view-page .lol-nav-tabs li a:hover,
.lol-view-page .lol-nav-tabs li a.lol-nav-active { color:#0f766e;border-bottom-color:#0f766e; }

/* Sticky sidebar */
.lol-view-page .lol-sticky-card { position:sticky;top:72px;background:#fff;border-radius:.75rem;border:1px solid #e2e8f0;box-shadow:0 4px 16px rgba(0,0,0,.08);padding:1.25rem 1rem; }
.lol-view-page .lol-sticky-card .lol-sticky-title { font-size:.78rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.75rem;padding-bottom:.5rem;border-bottom:1px solid #f1f5f9; }
.lol-view-page .lol-sticky-card .lol-action-btn { display:flex;align-items:center;gap:.6rem;width:100%;padding:.6rem .75rem;font-size:.83rem;font-weight:600;border-radius:8px;margin-bottom:.4rem;text-align:left;border:1px solid transparent;cursor:pointer;transition:background .15s;text-decoration:none; }
.lol-view-page .lol-sticky-card .lol-action-btn i { width:18px;text-align:center;flex-shrink:0; }
.lol-view-page .lol-action-primary { background:#0f766e;color:#fff;border-color:#0f766e; }
.lol-view-page .lol-action-primary:hover { background:#115e59;color:#fff; }
.lol-view-page .lol-action-outline { background:#fff;color:#334155;border-color:#e2e8f0; }
.lol-view-page .lol-action-outline:hover { background:#f8fafc;border-color:#cbd5e1; }

/* Mobile sticky bar */
.lol-mobile-bar { display:none;position:fixed;bottom:0;left:0;right:0;z-index:1030;background:#fff;border-top:1px solid #e2e8f0;box-shadow:0 -4px 16px rgba(0,0,0,.10);padding:.5rem 1rem;padding-bottom:calc(.5rem + env(safe-area-inset-bottom));gap:.5rem; }
.lol-mobile-bar-btn { display:flex;flex-direction:column;align-items:center;gap:3px;flex:1;font-size:.65rem;font-weight:700;color:#334155;text-decoration:none;padding:.4rem .25rem;border-radius:8px;border:none;background:transparent;cursor:pointer;transition:background .15s;min-height:52px;justify-content:center; }
.lol-mobile-bar-btn i { font-size:1.15rem;color:#0f766e; }
.lol-mobile-bar-btn:hover,.lol-mobile-bar-btn:active { background:#f1f5f9;color:#1e293b; }
.lol-mobile-bar-btn.lol-mobile-primary { background:#0f766e !important;color:#fff !important;border-radius:10px; }
.lol-mobile-bar-btn.lol-mobile-primary i { color:#fff !important; }
.lol-mobile-bar-btn.lol-mobile-primary:hover { background:#0d5c56 !important; }
@media (max-width:991.98px) {
    .lol-mobile-bar { display:flex; }
    .lol-main-content-wrap { padding-bottom:calc(80px + env(safe-area-inset-bottom)); }
}

/* Contact CTA row */
.lol-view-page .lol-contact-cta-row { display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem;padding-top:1rem;border-top:1px solid #f1f5f9; }

/* Modals */
.lol-view-page .lol-modal-header { background:linear-gradient(135deg,#0f4c3a,#1e293b);color:#fff;border-radius:.75rem .75rem 0 0;padding:1.25rem 1.5rem;border-bottom:none; }

/* ============================================================
   lol-interaction-hub — six-panel action hub
   ============================================================ */
.lol-view-page .lol-interaction-hub {
    margin-bottom: 1.75rem;
    background: linear-gradient(135deg, #f8fafc 0%, #f0fdfa 100%);
    border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.25rem 1rem;
    box-shadow: 0 2px 12px rgba(15,118,110,.07);
}
.lol-view-page .lol-interaction-hub-label {
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.07em; color: #94a3b8;
    margin-bottom: 0.9rem; padding-bottom: 0.6rem; border-bottom: 1px solid #e2e8f0;
}
.lol-view-page .lol-interaction-grid {
    display: grid; grid-template-columns: repeat(6, 1fr); gap: 0.75rem;
}
@media (max-width: 1199.98px) { .lol-view-page .lol-interaction-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 767.98px)  { .lol-view-page .lol-interaction-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 479.98px)  { .lol-view-page .lol-interaction-grid { grid-template-columns: 1fr; } }
.lol-view-page .lol-interaction-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 0.75rem;
    padding: 1rem 0.85rem 0.9rem; display: flex; flex-direction: column;
    gap: 0.4rem; transition: box-shadow .15s, border-color .15s, transform .15s; min-height: 0;
}
.lol-view-page .lol-interaction-card:hover {
    box-shadow: 0 4px 18px rgba(15,118,110,.12); border-color: #99f6e4; transform: translateY(-2px);
}
.lol-view-page .lol-interaction-card-icon { font-size: 1.45rem; color: #0f766e; margin-bottom: 0.1rem; line-height: 1; }
.lol-view-page .lol-interaction-card-label { font-size: 0.83rem; font-weight: 700; color: #1e293b; letter-spacing: -0.01em; line-height: 1.2; }
.lol-view-page .lol-interaction-card-helper { font-size: 0.74rem; color: #64748b; line-height: 1.45; flex: 1; }
.lol-view-page .lol-interaction-cta {
    display: inline-flex; align-items: center; gap: 5px; font-size: 0.75rem; font-weight: 700;
    padding: 0.38rem 0.7rem; border-radius: 7px; border: none; cursor: pointer;
    transition: background .15s, color .15s; white-space: nowrap;
    margin-top: 0.25rem; align-self: flex-start; text-decoration: none;
}
.lol-view-page .lol-interaction-cta:focus-visible { outline: 2px solid #0f766e; outline-offset: 2px; }
.lol-view-page .lol-interaction-cta-primary { background: #0f766e; color: #fff; }
.lol-view-page .lol-interaction-cta-primary:hover { background: #115e59; color: #fff; }
.lol-view-page .lol-interaction-cta-outline { background: #f0fdfa; color: #0f766e; border: 1px solid #99f6e4; }
.lol-view-page .lol-interaction-cta-outline:hover { background: #ccfbf1; color: #115e59; }
.lol-view-page .lol-interaction-cta-muted { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; cursor: default; font-weight: 600; opacity: .75; }
.lol-view-page .lol-interaction-share-row { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.25rem; }
.lol-view-page .lol-interaction-activity-row { display: flex; justify-content: space-between; font-size: 0.72rem; color: #64748b; padding: 0.18rem 0; border-bottom: 1px solid #f1f5f9; }
.lol-view-page .lol-interaction-activity-row:last-child { border-bottom: none; }
.lol-view-page .lol-interaction-activity-val { font-weight: 700; color: #94a3b8; font-size: 0.72rem; }
.lol-view-page .lol-interaction-ai-chips { display: flex; flex-direction: column; gap: 0.28rem; margin-bottom: 0.35rem; }
.lol-view-page .lol-interaction-ai-chip {
    font-size: 0.69rem; color: #0f766e; background: #f0fdfa; border: 1px solid #99f6e4;
    border-radius: 20px; padding: 0.2rem 0.5rem; white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis; max-width: 100%; cursor: default;
}
</style>
@endpush

@section('content')
<div class="container py-4 lol-view-page">

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @php
        /* ── Address display ── */
        $addrParts = array_filter([
            $meta['address'] ?? null,
            $meta['property_city'] ?? null,
        ]);
        $addrState  = trim($meta['property_state'] ?? '');
        $addrZip    = trim($meta['property_zip'] ?? $meta['zip_code'] ?? '');
        $stateZip   = trim($addrState . ($addrState && $addrZip ? ' ' : '') . $addrZip);
        if ($stateZip) $addrParts[] = $stateZip;
        $fullAddress = implode(', ', array_filter($addrParts));

        $pageTitle = ($str('listing_title') ?: $auction->title) ?: ($fullAddress ?: 'Rental Property Listing');

        /* ── Hero price ── */
        $heroPrice = null;
        foreach (['desired_rental_amount','starting_rent','reserve_rent','lease_now_price'] as $_pk) {
            $_pv = $meta[$_pk] ?? '';
            if ($_pv !== '' && $_pv !== null) {
                $heroPrice = '$' . number_format((float)preg_replace('/[^0-9.]/', '', (string)$_pv), 0);
                break;
            }
        }

        /* ── Photos ── */
        $propertyPhotos = $meta['property_photos'] ?? [];
        if (is_string($propertyPhotos)) {
            $d = json_decode($propertyPhotos, true);
            $propertyPhotos = is_array($d) ? $d : [];
        }
        $heroPhotoUrls  = [];
        $coverPhotoIdx  = 0;
        $_hIdx          = 0;
        foreach ($propertyPhotos as $_ph) {
            $_fn = is_array($_ph) ? ($_ph['filename'] ?? '') : $_ph;
            if (!$_fn) continue;
            $heroPhotoUrls[] = asset('storage/auction/images/' . $_fn);
            if (is_array($_ph) && !empty($_ph['is_cover'])) $coverPhotoIdx = $_hIdx;
            $_hIdx++;
        }

        /* ── Hero meta chips ── */
        $heroBeds      = $orOther($str('bedrooms'),  $str('other_bedrooms'));
        $heroBaths     = $orOther($str('bathrooms'), $str('other_bathrooms'));
        $heroSqft      = $str('minimum_heated_square');
        $heroPropType  = $str('property_type');
        $heroStatus    = $auction->status ?? null;
        $heroListDate  = $fmtDate($str('listing_date'));
        $heroUpdDate   = $auction->updated_at ? \Carbon\Carbon::parse($auction->updated_at)->format('F j, Y') : null;

        /* ── Hero badges ── */
        $badgePets     = in_array(strtolower((string)($str('pets') ?: $str('pet_policy'))), ['yes','allowed','1','true']);
        $badgePool     = in_array(strtolower($str('pool_needed')), ['yes','1','true']);
        $badge55Plus   = in_array(strtolower($str('leasing_55_plus')), ['yes','1','true']);
        $leaseLengths  = $subOther($arr('desired_lease_length'), $str('other_lease_for') ?: $str('lease_for'));
        $badgeShortTerm = !empty($leaseLengths) && (in_array('Monthly', $leaseLengths) || in_array('Short-Term', $leaseLengths));
        $heroBadgesDisplay = array_slice(array_values(array_filter([
            ['show' => $badgePets,      'label' => 'Pets Allowed',     'icon' => 'fa-solid fa-paw',            'color' => 'green',  'strong' => true],
            ['show' => $badgePool,      'label' => 'Pool',             'icon' => 'fa-solid fa-water-ladder',   'color' => 'blue',   'strong' => true],
            ['show' => $badge55Plus,    'label' => '55+ Community',    'icon' => 'fa-solid fa-person-cane',    'color' => 'purple', 'strong' => true],
            ['show' => $badgeShortTerm, 'label' => 'Short-Term OK',    'icon' => 'fa-solid fa-calendar-days', 'color' => 'amber',  'strong' => true],
            ['show' => (bool)$heroPropType, 'label' => $heroPropType,  'icon' => 'fa-solid fa-tag',           'color' => 'teal',   'strong' => false],
        ], fn($b) => $b['show'])), 0, 5);
    @endphp

    {{-- Page header row --}}
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div>
            <h2 class="mb-1 fw-bold" style="color:#1e293b;">{{ $pageTitle }}</h2>
            @if($fullAddress)
                <p class="text-muted mb-0"><i class="fa-solid fa-location-dot me-1"></i>{{ $fullAddress }}</p>
            @endif
        </div>
        @if(auth()->check() && auth()->id() == $auction->user_id)
        <div>
            <a href="{{ route('offer.listing.landlord.edit', ['auctionId' => $auction->id]) }}"
               class="btn btn-outline-primary">
                <i class="fa-solid fa-pen-to-square me-1"></i>Edit Listing
            </a>
        </div>
        @endif
    </div>

    <div class="mb-3">
        <a href="{{ route('offer.listing.landlord.searchListing') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>Back to Rental Properties
        </a>
    </div>

    {{-- ===== HERO ===== --}}
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
    <div class="lol-hero mb-4">
        <div class="row g-0" style="min-height:280px;">
            <div class="col-lg-8">
                <div class="lol-hero-carousel-wrap">
                    @if(count($heroPhotoUrls))
                        <img id="lolHeroImg"
                             src="{{ $heroPhotoUrls[$coverPhotoIdx] }}"
                             alt="Property photo" class="lol-hero-photo" style="cursor:pointer;"
                             onerror="this.style.display='none';var p=document.getElementById('lolHeroPlaceholder');if(p)p.style.display='flex';">
                        <div id="lolHeroPlaceholder" class="lol-hero-photo-placeholder" style="display:none;">
                            <i class="fa-solid fa-house"></i>
                        </div>
                        @if(count($heroPhotoUrls) > 1)
                        <button class="lol-hero-arrow lol-hero-arrow-prev" id="lolHeroPrev" aria-label="Previous photo">&#8249;</button>
                        <button class="lol-hero-arrow lol-hero-arrow-next" id="lolHeroNext" aria-label="Next photo">&#8250;</button>
                        <div class="lol-hero-carousel-counter" id="lolHeroCounter">{{ $coverPhotoIdx + 1 }} / {{ count($heroPhotoUrls) }}</div>
                        @endif
                    @else
                        <div class="lol-hero-photo-placeholder"><i class="fa-solid fa-house"></i></div>
                    @endif
                </div>
                <script>var _lolHeroPhotos={!! json_encode($heroPhotoUrls) !!};var _lolHeroStartIdx={{ $coverPhotoIdx }};</script>
            </div>
            <div class="col-lg-4">
                <div class="lol-hero-summary">
                    @if($heroPrice)
                        <div class="lol-hero-price">{{ $heroPrice }}<span style="font-size:1rem;font-weight:500;color:#64748b;margin-left:4px;">/ mo</span></div>
                    @endif
                    @if($fullAddress)
                        <div class="lol-hero-address"><i class="fa-solid fa-location-dot me-1" style="color:#0f766e;"></i>{{ $fullAddress }}</div>
                    @endif
                    <div class="lol-hero-meta">
                        @if($heroBeds)<span class="lol-hero-meta-item"><i class="fa-solid fa-bed"></i>{{ $heroBeds }} Beds</span>@endif
                        @if($heroBaths)<span class="lol-hero-meta-item"><i class="fa-solid fa-bath"></i>{{ $heroBaths }} Baths</span>@endif
                        @if($heroSqft)<span class="lol-hero-meta-item"><i class="fa-solid fa-ruler-combined"></i>{{ number_format((int)preg_replace('/[^0-9]/', '', $heroSqft)) }} Sq Ft</span>@endif
                        @if($heroPropType)<span class="lol-hero-meta-item"><i class="fa-solid fa-tag"></i>{{ $heroPropType }}</span>@endif
                    </div>
                    @if($heroStatus)
                        <div><span class="lol-hero-status"><i class="fa-solid fa-circle-check"></i>{{ $heroStatus }}</span></div>
                    @endif
                    @if($heroListDate || $heroUpdDate)
                        <div class="lol-hero-dates">
                            @if($heroListDate)<span>Listed: {{ $heroListDate }}</span>@endif
                            @if($heroListDate && $heroUpdDate)<span class="mx-1">·</span>@endif
                            @if($heroUpdDate)<span>Updated: {{ $heroUpdDate }}</span>@endif
                        </div>
                    @endif
                    <div class="lol-hero-badges">
                        @foreach($heroBadgesDisplay as $_b)
                            <span class="lol-badge lol-badge-{{ $_b['color'] }}"><i class="{{ $_b['icon'] }}"></i> {{ $_b['label'] }}</span>
                        @endforeach
                    </div>

                    @if($hasBPTimer)
                    <div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
                        <span class="text-muted fw-semibold" style="font-size:.8rem;"><i class="fa-regular fa-clock me-1"></i>Bidding Period:</span>
                        @if($timerRemainingSeconds <= 0)
                            <span class="badge bg-secondary" style="font-size:.8rem;">Expired</span>
                        @else
                            <span class="badge bg-info text-dark lol-bp-timer"
                                  data-seconds="{{ $timerRemainingSeconds }}"
                                  style="font-size:.8rem;font-variant-numeric:tabular-nums;">
                                @php
                                    $_ls = $timerRemainingSeconds;
                                    if ($_ls < 60) { echo $_ls . 's Remaining'; }
                                    else {
                                        $_ld = intdiv($_ls, 86400); $_ls %= 86400;
                                        $_lh = intdiv($_ls, 3600);  $_ls %= 3600;
                                        $_li = intdiv($_ls, 60);
                                        $_lp = [];
                                        if ($_ld) $_lp[] = $_ld . 'd';
                                        if ($_lh) $_lp[] = $_lh . 'h';
                                        if ($_li) $_lp[] = $_li . 'm';
                                        echo implode(' ', $_lp) . ' Remaining';
                                    }
                                @endphp
                            </span>
                        @endif
                    </div>
                    @endif

                    @php
                        $lolStandoutParts = array_values(array_map(
                            fn($b) => $b['label'],
                            array_filter($heroBadgesDisplay, fn($b) => !empty($b['strong']))
                        ));
                    @endphp
                    @if(count($lolStandoutParts) >= 2)
                    <div style="margin-top:10px;padding:10px 14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;">
                        <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#166534;margin-bottom:3px;">Why This Rental Stands Out</div>
                        @php
                            $lSlice = array_slice($lolStandoutParts, 0, -1);
                            $lLast  = end($lolStandoutParts);
                        @endphp
                        <div style="font-size:0.88rem;color:#14532d;">{{ count($lolStandoutParts) > 1 ? implode(', ', $lSlice) . ' and ' . $lLast : $lLast }}.</div>
                    </div>
                    @endif

                    <div class="lol-hero-ctas">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#lolShowingModal">
                            <i class="fa-solid fa-calendar-days me-1"></i>Schedule Showing
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#lolQuestionModal">
                            <i class="fa-solid fa-circle-question me-1"></i>Ask a Question
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="lolShareHeroBtn">
                            <i class="fa-solid fa-share-nodes me-1"></i>Share
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== INTERACTION HUB ===== --}}
    <div class="lol-interaction-hub" id="lol-interaction-hub">
        <div class="lol-interaction-hub-label"><i class="fa-solid fa-bolt me-1"></i>Quick Actions &amp; Listing Info</div>
        <div class="lol-interaction-grid">

            {{-- 1. Schedule Showing (reuses existing lolShowingModal — no new modal created) --}}
            <div class="lol-interaction-card">
                <div class="lol-interaction-card-icon"><i class="fa-solid fa-calendar-days"></i></div>
                <div class="lol-interaction-card-label">Schedule Showing</div>
                <div class="lol-interaction-card-helper">Request an in-person or virtual showing of this property.</div>
                <button type="button" class="lol-interaction-cta lol-interaction-cta-primary"
                        data-bs-toggle="modal" data-bs-target="#lolShowingModal"
                        aria-label="Schedule a showing for this property">
                    <i class="fa-solid fa-calendar-plus"></i>Request Showing
                </button>
            </div>

            {{-- 2. Ask AI --}}
            <div class="lol-interaction-card">
                <div class="lol-interaction-card-icon"><i class="fa-solid fa-robot"></i></div>
                <div class="lol-interaction-card-label">Ask AI</div>
                <div class="lol-interaction-ai-chips">
                    <span class="lol-interaction-ai-chip">What utilities are included?</span>
                    <span class="lol-interaction-ai-chip">Are pets allowed?</span>
                    <span class="lol-interaction-ai-chip">What is the lease term?</span>
                    <span class="lol-interaction-ai-chip">What are the move-in costs?</span>
                    <span class="lol-interaction-ai-chip">Is parking available?</span>
                </div>
                <input type="text" class="form-control form-control-sm"
                       placeholder="Ask a question about this property…"
                       aria-label="AI question input" disabled
                       style="font-size:.73rem;border-radius:6px;background:#f8fafc;cursor:default;">
                <button type="button" class="lol-interaction-cta lol-interaction-cta-outline"
                        data-bs-toggle="modal" data-bs-target="#lolAiModal"
                        aria-label="Ask AI a question about this property">
                    <i class="fa-solid fa-robot"></i>Ask AI
                </button>
            </div>

            {{-- 3. Ask a Question --}}
            <div class="lol-interaction-card">
                <div class="lol-interaction-card-icon"><i class="fa-solid fa-circle-question"></i></div>
                <div class="lol-interaction-card-label">Ask a Question</div>
                <div class="lol-interaction-card-helper">Send a direct question to the listing contact.</div>
                <button type="button" class="lol-interaction-cta lol-interaction-cta-outline"
                        data-bs-toggle="modal" data-bs-target="#lolQuestionModal"
                        aria-label="Ask a question about this listing">
                    <i class="fa-solid fa-paper-plane"></i>Send Question
                </button>
            </div>

            {{-- 4. Share Listing --}}
            <div class="lol-interaction-card">
                <div class="lol-interaction-card-icon"><i class="fa-solid fa-share-nodes"></i></div>
                <div class="lol-interaction-card-label">Share Listing</div>
                <div class="lol-interaction-card-helper">Share this property with friends, family, or your network.</div>
                <div class="lol-interaction-share-row">
                    <button type="button" class="lol-interaction-cta lol-interaction-cta-outline" id="lolHubCopyBtn"
                            aria-label="Copy listing link to clipboard">
                        <i class="fa-solid fa-link"></i>Copy Link
                    </button>
                    <button type="button" class="lol-interaction-cta lol-interaction-cta-outline" id="lolHubNativeShareBtn"
                            style="display:none;" aria-label="Share this listing via your device's share sheet">
                        <i class="fa-solid fa-share-nodes"></i>Share
                    </button>
                </div>
                <div class="lol-interaction-share-row" style="margin-top:.15rem;">
                    <span class="lol-interaction-cta lol-interaction-cta-muted" aria-label="QR Code — coming soon">
                        <i class="fa-solid fa-qrcode"></i>QR Code
                    </span>
                    <span class="lol-interaction-cta lol-interaction-cta-muted" aria-label="Embed widget — coming soon">
                        <i class="fa-solid fa-code"></i>Embed
                    </span>
                </div>
            </div>

            {{-- 5. Activity — hidden until live data is available --}}
            @if(false)
            <div class="lol-interaction-card">
                <div class="lol-interaction-card-icon"><i class="fa-solid fa-chart-simple"></i></div>
                <div class="lol-interaction-card-label">Activity</div>
                <div style="margin-top:.1rem;">
                    <div class="lol-interaction-activity-row">
                        <span>Views</span><span class="lol-interaction-activity-val">Coming Soon</span>
                    </div>
                    <div class="lol-interaction-activity-row">
                        <span>Saves</span><span class="lol-interaction-activity-val">Coming Soon</span>
                    </div>
                    <div class="lol-interaction-activity-row">
                        <span>Questions</span><span class="lol-interaction-activity-val">Coming Soon</span>
                    </div>
                    <div class="lol-interaction-activity-row">
                        <span>Offers/Bids</span><span class="lol-interaction-activity-val">Coming Soon</span>
                    </div>
                </div>
            </div>
            @endif

            {{-- 6. Leasing Information --}}
            <div class="lol-interaction-card">
                <div class="lol-interaction-card-icon"><i class="fa-solid fa-file-contract"></i></div>
                <div class="lol-interaction-card-label">Leasing Information</div>
                @php
                    $_hubRent  = $heroPrice ?: null;
                    $_hubLease = implode(', ', array_slice($leaseLengths ?? [], 0, 2)) ?: null;
                    $_hubPets  = $badgePets ? 'Allowed' : null;
                    $_hub55    = $badge55Plus ? '55+' : null;
                @endphp
                <div style="margin-top:.1rem;">
                    @if($_hubRent)
                    <div class="lol-interaction-activity-row">
                        <span>Rent</span><span class="lol-interaction-activity-val" style="color:#0f766e;font-size:.76rem;">{{ $_hubRent }}/mo</span>
                    </div>
                    @endif
                    @if($_hubLease)
                    <div class="lol-interaction-activity-row">
                        <span>Lease</span><span class="lol-interaction-activity-val" style="color:#475569;font-size:.72rem;">{{ $_hubLease }}</span>
                    </div>
                    @endif
                    @if($_hubPets)
                    <div class="lol-interaction-activity-row">
                        <span>Pets</span><span class="lol-interaction-activity-val" style="color:#475569;">Allowed</span>
                    </div>
                    @endif
                    @if($_hub55)
                    <div class="lol-interaction-activity-row">
                        <span>Community</span><span class="lol-interaction-activity-val" style="color:#475569;font-size:.68rem;">55+</span>
                    </div>
                    @endif
                    @if(!$_hubRent && !$_hubLease && !$_hubPets && !$_hub55)
                    <div class="lol-interaction-card-helper">See listing details for full leasing terms.</div>
                    @endif
                </div>
                <a href="#section-leasing" class="lol-interaction-cta lol-interaction-cta-outline"
                   onclick="event.preventDefault();var t=document.getElementById('section-leasing');if(t){var top=t.getBoundingClientRect().top+window.scrollY-82;window.scrollTo({top:top,behavior:'smooth'});}">
                    <i class="fa-solid fa-file-contract"></i>View Terms
                </a>
            </div>

        </div>{{-- /lol-interaction-grid --}}
    </div>{{-- /lol-interaction-hub --}}

    {{-- ===== TWO-COLUMN LAYOUT ===== --}}
    <div class="row g-4 align-items-start">
    <div class="col-lg-9 lol-main-content-wrap">

    {{-- Pre-compute section-visibility flags for nav tab guards and section @if guards --}}
    @php
        /* Photos */
        $navHasPhotos = count($propertyPhotos) > 0 || $str('video_tour_url') || $str('virtual_tour_url');

        /* Pricing */
        $navHasPricing = $str('desired_rental_amount') || $str('starting_rent') || $str('reserve_rent')
            || $str('lease_now_price') || $str('security_deposit_required') || $str('security_deposit_amount')
            || $str('first_month_rent_required') || $str('last_month_rent_required')
            || $str('total_move_in_funds_required') || $str('min_income_requirement');

        /* Utilities & Fees */
        $navHasUtilities = count($arr('tenant_pays')) > 0 || count($arr('owner_pays')) > 0
            || $str('utilities') || $str('cam_nnn_additional_rent_charges');

        /* Parking & Amenities */
        $navHasParking = $str('garage_needed') || $str('carport_needed') || $str('pool_needed')
            || count($arr('garage_parking_spaces_option')) > 0 || $str('garage_parking_spaces')
            || $str('parking_terms') || $str('commercial_parking_terms')
            || count($subOther($arr('view_preference'), $str('other_preferences'))) > 0
            || count($subOther($arr('non_negotiable_amenities'), $str('other_non_negotiable_amenities'))) > 0
            || count($subOther($arr('appliances'), $str('appliances_other'))) > 0;

        /* Pets */
        $navHasPets = $str('pets') || $str('pet_policy') || $str('number_of_pets')
            || $str('type_of_pets') || $str('breed_of_pets') || $str('weight_of_pets')
            || $str('pet_max_weight_lbs') || $str('pet_deposit_fee_rent')
            || $str('pet_deposit_amount') || $str('pet_monthly_fee')
            || count($arr('pet_species_allowed')) > 0;

        /* Compensation */
        $navHasCompensation = (bool)$str('tenant_broker_commission_structure');

        /* Contact */
        $navHasContact = $str('first_name') || $str('last_name') || $str('email')
            || $str('phone_number') || $str('agent_brokerage') || $str('agent_license_number');

        /* Additional Details */
        $navHasAddl = $str('additional_details') || $str('preferance_details');
    @endphp

    {{-- Smooth-scroll nav tabs (each tab is only rendered when its section has content) --}}
    <div class="lol-nav-tabs-wrap">
        <ul class="lol-nav-tabs" id="lolNavTabs">
            <li><a href="#section-overview">Overview</a></li>
            @if($navHasPhotos)
            <li><a href="#section-photos">Photos</a></li>
            @endif
            <li><a href="#section-details">Property</a></li>
            <li><a href="#section-leasing">Leasing Terms</a></li>
            @if($navHasPricing)
            <li><a href="#section-pricing">Pricing</a></li>
            @endif
            @if($navHasUtilities)
            <li><a href="#section-utilities">Utilities &amp; Fees</a></li>
            @endif
            @if($navHasParking)
            <li><a href="#section-parking">Parking &amp; Amenities</a></li>
            @endif
            <li><a href="#section-tenant">Tenant Criteria</a></li>
            @if($navHasPets)
            <li><a href="#section-pets">Pets &amp; Occupancy</a></li>
            @endif
            @if($navHasCompensation)
            <li><a href="#section-tenant-broker">Compensation</a></li>
            @endif
            @if($navHasContact)
            <li><a href="#section-contact">Contact</a></li>
            @endif
            @if($navHasAddl)
            <li><a href="#section-additional">Additional Details</a></li>
            @endif
        </ul>
    </div>

    {{-- ================================================================
         PHOTOS & TOURS
         ============================================================== --}}
    @if(count($propertyPhotos) || $str('video_tour_url') || $str('virtual_tour_url'))
    <div class="card section-card" id="section-photos">
        <div class="card-header"><i class="fa-solid fa-images me-2"></i>Photos &amp; Tours</div>
        <div class="card-body">
            @php
                $videoUrl = $str('video_tour_url');
                $virtualUrl = $str('virtual_tour_url');
                $videoEmbedUrl = null;
                $virtualEmbedUrl = null;
                foreach ([['url' => $videoUrl, 'var' => &$videoEmbedUrl], ['url' => $virtualUrl, 'var' => &$virtualEmbedUrl]] as &$_tour) {
                    if (!$_tour['url']) continue;
                    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_\-]{11})/', $_tour['url'], $_m)) {
                        $_tour['var'] = 'https://www.youtube.com/embed/' . $_m[1];
                    } elseif (preg_match('/vimeo\.com\/(\d+)/', $_tour['url'], $_m)) {
                        $_tour['var'] = 'https://player.vimeo.com/video/' . $_m[1];
                    }
                }
                unset($_tour);
            @endphp
            @if($videoUrl)
                @if($videoEmbedUrl)
                    <div class="ratio ratio-16x9 mb-3" style="max-width:560px;">
                        <iframe src="{{ $videoEmbedUrl }}" title="Video Tour" allowfullscreen allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                    </div>
                @else
                    <p class="mb-3"><span style="font-weight:600;color:#64748b;font-size:.875rem;">Video Tour:</span>
                        <a href="{{ $videoUrl }}" target="_blank" rel="noopener" class="ms-1">{{ $videoUrl }}</a></p>
                @endif
            @endif
            @if($virtualUrl)
                @if($virtualEmbedUrl)
                    <div class="ratio ratio-16x9 mb-3" style="max-width:560px;">
                        <iframe src="{{ $virtualEmbedUrl }}" title="Virtual Tour" allowfullscreen allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                    </div>
                @else
                    <p class="mb-3"><span style="font-weight:600;color:#64748b;font-size:.875rem;">3D / Virtual Tour:</span>
                        <a href="{{ $virtualUrl }}" target="_blank" rel="noopener" class="ms-1">{{ $virtualUrl }}</a></p>
                @endif
            @endif
            @if(count($propertyPhotos))
            @php $_galIdx = -1; @endphp
            <div class="d-flex flex-wrap gap-2 mt-2">
                @foreach($propertyPhotos as $_photo)
                @php
                    $_fn    = is_array($_photo) ? ($_photo['filename'] ?? '') : $_photo;
                    $_cover = is_array($_photo) && !empty($_photo['is_cover']);
                    if ($_fn) $_galIdx++;
                @endphp
                @if($_fn)
                <div class="text-center">
                    <a href="#" data-bs-toggle="modal" data-bs-target="#lolPhotoModal"
                       data-src="{{ asset('storage/auction/images/' . $_fn) }}"
                       data-index="{{ $_galIdx }}" style="display:block;">
                        <img src="{{ asset('storage/auction/images/' . $_fn) }}"
                             alt="Photo {{ $_galIdx + 1 }}" class="photo-thumb"
                             onerror="this.style.display='none'">
                    </a>
                    @if($_cover)<div><span class="cover-badge">Cover</span></div>@endif
                </div>
                @endif
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- Photo lightbox modal --}}
    <div class="modal fade" id="lolPhotoModal" tabindex="-1" aria-label="Property photo" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark border-0">
                <div class="modal-header border-0 pb-0">
                    <span class="text-white small" id="lolPhotoModalCounter"></span>
                    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-2">
                    <img id="lolPhotoModalImg" src="" alt="Property photo" style="max-width:100%;max-height:70vh;object-fit:contain;border-radius:6px;">
                </div>
                <div class="modal-footer border-0 justify-content-center gap-3 pt-0">
                    <button type="button" class="btn btn-outline-light btn-sm px-4" id="lolPhotoModalPrev">&#8249; Prev</button>
                    <button type="button" class="btn btn-outline-light btn-sm px-4" id="lolPhotoModalNext">Next &#8250;</button>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function(){
        var gallery=Array.from(document.querySelectorAll('[data-bs-target="#lolPhotoModal"]')).map(function(el){return el.getAttribute('data-src');});
        var ci=0;
        function show(idx){if(!gallery.length)return;if(idx<0)idx=gallery.length-1;if(idx>=gallery.length)idx=0;ci=idx;var img=document.getElementById('lolPhotoModalImg');var ctr=document.getElementById('lolPhotoModalCounter');if(img)img.src=gallery[ci];if(ctr)ctr.textContent=(ci+1)+' / '+gallery.length;}
        document.addEventListener('click',function(e){var t=e.target.closest('[data-bs-target="#lolPhotoModal"]');if(t){e.preventDefault();show(parseInt(t.getAttribute('data-index')||'0',10));}});
        var el=document.getElementById('lolPhotoModal');if(el)el.addEventListener('show.bs.modal',function(){show(ci);});
        var p=document.getElementById('lolPhotoModalPrev');var n=document.getElementById('lolPhotoModalNext');
        if(p)p.addEventListener('click',function(){show(ci-1);});if(n)n.addEventListener('click',function(){show(ci+1);});
        window._lolShowPhoto=show;
    })();
    </script>
    @endif

    {{-- ================================================================
         LISTING OVERVIEW
         ============================================================== --}}
    <div class="card section-card" id="section-overview">
        <div class="card-header"><i class="fa-solid fa-list-check me-2"></i>Listing Overview</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Listing Title', $str('listing_title') ?: $auction->title) !!}
                    {!! $row('Auction / Listing Type', $str('auction_type')) !!}
                    {!! $row('Listing Status', $str('listing_status')) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Listing Date', $fmtDate($str('listing_date'))) !!}
                    {!! $row('Expiration Date', $fmtDate($str('expiration_date'))) !!}
                    {!! $row('Bidding Period / Auction Time', $str('auction_time')) !!}
                    {!! $row('Available Date', $fmtDate($str('available_date') ?: $str('lease_available_date'))) !!}
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
                        <span class="badge bg-info text-dark lol-bp-timer"
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
        </div>
    </div>

    {{-- ================================================================
         PROPERTY DETAILS
         ============================================================== --}}
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
                </div>
                <div class="col-md-6">
                    {!! $row('Bedrooms', $orOther($str('bedrooms'), $str('other_bedrooms'))) !!}
                    {!! $row('Bathrooms', $orOther($str('bathrooms'), $str('other_bathrooms'))) !!}
                    {!! $row('Heated Sq Ft', $str('minimum_heated_square')) !!}
                    {!! $row('Leaseable Sq Ft', $str('minimum_leaseable')) !!}
                    {!! $row('Min Acreage', $str('min_acreage')) !!}
                    {!! $row('Total Acreage', $str('total_acreage')) !!}
                    {!! $row('Number of Units', $orOther($str('number_of_unit'), $str('number_of_unit_other'))) !!}
                    {!! $row('Property Condition', $orOther($str('condition_prop'), $str('other_property_condition'))) !!}
                </div>
            </div>

            @php $pItems = $subOther($arr('property_items'), $str('other_property_items')); @endphp
            @if(count($pItems))
            <hr>
            <div class="row"><div class="col-md-12">{!! $listRow('Property Style / Items', $pItems) !!}</div></div>
            @endif

            {{-- Leasing / rental space --}}
            @php
                $leasingSpaceFields = array_filter([
                    ['Leasing Space / Property', $orOther($str('leasing_space'), $str('leasing_spaces'))],
                    ['Occupant Status', $str('occupant_status')],
                    ['Occupied Until', $str('occupant_tenant')],
                    ['Restrictions', $str('restrictions')],
                    ['Maintenance By', $str('maintenance_by')],
                    ['Maintenance Response Time', $str('maintenance_response_time')],
                    ['Storage Space Included',
                        $str('included_storage_space_res_both')
                        ?: $str('included_storage_space_res_single')
                        ?: $str('included_storage_space_com_entire')],
                    ['Storage Space Details',
                        $str('storage_space_res_both')
                        ?: $str('storage_space_res_single')
                        ?: $str('storage_space_com_entire')],
                    ['Guests Allowed', $str('guests_allowed')],
                    ['Common Areas Access', $str('common_areas_access')],
                    ['Common Areas Cleaning', $str('common_areas_cleaning')],
                    ['Bathroom Facilities', $str('bathroom_facilities')],
                    ['Room Size', $str('room_size')],
                    ['Shared Amenities', $str('shared_amenities')],
                    ['Building Hours', $str('building_hours')],
                    ['24/7 Access', $str('access_24_7')],
                    ['Zoning Allows', $str('zoning_allows')],
                ], fn($f) => !empty($f[1]));
            @endphp
            @if(count($leasingSpaceFields))
            <hr>
            <h6 class="fw-semibold mb-3" style="font-size:.9rem;letter-spacing:0;">Leasing / Rental Space</h6>
            <div class="row">
                @foreach($leasingSpaceFields as $f)
                <div class="col-md-6">{!! $row($f[0], $f[1]) !!}</div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- ================================================================
         LEASING TERMS
         ============================================================== --}}
    <div class="card section-card" id="section-leasing">
        <div class="card-header"><i class="fa-solid fa-file-contract me-2"></i>Leasing Terms</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Available Date', $fmtDate($str('available_date') ?: $str('lease_available_date'))) !!}
                    {!! $row('Lease Start Date', $fmtDate($str('lease_date'))) !!}
                    {!! $row('Lease Duration', $str('lease_by')) !!}
                    @php
                        $leaseLengthItems = $subOther(
                            $arr('desired_lease_length') ?: $arr('lease_for'),
                            $str('other_lease_for')
                        );
                    @endphp
                    {!! $listRow('Desired Lease Length(s)', $leaseLengthItems) !!}
                    {!! $row('Age-Restricted Community (55+)', $yesNo($str('leasing_55_plus'))) !!}
                    {!! $row('Renewal Option Offered', $yesNo($str('renewal_option_offered'))) !!}
                    {!! $row('Renewal Option Details', $str('renewal_option_details')) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Subletting Policy', $str('subletting_policy')) !!}
                    {!! $row('Smoking Policy', $str('smoking_policy')) !!}
                    {!! $row('Landlord Maintenance Responsibility', $str('ll_maintenance_responsibility')) !!}
                    {!! $row('Number of Occupants Allowed', $str('number_of_occupants_allowed') ?: $str('number_occupant')) !!}
                    {!! $row('Landlord Approval Conditions', $str('landlord_approval_conditions')) !!}
                    {!! $row('Additional Landlord Lease Terms', $str('additional_landlord_lease_terms')) !!}
                </div>
            </div>

            {{-- Residential: what's included in rent --}}
            @php $rentIncludes = $arr('rent_includes'); @endphp
            @if(count($rentIncludes))
            <hr>
            <div class="row"><div class="col-md-12">{!! $listRow('Rent Includes', $rentIncludes) !!}</div></div>
            @endif

            {{-- Commercial lease terms --}}
            @php
                $commLeaseType = $orOther($str('commercial_lease_type'), $str('commercial_lease_type_other'));
                $termsOfLease  = $arr('terms_of_lease');
                $hasCommFields = $commLeaseType || $str('cam_nnn_additional_rent_charges') || $str('rent_escalation_terms')
                    || $str('tenant_improvement_buildout_terms') || $str('permitted_use_restrictions')
                    || $str('signage_rights') || $str('personal_guarantee_requirement')
                    || $str('commercial_approval_conditions') || count($termsOfLease);
            @endphp
            @if($hasCommFields)
            <hr>
            <h6 class="fw-semibold mb-3" style="font-size:.9rem;letter-spacing:0;">Commercial Lease Details</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Commercial Lease Type', $commLeaseType) !!}
                    {!! $row('Rent Escalation Terms', $str('rent_escalation_terms')) !!}
                    {!! $row('Tenant Improvement / Build-Out Terms', $str('tenant_improvement_buildout_terms')) !!}
                    {!! $row('Permitted Use / Restrictions', $str('permitted_use_restrictions')) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Signage Rights', $str('signage_rights')) !!}
                    {!! $row('Personal Guarantee Requirement', $str('personal_guarantee_requirement')) !!}
                    {!! $row('Commercial Approval Conditions', $str('commercial_approval_conditions')) !!}
                </div>
            </div>
            @if(count($termsOfLease))
            <div class="row"><div class="col-md-12">{!! $listRow('Terms of Lease', $termsOfLease) !!}</div></div>
            @endif
            @endif
        </div>
    </div>

    {{-- ================================================================
         RENTAL PRICING & DEPOSITS
         ============================================================== --}}
    @php
        $pricingFields = array_filter([
            ['Desired Rental Amount', $fmtMoney($str('desired_rental_amount'))],
            ['Lease Amount Frequency', $str('lease_amount_frequency')],
            ['Starting Rent', $fmtMoney($str('starting_rent'))],
            ['Reserve Rent', $fmtMoney($str('reserve_rent'))],
            ['Lease Now Price', $fmtMoney($str('lease_now_price'))],
            ['Security Deposit Required', $yesNo($str('security_deposit_required'))],
            ['Security Deposit Amount', $fmtMoney($str('security_deposit_amount'))],
            ['First Month Rent Required', $yesNo($str('first_month_rent_required'))],
            ['Last Month Rent Required', $yesNo($str('last_month_rent_required'))],
            ['Total Move-In Funds Required', $fmtMoney($str('total_move_in_funds_required'))],
            ['Minimum Income Requirement', $str('min_income_requirement') ? '$' . number_format((float)preg_replace('/[^0-9.]/', '', $str('min_income_requirement')), 0) . '/mo' : null],
        ], fn($f) => !empty($f[1]));
    @endphp
    @if(count($pricingFields))
    <div class="card section-card" id="section-pricing">
        <div class="card-header"><i class="fa-solid fa-dollar-sign me-2"></i>Rental Pricing &amp; Deposits</div>
        <div class="card-body">
            <div class="row">
                @foreach($pricingFields as $f)
                <div class="col-md-6">{!! $row($f[0], $f[1]) !!}</div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- ================================================================
         UTILITIES & FEES
         ============================================================== --}}
    @php
        $tenantPays = $arr('tenant_pays');
        $ownerPays  = $arr('owner_pays');
        $utilFields = array_filter([
            ['Utilities Included in Rent', $str('utilities')],
            ['CAM / NNN Additional Rent Charges', $str('cam_nnn_additional_rent_charges')],
        ], fn($f) => !empty($f[1]));
        $hasUtilities = count($tenantPays) || count($ownerPays) || count($utilFields);
    @endphp
    @if($hasUtilities)
    <div class="card section-card" id="section-utilities">
        <div class="card-header"><i class="fa-solid fa-bolt me-2"></i>Utilities &amp; Fees</div>
        <div class="card-body">
            @if(count($utilFields))
            <div class="row">
                @foreach($utilFields as $f)
                <div class="col-md-6">{!! $row($f[0], $f[1]) !!}</div>
                @endforeach
            </div>
            @endif
            @if(count($tenantPays) || count($ownerPays))
            @if(count($utilFields))<hr>@endif
            <div class="row">
                @if(count($tenantPays))<div class="col-md-6">{!! $listRow('Tenant Pays', $tenantPays) !!}</div>@endif
                @if(count($ownerPays))<div class="col-md-6">{!! $listRow('Owner / Landlord Pays', $ownerPays) !!}</div>@endif
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- ================================================================
         PARKING & AMENITIES
         ============================================================== --}}
    @php
        $garageNeeded    = $orOther($str('garage_needed'),  $str('other_garage_needed'));
        $carportNeeded   = $orOther($str('carport_needed'), $str('other_carport_needed'));
        $poolNeeded      = $yesNo($str('pool_needed'));
        $poolTypes       = $arr('pool_type');
        $poolTypeList    = is_string($poolTypes) ? [$poolTypes] : array_filter(array_keys(array_filter($poolTypes)));
        $garageParkOpts  = $arr('garage_parking_spaces_option');
        $parkingSpaces   = $str('garage_parking_spaces');
        $parkingTerms    = $str('parking_terms') ?: $str('commercial_parking_terms');
        $viewPref        = $subOther($arr('view_preference'), $str('other_preferences'));
        $nonNegAmenities = $subOther($arr('non_negotiable_amenities'), $str('other_non_negotiable_amenities'));
        $appliances      = $subOther($arr('appliances'), $str('appliances_other'));
        $parkingFields = array_filter([
            ['Garage', $garageNeeded],
            ['Carport', $carportNeeded],
            ['Parking Spaces', $parkingSpaces],
            ['Parking Features', implode(', ', array_filter(is_array($garageParkOpts) ? $garageParkOpts : []))],
            ['Parking Terms', $parkingTerms],
            ['Pool', $poolNeeded],
            ['Pool Type', count($poolTypeList) ? implode(', ', $poolTypeList) : null],
        ], fn($f) => !empty($f[1]));
        $hasParking = count($parkingFields) || count($viewPref) || count($nonNegAmenities) || count($appliances);
    @endphp
    @if($hasParking)
    <div class="card section-card" id="section-parking">
        <div class="card-header"><i class="fa-solid fa-car me-2"></i>Parking &amp; Amenities</div>
        <div class="card-body">
            @if(count($parkingFields))
            <div class="row">
                @foreach($parkingFields as $f)
                <div class="col-md-6">{!! $row($f[0], $f[1]) !!}</div>
                @endforeach
            </div>
            @endif
            @if(count($appliances))
            @if(count($parkingFields))<hr>@endif
            <div class="row"><div class="col-md-12">{!! $listRow('Appliances Included', $appliances) !!}</div></div>
            @endif
            @if(count($viewPref))
            <div class="row"><div class="col-md-12">{!! $listRow('View / Location Features', $viewPref) !!}</div></div>
            @endif
            @if(count($nonNegAmenities))
            <div class="row"><div class="col-md-12">{!! $listRow('Non-Negotiable Amenities', $nonNegAmenities) !!}</div></div>
            @endif
        </div>
    </div>
    @endif

    {{-- ================================================================
         DESIRED TENANT CRITERIA
         ============================================================== --}}
    <div class="card section-card" id="section-tenant">
        <div class="card-header"><i class="fa-solid fa-user-check me-2"></i>Desired Tenant Criteria</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Tenant Type Required', $str('tenant_require')) !!}
                    {!! $row('Occupant Type(s)', $str('occupant_types')) !!}
                    {!! $row('Monthly Income Requirement', $str('monthly_income')) !!}
                    {!! $row('Minimum Income Requirement', $str('min_income_requirement')) !!}
                    @php $creditRatings = $arr('credit_scroe_rating'); @endphp
                    {!! $listRow('Credit Score Rating Required', $creditRatings) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Prior Eviction', $yesNo($str('prior_eviction'))) !!}
                    @if($str('prior_eviction') && strtolower($str('prior_eviction')) !== 'no')
                        {!! $row('Eviction Explanation / Circumstances', $str('eviction_explanation')) !!}
                    @endif
                    {!! $row('Prior Felony', $yesNo($str('prior_felony'))) !!}
                    @if($str('prior_felony') && strtolower($str('prior_felony')) !== 'no')
                        {!! $row('Felony Explanation / Circumstances', $str('prior_felony_explanation')) !!}
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================================
         PETS & OCCUPANCY
         ============================================================== --}}
    @php
        $petFields = array_filter([
            ['Pets Allowed', $yesNo($str('pets') ?: $str('pet_policy'))],
            ['Number of Pets Allowed', $str('number_of_pets')],
            ['Type of Pets', $str('type_of_pets')],
            ['Breed of Pets', $str('breed_of_pets')],
            ['Max Pet Weight (lbs)', $str('weight_of_pets') ?: $str('pet_max_weight_lbs')],
            ['Pet Deposit / Fee on Rent', $str('pet_deposit_fee_rent')],
            ['Pet Deposit Amount', $fmtMoney($str('pet_deposit_amount'))],
            ['Pet Monthly Fee', $fmtMoney($str('pet_monthly_fee'))],
        ], fn($f) => !empty($f[1]));
        $petSpecies = $arr('pet_species_allowed');
        $hasPets = count($petFields) || count($petSpecies);
    @endphp
    @if($hasPets)
    <div class="card section-card" id="section-pets">
        <div class="card-header"><i class="fa-solid fa-paw me-2"></i>Pets &amp; Occupancy</div>
        <div class="card-body">
            <div class="row">
                @foreach($petFields as $f)
                <div class="col-md-6">{!! $row($f[0], $f[1]) !!}</div>
                @endforeach
            </div>
            @if(count($petSpecies))
            <div class="row"><div class="col-md-12">{!! $listRow('Pet Species Allowed', $petSpecies) !!}</div></div>
            @endif
        </div>
    </div>
    @endif

    {{-- ================================================================
         TENANT'S BROKER COMPENSATION
         ============================================================== --}}
    @if($navHasCompensation)
    <div class="card section-card" id="section-tenant-broker">
        <div class="card-header"><i class="fa-solid fa-handshake me-2"></i>Tenant's Broker Compensation</div>
        <div class="card-body">
            @php
                $_tbcs  = $str('tenant_broker_commission_structure');
                $_tbfs  = $str('tenant_broker_fee_structure');
            @endphp
            <div class="row">
                <div class="col-md-6">
                    {!! $row("Tenant's Broker Commission Structure", $_tbcs) !!}
                    @if($_tbfs)
                        {!! $row("Tenant's Broker Commission Fee Type", $_tbfs) !!}
                        @if($_tbfs === 'Percentage of the Rent Due Each Rental Period')
                            {!! $row("Tenant's Broker Fee", $fmtPercent($str('tenant_broker_percentage'))) !!}
                        @elseif($_tbfs === 'Percentage of the Gross Lease Value')
                            {!! $row("Tenant's Broker Fee", $fmtPercent($str('tenant_broker_gross_lease'))) !!}
                        @elseif($_tbfs === "Percentage of the First Month's Rent")
                            {!! $row("Tenant's Broker Fee", $fmtPercent($str('tenant_broker_first_month_rent'))) !!}
                        @elseif($_tbfs === 'Percentage of the Net Aggregate Rent')
                            {!! $row("Tenant's Broker Fee", $fmtPercent($str('tenant_broker_percentage'))) !!}
                        @elseif($_tbfs === 'Percentage of the Gross Rent')
                            {!! $row("Tenant's Broker Fee", $fmtPercent($str('tenant_broker_gross_lease'))) !!}
                        @elseif($_tbfs === 'Flat fee')
                            {!! $row("Tenant's Broker Fee", $fmtMoney($str('tenant_broker_flat_fee'))) !!}
                        @elseif($_tbfs === 'Other')
                            {!! $row("Tenant's Broker Fee", $str('tenant_broker_other')) !!}
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ================================================================
         CONTACT / LANDLORD INFORMATION
         ============================================================== --}}
    @php
        $hasContact = $str('first_name') || $str('last_name') || $str('email')
            || $str('phone_number') || $str('agent_brokerage') || $str('agent_license_number');
    @endphp
    @if($hasContact)
    <div class="card section-card" id="section-contact">
        <div class="card-header"><i class="fa-solid fa-id-card me-2"></i>Contact / Landlord Information</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Name', trim($str('first_name') . ' ' . $str('last_name'))) !!}
                    {!! $row('Email', $str('email')) !!}
                    @php
                        $phone = $str('phone_number');
                        $phoneDigits = preg_replace('/\D/', '', $phone);
                        if (strlen($phoneDigits) === 10) {
                            $phone = '(' . substr($phoneDigits, 0, 3) . ') ' . substr($phoneDigits, 3, 3) . '-' . substr($phoneDigits, 6);
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
            <div class="lol-contact-cta-row">
                @if($str('email'))
                    <a href="mailto:{{ $str('email') }}" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-envelope me-1"></i>Email Landlord
                    </a>
                @endif
                <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#lolShowingModal">
                    <i class="fa-solid fa-calendar-days me-1"></i>Schedule Showing
                </button>
                <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#lolQuestionModal">
                    <i class="fa-solid fa-circle-question me-1"></i>Ask a Question
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ================================================================
         ADDITIONAL DETAILS
         ============================================================== --}}
    @php
        $hasAddlDetails = $str('additional_details') || $str('preferance_details');
    @endphp
    @if($hasAddlDetails)
    <div class="card section-card" id="section-additional">
        <div class="card-header"><i class="fa-solid fa-circle-info me-2"></i>Additional Details</div>
        <div class="card-body">
            @if($str('additional_details'))
                <p class="mb-2" style="font-size:.925rem;">{!! nl2br(e($str('additional_details'))) !!}</p>
            @endif
            @if($str('preferance_details'))
                <p class="mb-0" style="font-size:.925rem;">{!! nl2br(e($str('preferance_details'))) !!}</p>
            @endif
        </div>
    </div>
    @endif

    {{-- Owner edit button (bottom) --}}
    @if(auth()->check() && auth()->id() == $auction->user_id)
    <div class="text-end mt-2 mb-4">
        <a href="{{ route('offer.listing.landlord.edit', ['auctionId' => $auction->id]) }}"
           class="btn btn-primary">
            <i class="fa-solid fa-pen-to-square me-1"></i>Edit Listing
        </a>
    </div>
    @endif

    </div>{{-- /col-lg-9 --}}

    {{-- ===== STICKY DESKTOP SIDEBAR ===== --}}
    <div class="col-lg-3 d-none d-lg-block">
        <div class="lol-sticky-card">
            <div class="lol-sticky-title">Quick Actions</div>
            <button class="lol-action-btn lol-action-primary" data-bs-toggle="modal" data-bs-target="#lolQuestionModal">
                <i class="fa-solid fa-circle-question"></i>Ask a Question
            </button>
            <button class="lol-action-btn lol-action-outline" data-bs-toggle="modal" data-bs-target="#lolShowingModal">
                <i class="fa-solid fa-calendar-days"></i>Schedule Showing
            </button>
            <button class="lol-action-btn lol-action-outline" type="button" disabled style="cursor:default;opacity:.6;">
                <i class="fa-regular fa-bookmark"></i>Save Listing
            </button>
            <button class="lol-action-btn lol-action-outline" id="lolShareSidebarBtn" type="button">
                <i class="fa-solid fa-share-nodes"></i>Share Listing
            </button>
            <a href="{{ route('offer.listing.landlord.searchListing') }}" class="lol-action-btn lol-action-outline">
                <i class="fa-solid fa-arrow-left"></i>Back to Search
            </a>
            @if($heroPrice)
            <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid #f1f5f9;text-align:center;">
                <div style="font-size:.72rem;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;">Monthly Rent</div>
                <div style="font-size:1.4rem;font-weight:800;color:#1e293b;letter-spacing:-.02em;">{{ $heroPrice }}</div>
                <div style="font-size:.72rem;color:#94a3b8;">per month</div>
            </div>
            @endif

            @if($heroBeds || $heroBaths || $heroSqft || $heroPropType)
            <div style="margin-top:.75rem;padding-top:.75rem;border-top:1px solid #f1f5f9;">
                @if($heroBeds)<div class="d-flex justify-content-between mb-1"><span style="font-size:.82rem;color:#64748b;">Bedrooms</span><span style="font-size:.82rem;font-weight:700;">{{ $heroBeds }}</span></div>@endif
                @if($heroBaths)<div class="d-flex justify-content-between mb-1"><span style="font-size:.82rem;color:#64748b;">Bathrooms</span><span style="font-size:.82rem;font-weight:700;">{{ $heroBaths }}</span></div>@endif
                @if($heroSqft)<div class="d-flex justify-content-between mb-1"><span style="font-size:.82rem;color:#64748b;">Sq Ft</span><span style="font-size:.82rem;font-weight:700;">{{ number_format((int)preg_replace('/[^0-9]/','',$heroSqft)) }}</span></div>@endif
                @if($heroPropType)<div class="d-flex justify-content-between mb-1"><span style="font-size:.82rem;color:#64748b;">Type</span><span style="font-size:.82rem;font-weight:700;text-align:right;max-width:55%;">{{ $heroPropType }}</span></div>@endif
            </div>
            @endif

            @if($hasBPTimer)
            @php
                $_lolSidebarEndDate = null;
                $_lolExpDateStr = $str('expiration_date');
                if ($_lolExpDateStr) {
                    $_lolSidebarEndDate = $fmtDate($_lolExpDateStr);
                } elseif (!empty($_timerEnd) && $_timerEnd instanceof \Carbon\Carbon) {
                    $_lolSidebarEndDate = $_timerEnd->format('M j, Y');
                }
            @endphp
            @if($_lolSidebarEndDate)
            <div style="margin-top:.75rem;padding-top:.75rem;border-top:1px solid #f1f5f9;">
                <div class="d-flex justify-content-between" style="font-size:.78rem;color:#64748b;">
                    <span><i class="fa-regular fa-clock me-1"></i>Bidding Ends</span>
                    <span style="font-weight:700;color:#475569;">{{ $_lolSidebarEndDate }}</span>
                </div>
            </div>
            @endif
            @endif

            {{-- Activity section hidden until live data is available --}}
            @if(false)
            <div style="margin-top:.75rem;padding-top:.75rem;border-top:1px solid #f1f5f9;">
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
        </div>
    </div>

    </div>{{-- /row --}}

    {{-- ===== ASK A QUESTION MODAL ===== --}}
    <div class="modal fade" id="lolQuestionModal" tabindex="-1" aria-labelledby="lolQuestionModalLabel" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content" style="border-radius:.85rem;overflow:hidden;border:none;">
                <div class="modal-header lol-modal-header">
                    <h5 class="modal-title fw-bold" id="lolQuestionModalLabel"><i class="fa-solid fa-circle-question me-2"></i>Ask a Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1);"></button>
                </div>
                <form method="POST" action="{{ route('offer.listing.landlord.question', ['auction' => $auction->id]) }}">
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
                            <input type="text" class="form-control @error('name', 'lolQuestionInquiry') is-invalid @enderror" name="name" placeholder="Jane Smith" value="{{ old('name') }}" required>
                            @error('name', 'lolQuestionInquiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email', 'lolQuestionInquiry') is-invalid @enderror" name="email" placeholder="jane@example.com" value="{{ old('email') }}" required>
                            @error('email', 'lolQuestionInquiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Phone Number</label>
                            <input type="tel" class="form-control" name="phone" placeholder="(555) 000-0000" value="{{ old('phone') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Your Question <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('question', 'lolQuestionInquiry') is-invalid @enderror" name="question" rows="4" placeholder="What would you like to know about this rental property?" required>{{ old('question') }}</textarea>
                            @error('question', 'lolQuestionInquiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
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

    {{-- ===== SCHEDULE A SHOWING MODAL ===== --}}
    <div class="modal fade" id="lolShowingModal" tabindex="-1" aria-labelledby="lolShowingModalLabel" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content" style="border-radius:.85rem;overflow:hidden;border:none;">
                <div class="modal-header lol-modal-header">
                    <h5 class="modal-title fw-bold" id="lolShowingModalLabel"><i class="fa-solid fa-calendar-days me-2"></i>Schedule a Showing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1);"></button>
                </div>
                <form method="POST" action="{{ route('offer.listing.landlord.showing', ['auction' => $auction->id]) }}">
                @csrf
                <input type="text" name="website" value="" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;" tabindex="-1" autocomplete="off" aria-hidden="true">
                <div class="modal-body p-4">
                    @if(session('success') && str_contains((string)session('success'), 'showing'))
                        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Your Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name', 'lolShowingInquiry') is-invalid @enderror" name="name" placeholder="Jane Smith" value="{{ old('name') }}" required>
                            @error('name', 'lolShowingInquiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email', 'lolShowingInquiry') is-invalid @enderror" name="email" placeholder="jane@example.com" value="{{ old('email') }}" required>
                            @error('email', 'lolShowingInquiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-calendar-check me-1"></i>Request Showing
                    </button>
                </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal: Ask AI About This Property --}}
    <div class="modal fade" id="lolAiModal" tabindex="-1" aria-labelledby="lolAiModalLabel" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content" style="border-radius:.85rem;overflow:hidden;border:none;">
                <div class="modal-header lol-modal-header">
                    <h5 class="modal-title fw-bold" id="lolAiModalLabel"><i class="fa-solid fa-robot me-2"></i>Ask AI About This Property</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1);"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted mb-3" style="font-size:.875rem;">Get instant AI-powered answers about this rental listing. Try asking:</p>
                    <div id="lolAiExamples" class="mb-3 p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0;min-height:60px;">
                        <span class="text-muted fst-italic" style="font-size:.875rem;" id="lolAiExampleText"></span>
                    </div>
                    <label class="form-label fw-semibold" style="font-size:.85rem;">Your Question</label>
                    <textarea class="form-control" rows="4" id="lolAiTextarea"
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
<div class="lol-mobile-bar d-lg-none">
    <button type="button" class="lol-mobile-bar-btn lol-mobile-primary" data-bs-toggle="modal" data-bs-target="#lolQuestionModal">
        <i class="fa-solid fa-circle-question"></i><span>Ask</span>
    </button>
    <button type="button" class="lol-mobile-bar-btn" data-bs-toggle="modal" data-bs-target="#lolShowingModal">
        <i class="fa-solid fa-calendar-days"></i><span>Showing</span>
    </button>
    @if(auth()->check() && auth()->id() == $auction->user_id)
    <a href="{{ route('offer.listing.landlord.edit', ['auctionId' => $auction->id]) }}" class="lol-mobile-bar-btn">
        <i class="fa-solid fa-pen-to-square"></i><span>Edit</span>
    </a>
    @endif
    <button type="button" class="lol-mobile-bar-btn" id="lolMobileShareBtn">
        <i class="fa-solid fa-share-nodes"></i><span>Share</span>
    </button>
    <a href="{{ route('offer.listing.landlord.searchListing') }}" class="lol-mobile-bar-btn">
        <i class="fa-solid fa-arrow-left"></i><span>Search</span>
    </a>
</div>

@push('scripts')
<script>
(function () {
    /* ── Bidding period countdown timer ── */
    function lolBpFormat(s) {
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
    document.querySelectorAll('.lol-bp-timer[data-seconds]').forEach(function (el) {
        var secs = parseInt(el.getAttribute('data-seconds'), 10) || 0;
        el.textContent = lolBpFormat(secs);
        if (secs <= 0) { el.classList.replace('bg-info', 'bg-secondary'); return; }
        var iv = setInterval(function () {
            secs--;
            el.textContent = lolBpFormat(secs);
            if (secs <= 0) {
                clearInterval(iv);
                el.classList.remove('bg-info', 'text-dark');
                el.classList.add('bg-secondary');
            }
        }, 1000);
    });

    /* ── Hero carousel ── */
    var photos  = (typeof _lolHeroPhotos !== 'undefined') ? _lolHeroPhotos : [];
    var heroIdx = (typeof _lolHeroStartIdx !== 'undefined') ? _lolHeroStartIdx : 0;
    var heroImg     = document.getElementById('lolHeroImg');
    var heroPrev    = document.getElementById('lolHeroPrev');
    var heroNext    = document.getElementById('lolHeroNext');
    var heroCounter = document.getElementById('lolHeroCounter');

    function updateHero(idx) {
        if (!photos.length) return;
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
            if (typeof window._lolShowPhoto === 'function') window._lolShowPhoto(heroIdx);
            var el = document.getElementById('lolPhotoModal');
            if (el && typeof bootstrap !== 'undefined') bootstrap.Modal.getOrCreateInstance(el).show();
        });
    }
    if (photos.length) updateHero(heroIdx);

    /* ── Share ── */
    function doShare() {
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
    ['lolShareSidebarBtn', 'lolShareHeroBtn', 'lolMobileShareBtn'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('click', doShare);
    });

    /* ── Interaction Hub ── */
    (function () {
        var lolAiExamples = [
            'What utilities are included in the rent?',
            'Are pets allowed at this property?',
            'What is the minimum lease term?',
            'What are the move-in costs and deposits?',
            'Is parking included or available?',
            'Is the property near public transit?',
            'What appliances are included?'
        ];
        var lolAiIdx = 0;
        var lolAiEl = document.getElementById('lolAiExampleText');
        var lolAiModal = document.getElementById('lolAiModal');
        if (lolAiEl && lolAiModal) {
            lolAiEl.textContent = lolAiExamples[0];
            lolAiModal.addEventListener('show.bs.modal', function () {
                lolAiEl.textContent = lolAiExamples[lolAiIdx % lolAiExamples.length];
                lolAiIdx++;
            });
        }
        var lolHubNativeBtn = document.getElementById('lolHubNativeShareBtn');
        if (navigator.share && lolHubNativeBtn) { lolHubNativeBtn.style.display = ''; }
        /* Copy Link — goes directly to clipboard regardless of native share availability */
        var lolHubCopyBtn = document.getElementById('lolHubCopyBtn');
        if (lolHubCopyBtn) {
            lolHubCopyBtn.addEventListener('click', function () {
                var url = window.location.href;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(function () {
                        alert('Link copied to clipboard!');
                    }).catch(function () { alert('Share: ' + url); });
                } else { alert('Share: ' + url); }
            });
        }
        /* Native Share button — uses doShare() which correctly invokes navigator.share */
        if (lolHubNativeBtn) { lolHubNativeBtn.addEventListener('click', doShare); }
    }());

    /* ── Smooth-scroll nav tabs + active-section highlighting ── */
    var LOL_OFFSET = 82;
    var lolNavLinks = Array.from(document.querySelectorAll('#lolNavTabs a[href^="#"]'));
    var lolSections  = lolNavLinks.map(function (a) { return document.querySelector(a.getAttribute('href')); }).filter(Boolean);
    lolSections.sort(function (a, b) { return a.offsetTop - b.offsetTop; });

    lolNavLinks.forEach(function (a) {
        a.addEventListener('click', function (e) {
            var target = document.querySelector(a.getAttribute('href'));
            if (!target) return;
            e.preventDefault();
            var top = target.getBoundingClientRect().top + window.scrollY - LOL_OFFSET;
            window.scrollTo({ top: top, behavior: 'smooth' });
        });
    });

    function lolOnScroll() {
        var scrollY = window.scrollY + LOL_OFFSET + 10;
        var active = null;
        lolSections.forEach(function (s) {
            if (s && s.offsetTop <= scrollY) active = s;
        });
        lolNavLinks.forEach(function (a) { a.classList.remove('lol-nav-active'); });
        if (active) {
            var link = document.querySelector('#lolNavTabs a[href="#' + active.id + '"]');
            if (link) link.classList.add('lol-nav-active');
        }
    }
    window.addEventListener('scroll', lolOnScroll, { passive: true });
    lolOnScroll();

    /* ---- Auto-reopen modals after validation failure ---- */
    @if(session('open_modal') === 'question')
    (function () {
        var el = document.getElementById('lolQuestionModal');
        if (el && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(el).show();
        }
    }());
    @endif
    @if(session('open_modal') === 'showing')
    (function () {
        var el = document.getElementById('lolShowingModal');
        if (el && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(el).show();
        }
    }());
    @endif
})();
</script>
@endpush
@endsection
