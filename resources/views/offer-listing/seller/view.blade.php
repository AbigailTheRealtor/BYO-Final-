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
    padding: 1.6rem 1.4rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
    min-height: 280px;
    gap: 0.1rem;
}
.sol-view-page .sol-hero-price {
    font-size: 1.85rem;
    font-weight: 800;
    color: #1e293b;
    letter-spacing: -0.03em;
    line-height: 1.15;
}
.sol-view-page .sol-hero-address {
    color: #475569;
    font-size: 0.9rem;
    margin-top: 0.3rem;
    word-break: break-word;
    overflow-wrap: anywhere;
    line-height: 1.45;
}
.sol-view-page .sol-hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem 0.9rem;
    margin-top: 0.65rem;
    font-size: 0.84rem;
    color: #334155;
}
.sol-view-page .sol-hero-meta-item i {
    color: #2563eb;
    margin-right: 4px;
}
.sol-view-page .sol-hero-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: 0.75rem;
}
.sol-view-page .sol-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.73rem;
    font-weight: 600;
    padding: 0.25rem 0.55rem;
    border-radius: 20px;
    border: 1px solid;
    white-space: nowrap;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
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
    font-size: 0.8rem;
    font-weight: 700;
    padding: 0.28rem 0.7rem;
    border-radius: 20px;
    background: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
    margin-top: 0.6rem;
    white-space: nowrap;
}
.sol-view-page .sol-hero-dates {
    font-size: 0.76rem;
    color: #94a3b8;
    margin-top: 0.4rem;
}
.sol-view-page .sol-hero-ctas {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.9rem;
    padding-top: 0.9rem;
    border-top: 1px solid #f1f5f9;
}
.sol-view-page .sol-hero-ctas .btn {
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.42rem 0.75rem;
    border-radius: 8px;
    white-space: nowrap;
    flex-shrink: 0;
}
.sol-view-page .sol-hero-ctas .btn-primary,
.sol-view-page button.btn.btn-primary.sol-hero-cta-btn {
    background-color: #2563eb !important;
    border-color: #2563eb !important;
    color: #fff !important;
}
.sol-view-page .sol-hero-ctas .btn-primary i,
.sol-view-page button.btn.btn-primary.sol-hero-cta-btn i {
    color: #fff !important;
}
.sol-view-page .sol-hero-ctas .btn-primary:hover,
.sol-view-page .sol-hero-ctas .btn-primary:focus,
.sol-view-page button.btn.btn-primary.sol-hero-cta-btn:hover,
.sol-view-page button.btn.btn-primary.sol-hero-cta-btn:focus {
    background-color: #1d4ed8 !important;
    border-color: #1d4ed8 !important;
    color: #fff !important;
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
.sol-view-page .sol-nav-tabs li a:hover,
.sol-view-page .sol-nav-tabs li a.sol-nav-active {
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
.sol-view-page .sol-action-outline { background: #fff; color: #334155; border-color: #cbd5e1; }
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

/* ---- Subsection headings — normalised typography ---- */
.sol-view-page h6.fw-semibold,
.sol-view-page h6.fw-bold {
    color: #1e293b;
    font-size: 0.97rem;
    font-weight: 600;
    letter-spacing: 0;
    font-stretch: normal;
    font-family: inherit;
    text-transform: none;
}

/* ---- Row spacing — slightly more breathing room in card bodies ---- */
.sol-view-page .section-card .card-body .row.mb-2 {
    margin-bottom: 0.65rem !important;
}

/* ---- Mobile bar: highlight the Submit Offer button ---- */
.sol-mobile-bar-btn.sol-mobile-bar-offer {
    background: #2563eb !important;
    color: #fff !important;
    border-radius: 10px;
}
.sol-mobile-bar-btn.sol-mobile-bar-offer i {
    color: #fff !important;
}
.sol-mobile-bar-btn.sol-mobile-bar-offer:hover,
.sol-mobile-bar-btn.sol-mobile-bar-offer:active {
    background: #1d4ed8 !important;
    color: #fff !important;
}

/* ---- Hero carousel overlay controls ---- */
.sol-view-page .sol-hero-carousel-wrap {
    position: relative;
    height: 100%;
    min-height: 280px;
    overflow: hidden;
    background: #0f172a;
}
.sol-view-page .sol-hero-carousel-wrap .sol-hero-photo {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    max-height: none;
}
.sol-view-page .sol-hero-carousel-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0,0,0,.45);
    color: #fff;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    font-size: 1.55rem;
    line-height: 1;
    cursor: pointer;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .15s;
    padding: 0;
    flex-shrink: 0;
}
.sol-view-page .sol-hero-carousel-arrow:hover { background: rgba(0,0,0,.72); }
.sol-view-page .sol-hero-carousel-arrow.sol-hero-prev { left: 12px; }
.sol-view-page .sol-hero-carousel-arrow.sol-hero-next { right: 12px; }
.sol-view-page .sol-hero-carousel-counter {
    position: absolute;
    bottom: 12px;
    right: 14px;
    background: rgba(0,0,0,.50);
    color: #fff;
    font-size: 0.76rem;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 20px;
    z-index: 10;
    pointer-events: none;
    letter-spacing: .02em;
}

/* ============================================================
   sol-interaction-hub — six-panel action hub
   ============================================================ */
.sol-view-page .sol-interaction-hub {
    margin-bottom: 1.75rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    padding: 1.25rem 1rem;
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
}
.sol-view-page .sol-interaction-hub-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #94a3b8;
    margin-bottom: 0.9rem;
    padding-bottom: 0.6rem;
    border-bottom: 1px solid #e2e8f0;
}
.sol-view-page .sol-interaction-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 0.75rem;
}
@media (max-width: 1199.98px) {
    .sol-view-page .sol-interaction-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
@media (max-width: 767.98px) {
    .sol-view-page .sol-interaction-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 479.98px) {
    .sol-view-page .sol-interaction-grid {
        grid-template-columns: 1fr;
    }
}
.sol-view-page .sol-interaction-card {
    background: #fff;
    border: 1px solid #CBD5E1;
    border-radius: 0.75rem;
    padding: 1rem 0.85rem 0.9rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    transition: box-shadow .15s, border-color .15s, transform .15s;
    min-height: 0;
}
.sol-view-page .sol-interaction-cta-hire {
    background: #fff;
    color: #2563eb;
    border: 1px solid #2563eb;
    font-weight: 700;
}
.sol-view-page .sol-interaction-cta-hire:hover {
    background: #eff6ff;
    color: #1d4ed8;
    border-color: #2563eb;
}
.sol-view-page .sol-action-hire {
    border-color: #2563eb !important;
    color: #2563eb !important;
    font-weight: 700;
    background: #fff;
}
.sol-view-page .sol-action-hire:hover {
    background: #eff6ff !important;
    border-color: #2563eb !important;
    color: #1d4ed8 !important;
}
.sol-view-page .sol-interaction-card:hover {
    box-shadow: 0 4px 18px rgba(37,99,235,.12);
    border-color: #bfdbfe;
    transform: translateY(-2px);
}
.sol-view-page .sol-interaction-card-icon {
    font-size: 1.45rem;
    color: #2563eb;
    margin-bottom: 0.1rem;
    line-height: 1;
}
.sol-view-page .sol-interaction-card-label {
    font-size: 0.83rem;
    font-weight: 700;
    color: #1e293b;
    letter-spacing: -0.01em;
    line-height: 1.2;
}
.sol-view-page .sol-interaction-card-helper {
    font-size: 0.74rem;
    color: #64748b;
    line-height: 1.45;
    flex: 1;
}
.sol-view-page .sol-interaction-price-row {
    font-size: 0.72rem;
    color: #334155;
    line-height: 1.5;
    margin-bottom: 0.1rem;
}
.sol-view-page .sol-interaction-price-row strong {
    color: #1e293b;
    font-size: 0.8rem;
}
.sol-view-page .sol-interaction-cta {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.38rem 0.7rem;
    border-radius: 7px;
    border: none;
    cursor: pointer;
    transition: background .15s, color .15s;
    white-space: nowrap;
    margin-top: 0.25rem;
    align-self: flex-start;
    text-decoration: none;
}
.sol-view-page .sol-interaction-cta:focus-visible {
    outline: 2px solid #2563eb;
    outline-offset: 2px;
}
.sol-view-page .sol-interaction-cta-primary {
    background: #2563eb;
    color: #fff;
}
.sol-view-page .sol-interaction-cta-primary:hover { background: #1d4ed8; color: #fff; }
.sol-view-page .sol-interaction-cta-outline {
    background: #fff;
    color: #334155;
    border: 1px solid #cbd5e1;
}
.sol-view-page .sol-interaction-cta-outline:hover { background: #f8fafc; color: #1e293b; border-color: #94a3b8; }
.sol-view-page .sol-interaction-cta-muted {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
    cursor: default;
    font-weight: 600;
    opacity: .75;
}
.sol-view-page .sol-interaction-share-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: 0.25rem;
}
.sol-view-page .sol-interaction-activity-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.72rem;
    color: #64748b;
    padding: 0.18rem 0;
    border-bottom: 1px solid #f1f5f9;
}
.sol-view-page .sol-interaction-activity-row:last-child { border-bottom: none; }
.sol-view-page .sol-interaction-activity-val {
    font-weight: 700;
    color: #94a3b8;
    font-size: 0.72rem;
}
.sol-view-page .sol-interaction-ai-chips {
    display: flex;
    flex-direction: column;
    gap: 0.28rem;
    margin-bottom: 0.35rem;
}
.sol-view-page .sol-interaction-ai-chip {
    font-size: 0.69rem;
    color: #3b82f6;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 20px;
    padding: 0.2rem 0.5rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
    cursor: default;
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
        $heroPhotoUrls = [];
        $coverPhotoIdx = 0;
        $_hpTmpIdx = 0;
        foreach ($propertyPhotos as $ph) {
            $fn = is_array($ph) ? ($ph['filename'] ?? '') : $ph;
            if (!$fn) continue;
            $heroPhotoUrls[] = asset('storage/auction/images/' . $fn);
            if (is_array($ph) && !empty($ph['is_cover'])) {
                $coverPhoto = $fn;
                $coverPhotoIdx = $_hpTmpIdx;
            }
            if (!$coverPhoto) $coverPhoto = $fn;
            $_hpTmpIdx++;
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
        $badgeFinancing      = count($heroOfFin) > 0;
        $badgeLeaseOpt       = in_array('Lease Option', $heroOfFin) || in_array('Lease Purchase', $heroOfFin);
        $badgeCrypto         = in_array('Cryptocurrency', $heroOfFin);
        $badgeHOA            = in_array(strtolower((string)($str('has_hoa'))), ['yes','1','true']);
        /* Bidding Period badge: only when auction_type explicitly indicates a bidding format */
        $badgeBidding = (
            stripos($str('auction_type'), 'bidding') !== false
            || stripos($str('auction_type'), 'auction') !== false
        );
        /* Additional badge variables for priority ordering */
        $badgeAssumable       = in_array('Assumable Mortgage', $heroOfFin);
        $badgeSellerFinancing = in_array('Seller Financing', $heroOfFin);
        $badgeLeasePurchase   = in_array('Lease Purchase', $heroOfFin);
        $badgeExchange        = in_array('Exchange', $heroOfFin) || in_array('Trade', $heroOfFin);
        $badgeNoHOA           = in_array(strtolower((string)($str('has_hoa'))), ['no','0','false']);

        /* Priority-ordered badge candidate list; slice to max 5 */
        $heroBadges = [
            ['show' => $badgeWaterfront,                      'label' => 'Waterfront / View',  'icon' => 'fa-solid fa-water',               'color' => 'teal',   'strong' => true],
            ['show' => $badgePool,                            'label' => 'Pool',               'icon' => 'fa-solid fa-water-ladder',        'color' => 'blue',   'strong' => true],
            ['show' => $badgeAssumable,                       'label' => 'Assumable Mortgage', 'icon' => 'fa-solid fa-hand-holding-dollar', 'color' => 'green',  'strong' => true],
            ['show' => $badgeSellerFinancing,                 'label' => 'Seller Financing',   'icon' => 'fa-solid fa-hand-holding-dollar', 'color' => 'green',  'strong' => true],
            ['show' => in_array('Lease Option', $heroOfFin),  'label' => 'Lease Option',       'icon' => 'fa-solid fa-key',                 'color' => 'purple', 'strong' => true],
            ['show' => $badgeLeasePurchase,                   'label' => 'Lease Purchase',     'icon' => 'fa-solid fa-key',                 'color' => 'purple', 'strong' => true],
            ['show' => $badgeCrypto,                          'label' => 'Crypto Accepted',    'icon' => 'fa-brands fa-bitcoin',            'color' => 'amber',  'strong' => true],
            ['show' => $badgeExchange,                        'label' => 'Exchange / Trade',   'icon' => 'fa-solid fa-arrows-rotate',       'color' => 'amber',  'strong' => true],
            ['show' => $badgeNoHOA,                           'label' => 'No HOA',             'icon' => 'fa-solid fa-circle-check',        'color' => 'green',  'strong' => false],
            ['show' => $badgeHOA,                             'label' => 'HOA',                'icon' => 'fa-solid fa-building-columns',    'color' => 'rose',   'strong' => false],
            ['show' => $badgeFinancing,                       'label' => 'Financing Available','icon' => 'fa-solid fa-hand-holding-dollar', 'color' => 'green',  'strong' => false],
            ['show' => (bool)$heroPropType,                   'label' => (string)$heroPropType,'icon' => 'fa-solid fa-tag',                 'color' => 'blue',   'strong' => false],
            ['show' => (bool)$heroStatus,                     'label' => (string)$heroStatus,  'icon' => 'fa-solid fa-circle-check',        'color' => 'green',  'strong' => false],
        ];
        $heroBadgesDisplay = array_slice(array_values(array_filter($heroBadges, fn($b) => $b['show'])), 0, 5);
    @endphp

    {{-- ===== HERO SECTION ===== --}}
    <div class="sol-hero mb-4">
        <div class="row g-0" style="min-height:280px;">
            <div class="col-lg-8">
                <div class="sol-hero-carousel-wrap">
                    @if(count($heroPhotoUrls))
                        <img id="heroCarouselImg"
                             src="{{ $heroPhotoUrls[$coverPhotoIdx] }}"
                             alt="Property photo"
                             class="sol-hero-photo"
                             style="cursor:pointer;"
                             onerror="this.style.display='none';var ph=document.getElementById('heroCarouselPlaceholder');if(ph)ph.style.display='flex'">
                        <div id="heroCarouselPlaceholder" class="sol-hero-photo-placeholder" style="display:none;">
                            <i class="fa-solid fa-house"></i>
                        </div>
                        @if(count($heroPhotoUrls) > 1)
                        <button class="sol-hero-carousel-arrow sol-hero-prev" id="heroCarouselPrev" aria-label="Previous photo">&#8249;</button>
                        <button class="sol-hero-carousel-arrow sol-hero-next" id="heroCarouselNext" aria-label="Next photo">&#8250;</button>
                        <div class="sol-hero-carousel-counter" id="heroCarouselCounter">{{ $coverPhotoIdx + 1 }} / {{ count($heroPhotoUrls) }}</div>
                        @endif
                    @else
                        <div class="sol-hero-photo-placeholder">
                            <i class="fa-solid fa-house"></i>
                        </div>
                    @endif
                </div>
                <script>var _solHeroPhotos={!! json_encode($heroPhotoUrls) !!};var _solHeroStartIdx={{ $coverPhotoIdx }};</script>
            </div>
            <div class="col-lg-4">
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
                        @foreach ($heroBadgesDisplay as $b)
                            <span class="sol-badge sol-badge-{{ $b['color'] }}"><i class="{{ $b['icon'] }}"></i> {{ $b['label'] }}</span>
                        @endforeach
                    </div>

                    @php
                        $heroStandoutParts = array_values(array_map(
                            fn($b) => $b['label'],
                            array_filter($heroBadgesDisplay, fn($b) => $b['strong'])
                        ));
                    @endphp
                    @if(count($heroStandoutParts) >= 2)
                        <div style="margin-top:10px;padding:10px 14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;">
                            <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#1d4ed8;margin-bottom:3px;">Why This Property Stands Out</div>
                            <div style="font-size:0.88rem;color:#1e3a5f;">This property stands out for its {{ implode(', ', array_slice($heroStandoutParts, 0, -1)) }}{{ count($heroStandoutParts) > 1 ? ' and ' . end($heroStandoutParts) : $heroStandoutParts[0] }}.</div>
                        </div>
                    @endif

                    <div class="sol-hero-ctas">
                        <form method="POST" action="{{ route('offers.store') }}" style="display:inline;">
                            @csrf
                            <input type="hidden" name="offer_auction_id" value="{{ $offerAuction->id }}">
                            <input type="hidden" name="role" value="seller">
                            <button type="submit" class="btn btn-primary sol-hero-cta-btn" aria-label="Submit an offer on this property">
                                <i class="fa-solid fa-file-signature me-1"></i>Submit Offer
                            </button>
                        </form>
                        <button type="button" class="btn btn-outline-secondary sol-hero-cta-btn" data-sol-modal="#solShowingModal" aria-label="Schedule a showing">
                            <i class="fa-solid fa-calendar-days me-1"></i>Schedule Showing
                        </button>
                        <button type="button" class="btn btn-outline-secondary sol-hero-cta-btn" data-sol-modal="#solQuestionModal" aria-label="Ask a question about this listing">
                            <i class="fa-solid fa-circle-question me-1"></i>Ask a Question
                        </button>
                        @auth
                            @if(auth()->id() != $auction->user_id && !(isset($meta['hired_agent_id']) && (int)$meta['hired_agent_id'] === (int)auth()->id()))
                            <button type="button" class="btn btn-outline-success sol-hero-cta-btn"
                                    data-bs-toggle="modal" data-bs-target="#solShowingRequestModal"
                                    aria-label="Request a showing for this property">
                                <i class="fa-solid fa-calendar-plus me-1"></i>Request a Showing
                            </button>
                            @endif
                        @else
                        <a href="{{ route('login') }}" class="btn btn-outline-secondary sol-hero-cta-btn"
                           aria-label="Log in to request a showing">
                            <i class="fa-solid fa-lock me-1"></i>Log in to Request a Showing
                        </a>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== ESTIMATED MONTHLY PAYMENT CALCULATOR ===== --}}
    @include('seller_property._mortgage_calculator', compact('calcData'))

    {{-- ===== INTERACTION HUB ===== --}}
    @php
        $hubAuctionType  = strtolower($str('auction_type'));
        $hubIsBidding    = (strpos($hubAuctionType, 'bidding') !== false || strpos($hubAuctionType, 'auction') !== false);
        $hubStartPrice   = $fmtMoney($str('starting_price'));
        $hubReserve      = $fmtMoney($str('reserve_price'));
        $hubBuyNow       = $fmtMoney($str('buy_now_price'));
        $hubAsking       = $fmtMoney($str('desired_sale_price')) ?: $fmtMoney($str('purchase_price')) ?: $heroPrice;
        $hubBidEnd       = $fmtDate($str('bidding_end_date') ?: $str('offer_deadline'));
        $hubReservePublic = in_array(strtolower((string)($str('reserve_price_public') ?: '')), ['yes','1','true']);
        $hubLastUpdated  = $auction->updated_at ? \Carbon\Carbon::parse($auction->updated_at)->format('M j, Y') : null;
    @endphp
    <div class="sol-interaction-hub" id="sol-interaction-hub">
        <div class="sol-interaction-hub-label"><i class="fa-solid fa-bolt me-1"></i>Quick Actions &amp; Listing Info</div>
        <div class="sol-interaction-grid">

            {{-- 1. Submit Offer --}}
            <div class="sol-interaction-card">
                <div class="sol-interaction-card-icon"><i class="fa-solid fa-file-signature"></i></div>
                <div class="sol-interaction-card-label">Submit Offer</div>
                @if($hubIsBidding)
                    <div class="sol-interaction-price-row">
                        @if($hubStartPrice)<div>Starting: <strong>{{ $hubStartPrice }}</strong></div>@endif
                        @if($hubReserve && $hubReservePublic)<div>Reserve: <strong>{{ $hubReserve }}</strong></div>@endif
                        @if($hubBuyNow)<div>Buy Now: <strong>{{ $hubBuyNow }}</strong></div>@endif
                        @if($hubBidEnd)<div style="color:#94a3b8;font-size:.69rem;">Bidding ends {{ $hubBidEnd }}</div>@endif
                    </div>
                @elseif($hubAsking)
                    <div class="sol-interaction-price-row">Asking: <strong>{{ $hubAsking }}</strong></div>
                @else
                    <div class="sol-interaction-card-helper">Review listing terms and submit your offer.</div>
                @endif
                <form method="POST" action="{{ route('offers.store') }}">
                    @csrf
                    <input type="hidden" name="offer_auction_id" value="{{ $offerAuction->id }}">
                    <input type="hidden" name="role" value="seller">
                    <button type="submit" class="sol-interaction-cta sol-interaction-cta-primary"
                            aria-label="Submit an offer on this property">
                        <i class="fa-solid fa-file-signature"></i>Submit Offer
                    </button>
                </form>
            </div>

            {{-- 2. Schedule Showing --}}
            <div class="sol-interaction-card">
                <div class="sol-interaction-card-icon"><i class="fa-solid fa-calendar-days"></i></div>
                <div class="sol-interaction-card-label">Schedule Showing</div>
                <div class="sol-interaction-card-helper">Request an in-person or virtual showing.</div>
                <button type="button" class="sol-interaction-cta sol-interaction-cta-outline"
                        data-bs-toggle="modal" data-bs-target="#solShowingModal"
                        aria-label="Schedule a showing for this property">
                    <i class="fa-solid fa-calendar-plus"></i>Request Showing
                </button>
            </div>

            {{-- 3. Ask AI --}}
            <div class="sol-interaction-card">
                <div class="sol-interaction-card-icon"><i class="fa-solid fa-robot"></i></div>
                <div class="sol-interaction-card-label">Ask AI</div>
                <div class="sol-interaction-ai-chips">
                    <span class="sol-interaction-ai-chip">HOA fees &amp; what they cover?</span>
                    <span class="sol-interaction-ai-chip">Is this in a flood zone?</span>
                    <span class="sol-interaction-ai-chip">Financing options available?</span>
                    <span class="sol-interaction-ai-chip">Roof age &amp; condition?</span>
                    <span class="sol-interaction-ai-chip">School districts nearby?</span>
                </div>
                <input type="text" class="form-control form-control-sm"
                       placeholder="Ask a question about this property…"
                       aria-label="AI question input"
                       disabled
                       style="font-size:.73rem;border-radius:6px;background:#f8fafc;cursor:default;">
                <button type="button" class="sol-interaction-cta sol-interaction-cta-outline"
                        data-bs-toggle="modal" data-bs-target="#solAiModal"
                        aria-label="Ask AI a question about this property">
                    <i class="fa-solid fa-robot"></i>Ask AI
                </button>
            </div>

            {{-- 4. Ask a Question --}}
            <div class="sol-interaction-card">
                <div class="sol-interaction-card-icon"><i class="fa-solid fa-circle-question"></i></div>
                <div class="sol-interaction-card-label">Ask a Question</div>
                <div class="sol-interaction-card-helper">Send a direct question to the listing contact.</div>
                <button type="button" class="sol-interaction-cta sol-interaction-cta-outline"
                        data-bs-toggle="modal" data-bs-target="#solQuestionModal"
                        aria-label="Ask the agent a question about this listing">
                    <i class="fa-solid fa-paper-plane"></i>Send Question
                </button>
            </div>

            {{-- 5. Share Listing --}}
            <div class="sol-interaction-card">
                <div class="sol-interaction-card-icon"><i class="fa-solid fa-share-nodes"></i></div>
                <div class="sol-interaction-card-label">Share Listing</div>
                <div class="sol-interaction-card-helper">Share this property with friends, family, or your network.</div>
                <div class="sol-interaction-share-row">
                    <button type="button" class="sol-interaction-cta sol-interaction-cta-outline" id="solHubCopyBtn"
                            aria-label="Copy listing link to clipboard">
                        <i class="fa-solid fa-link"></i>Copy Link
                    </button>
                    <button type="button" class="sol-interaction-cta sol-interaction-cta-outline" id="solHubNativeShareBtn"
                            style="display:none;" aria-label="Share this listing via your device's share sheet">
                        <i class="fa-solid fa-share-nodes"></i>Share
                    </button>
                </div>
                <div class="sol-interaction-share-row" style="margin-top:.15rem;">
                    <span class="sol-interaction-cta sol-interaction-cta-muted" aria-label="QR Code — coming soon">
                        <i class="fa-solid fa-qrcode"></i>QR Code
                    </span>
                    <span class="sol-interaction-cta sol-interaction-cta-muted" aria-label="Embed widget — coming soon">
                        <i class="fa-solid fa-code"></i>Embed
                    </span>
                </div>
            </div>

            {{-- 6. Hire an Agent --}}
            <div class="sol-interaction-card">
                <div class="sol-interaction-card-icon"><i class="fa-solid fa-user-tie"></i></div>
                <div class="sol-interaction-card-label">Hire an Agent</div>
                <div class="sol-interaction-card-helper">Need representation? Connect with a licensed real estate agent.</div>
                <button type="button" class="sol-interaction-cta sol-interaction-cta-hire"
                        data-bs-toggle="modal" data-bs-target="#solHireAgentModal"
                        aria-label="Find and hire a real estate agent">
                    <i class="fa-solid fa-user-tie"></i>Find an Agent
                </button>
            </div>

            {{-- Activity — hidden until live data is available --}}
            @if(false)
            <div class="sol-interaction-card">
                <div class="sol-interaction-card-icon"><i class="fa-solid fa-chart-simple"></i></div>
                <div class="sol-interaction-card-label">Activity</div>
                <div style="margin-top:.1rem;">
                    <div class="sol-interaction-activity-row">
                        <span>Views</span><span class="sol-interaction-activity-val">Coming Soon</span>
                    </div>
                    <div class="sol-interaction-activity-row">
                        <span>Saves</span><span class="sol-interaction-activity-val">Coming Soon</span>
                    </div>
                    <div class="sol-interaction-activity-row">
                        <span>Questions</span><span class="sol-interaction-activity-val">Coming Soon</span>
                    </div>
                    <div class="sol-interaction-activity-row">
                        <span>Offers</span><span class="sol-interaction-activity-val">Coming Soon</span>
                    </div>
                    @if($hubLastUpdated)
                    <div class="sol-interaction-activity-row" style="margin-top:.3rem;border-top:1px solid #e2e8f0;padding-top:.3rem;">
                        <span style="color:#94a3b8;">Last Updated</span>
                        <span class="sol-interaction-activity-val" style="color:#475569;">{{ $hubLastUpdated }}</span>
                    </div>
                    @endif
                </div>
            </div>
            @endif

        </div>{{-- /sol-interaction-grid --}}
    </div>{{-- /sol-interaction-hub --}}

    {{-- ===== TWO-COLUMN LAYOUT: MAIN + STICKY RAIL ===== --}}
    <div class="row g-4 align-items-start">

        {{-- Main content column --}}
        <div class="col-lg-9 sol-main-content-wrap">

    {{-- ===== SMOOTH-SCROLL NAV TABS ===== --}}
    <div class="sol-nav-tabs-wrap">
        <ul class="sol-nav-tabs" id="solNavTabs">
            <li><a href="#section-overview">Overview</a></li>
            @if($val('additional_details'))<li><a href="#section-description">Description</a></li>@endif
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

        window._solShowPhoto = showPhoto;
    })();
    </script>

    {{-- Hero image carousel script --}}
    <script>
    (function () {
        var photos = (typeof _solHeroPhotos !== 'undefined') ? _solHeroPhotos : [];
        if (photos.length === 0) return;

        var heroIdx = (typeof _solHeroStartIdx !== 'undefined') ? _solHeroStartIdx : 0;
        var heroImg     = document.getElementById('heroCarouselImg');
        var heroPrev    = document.getElementById('heroCarouselPrev');
        var heroNext    = document.getElementById('heroCarouselNext');
        var heroCounter = document.getElementById('heroCarouselCounter');

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
                if (typeof window._solShowPhoto === 'function') {
                    window._solShowPhoto(heroIdx);
                }
                var modalEl = document.getElementById('photoModal');
                if (modalEl && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            });
        }

        updateHero(heroIdx);
    })();
    </script>
    @endif
    @endif

    {{-- Property Description --}}
    @if($val('additional_details'))
    <div class="card section-card" id="section-description">
        <div class="card-header"><i class="fa-solid fa-align-left me-2"></i>Property Description</div>
        <div class="card-body">
            <p class="field-value mb-0">{!! nl2br(e($val('additional_details'))) !!}</p>
        </div>
    </div>
    @endif

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

    {{-- Listing Details --}}
    <div class="card section-card" id="section-overview">
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
                        <span class="badge bg-info text-dark sol-bp-timer"
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
    @php
        $hasBrokerComp = $str('commission_structure') || $str('commission_structure_type')
            || $str('commission_structure_type_fee_flat') || $str('commission_structure_type_fee_percentage')
            || $str('commission_structure_type_fee_percentage_combo') || $str('commission_structure_type_fee_flat_combo')
            || $str('commission_structure_type_fee_other') || $str('agency_agreement_timeframe')
            || $str('agency_agreement_custom');
    @endphp
    @if($hasBrokerComp)
    <div class="card section-card">
        <div class="card-header"><i class="fa-solid fa-file-contract me-2"></i>Broker Compensation &amp; Agency Agreement</div>
        <div class="card-body">

            {{-- Buyer's Broker Compensation --}}
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
            </div>
        </div>
    </div>
    @endif

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
                        $digits = preg_replace('/\D/', '', $phone);
                        if ($phone && strlen($digits) === 10) {
                            $phone = '(' . substr($digits, 0, 3) . ') ' . substr($digits, 3, 3) . '-' . substr($digits, 6);
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

                <form method="POST" action="{{ route('offers.store') }}">
                    @csrf
                    <input type="hidden" name="offer_auction_id" value="{{ $offerAuction->id }}">
                    <input type="hidden" name="role" value="seller">
                    <button type="submit" class="sol-action-btn sol-action-primary">
                        <i class="fa-solid fa-file-signature"></i>Submit Offer
                    </button>
                </form>
                <button class="sol-action-btn sol-action-outline" data-bs-toggle="modal" data-bs-target="#solShowingModal">
                    <i class="fa-solid fa-calendar-days"></i>Schedule Showing
                </button>
                @auth
                    @if(auth()->id() != $auction->user_id && !(isset($meta['hired_agent_id']) && (int)$meta['hired_agent_id'] === (int)auth()->id()))
                    <button class="sol-action-btn sol-action-outline" data-bs-toggle="modal" data-bs-target="#solShowingRequestModal"
                            style="border-color:#16a34a;color:#15803d;">
                        <i class="fa-solid fa-calendar-plus"></i>Request a Showing
                    </button>
                    @endif
                @else
                <a href="{{ route('login') }}" class="sol-action-btn sol-action-outline">
                    <i class="fa-solid fa-lock"></i>Log in to Request a Showing
                </a>
                @endauth
                <button class="sol-action-btn sol-action-outline" data-bs-toggle="modal" data-bs-target="#solAiModal">
                    <i class="fa-solid fa-robot"></i>Ask AI About Property
                </button>
                <button class="sol-action-btn sol-action-outline" data-bs-toggle="modal" data-bs-target="#solQuestionModal">
                    <i class="fa-solid fa-circle-question"></i>Ask a Question
                </button>
                <button type="button" class="sol-action-btn sol-action-outline sol-action-hire"
                        data-bs-toggle="modal" data-bs-target="#solHireAgentModal">
                    <i class="fa-solid fa-user-tie"></i>Hire an Agent
                </button>
                <button class="sol-action-btn sol-action-outline" type="button" disabled style="cursor:default;opacity:.6;">
                    <i class="fa-regular fa-bookmark"></i>Save Listing
                </button>
                <button class="sol-action-btn sol-action-outline" id="solShareBtn" type="button">
                    <i class="fa-solid fa-share-nodes"></i>Share Listing
                </button>
                {{-- Bidding Ends --}}
                @if($hasBPTimer)
                @php
                    $_solSidebarEndDate = null;
                    $_solExpDateStr = $str('expiration_date');
                    if ($_solExpDateStr) {
                        $_solSidebarEndDate = $fmtDate($_solExpDateStr);
                    } elseif (!empty($_timerEnd) && $_timerEnd instanceof \Carbon\Carbon) {
                        $_solSidebarEndDate = $_timerEnd->format('M j, Y');
                    }
                @endphp
                @if($_solSidebarEndDate)
                <div class="mb-3 pb-3 mt-3" style="border-bottom:1px solid #f1f5f9;">
                    <div class="d-flex justify-content-between" style="font-size:.78rem;color:#64748b;">
                        <span><i class="fa-regular fa-clock me-1"></i>Bidding Ends</span>
                        <span style="font-weight:700;color:#475569;">{{ $_solSidebarEndDate }}</span>
                    </div>
                </div>
                @endif
                @endif

                <a href="{{ route('offer.listing.seller.searchListing') }}" class="sol-action-btn sol-action-outline">
                    <i class="fa-solid fa-arrow-left"></i>Back to Search
                </a>

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

    {{-- Modal: Schedule a Showing --}}
    <div class="modal fade" id="solShowingModal" tabindex="-1" aria-labelledby="solShowingModalLabel" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content" style="border-radius:.85rem;overflow:hidden;border:none;">
                <div class="modal-header sol-modal-header">
                    <h5 class="modal-title fw-bold" id="solShowingModalLabel"><i class="fa-solid fa-calendar-days me-2"></i>Schedule a Showing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1);"></button>
                </div>
                <form method="POST" action="{{ route('offer.listing.seller.showing', ['auction' => $auction->id]) }}">
                @csrf
                <input type="text" name="website" value="" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;" tabindex="-1" autocomplete="off" aria-hidden="true">
                <div class="modal-body p-4">
                    @if(session('success') && str_contains(session('success'), 'showing'))
                        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Your Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name', 'showingInquiry') is-invalid @enderror" name="name" placeholder="Jane Smith" value="{{ old('name') }}" required>
                            @error('name', 'showingInquiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email', 'showingInquiry') is-invalid @enderror" name="email" placeholder="jane@example.com" value="{{ old('email') }}" required>
                            @error('email', 'showingInquiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
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

    {{-- Modal: Ask a Question --}}
    <div class="modal fade" id="solQuestionModal" tabindex="-1" aria-labelledby="solQuestionModalLabel" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content" style="border-radius:.85rem;overflow:hidden;border:none;">
                <div class="modal-header sol-modal-header">
                    <h5 class="modal-title fw-bold" id="solQuestionModalLabel"><i class="fa-solid fa-circle-question me-2"></i>Ask a Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1);"></button>
                </div>
                <form method="POST" action="{{ route('offer.listing.seller.question', ['auction' => $auction->id]) }}">
                @csrf
                <input type="text" name="website" value="" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;" tabindex="-1" autocomplete="off" aria-hidden="true">
                <div class="modal-body p-4">
                    @if(session('success') && str_contains(session('success'), 'question'))
                        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Your Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name', 'questionInquiry') is-invalid @enderror" name="name" placeholder="Jane Smith" value="{{ old('name') }}" required>
                            @error('name', 'questionInquiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email', 'questionInquiry') is-invalid @enderror" name="email" placeholder="jane@example.com" value="{{ old('email') }}" required>
                            @error('email', 'questionInquiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Phone Number</label>
                            <input type="tel" class="form-control" name="phone" placeholder="(555) 000-0000" value="{{ old('phone') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Your Question <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('question', 'questionInquiry') is-invalid @enderror" name="question" rows="4" placeholder="What would you like to know about this property?" required>{{ old('question') }}</textarea>
                            @error('question', 'questionInquiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                    @php $__solAiSuggestions = app(\App\Services\AskAi\AskAiSuggestedQuestionsService::class)->forListing('seller'); @endphp
                    @if(!empty($__solAiSuggestions))
                    <div id="solAiSuggestions"
                         aria-label="Suggested questions for this listing"
                         aria-live="polite"
                         class="mb-3">
                        <div class="text-muted mb-2" style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Suggested Questions</div>
                        <div class="ask-ai-chip-wrap">
                            @foreach($__solAiSuggestions as $__sq)
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
                    <textarea class="form-control" rows="4" id="solAiTextarea"
                              placeholder="What would you like to know?"
                              maxlength="1000"></textarea>
                    <div id="solAiResult" style="display:none;margin-top:1rem;"></div>
                </div>
                <div class="modal-footer border-0 pb-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="solAiSubmitBtn">
                        <span id="solAiSubmitSpinner" class="spinner-border spinner-border-sm me-1" role="status" style="display:none;"></span>
                        <i class="fa-solid fa-robot me-1" id="solAiSubmitIcon"></i><span id="solAiSubmitLabel">Ask AI</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>{{-- /container --}}

{{-- ===== MOBILE STICKY BOTTOM BAR ===== --}}
<div class="sol-mobile-bar d-lg-none">
    <form method="POST" action="{{ route('offers.store') }}">
        @csrf
        <input type="hidden" name="offer_auction_id" value="{{ $offerAuction->id }}">
        <input type="hidden" name="role" value="seller">
        <button type="submit" class="sol-mobile-bar-btn sol-mobile-bar-offer">
            <i class="fa-solid fa-file-signature"></i>
            <span>Offer</span>
        </button>
    </form>
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
    <button type="button" class="sol-mobile-bar-btn"
            data-bs-toggle="modal" data-bs-target="#solHireAgentModal">
        <i class="fa-solid fa-user-tie"></i>
        <span>Agent</span>
    </button>
    <a href="{{ route('offer.listing.seller.searchListing') }}" class="sol-mobile-bar-btn">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Search</span>
    </a>
</div>

{{-- ===== HIRE AGENT MODAL ===== --}}
<x-hire-agent-modal
    listing-id="{{ $auction->id }}"
    listing-type="seller_offer"
    listing-role="seller"
    :listing-title="($meta['listing_title'] ?? null) ?: ($auction->title ?? '')"
    prefill-prop-type="{{ $meta['property_type'] ?? '' }}"
    modal-id="solHireAgentModal"
/>

{{-- ===== REQUEST A SHOWING MODAL (authenticated users only) ===== --}}
@auth
@if(auth()->id() != $auction->user_id && !(isset($meta['hired_agent_id']) && (int)$meta['hired_agent_id'] === (int)auth()->id()))
<div class="modal fade" id="solShowingRequestModal" tabindex="-1" aria-labelledby="solShowingRequestModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content" style="border-radius:.85rem;overflow:hidden;border:none;">
            <div class="modal-header sol-modal-header">
                <h5 class="modal-title fw-bold" id="solShowingRequestModalLabel">
                    <i class="fa-solid fa-calendar-plus me-2"></i>Request a Showing
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1);"></button>
            </div>
            @include('showings._request-form', ['auctionId' => $auction->id])
        </div>
    </div>
</div>
@endif
@endauth

@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    /* ---- Bidding period countdown timer ---- */
    (function () {
        function solBpFormat(s) {
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
        document.querySelectorAll('.sol-bp-timer[data-seconds]').forEach(function (el) {
            var secs = parseInt(el.getAttribute('data-seconds'), 10) || 0;
            el.textContent = solBpFormat(secs);
            if (secs <= 0) { el.classList.replace('bg-info', 'bg-secondary'); return; }
            var iv = setInterval(function () {
                secs--;
                el.textContent = solBpFormat(secs);
                if (secs <= 0) {
                    clearInterval(iv);
                    el.classList.remove('bg-info', 'text-dark');
                    el.classList.add('bg-secondary');
                }
            }, 1000);
        });
    }());

    /* ---- Smooth-scroll + active-section highlighting ---- */
    var SOL_OFFSET = 82;
    var solNavLinks = Array.from(document.querySelectorAll('#solNavTabs a[href^="#"]'));
    var solSections  = solNavLinks.map(function (a) { return document.querySelector(a.getAttribute('href')); }).filter(Boolean);
    solSections.sort(function (a, b) { return a.offsetTop - b.offsetTop; });

    solNavLinks.forEach(function (a) {
        a.addEventListener('click', function (e) {
            var target = document.querySelector(a.getAttribute('href'));
            if (!target) return;
            e.preventDefault();
            var top = target.getBoundingClientRect().top + window.scrollY - SOL_OFFSET;
            window.scrollTo({ top: top, behavior: 'smooth' });
        });
    });

    function solOnScroll() {
        var scrollY = window.scrollY + SOL_OFFSET + 10;
        var active = null;
        solSections.forEach(function (s) {
            if (s && s.offsetTop <= scrollY) active = s;
        });
        solNavLinks.forEach(function (a) { a.classList.remove('sol-nav-active'); });
        if (active) {
            var link = document.querySelector('#solNavTabs a[href="#' + active.id + '"]');
            if (link) link.classList.add('sol-nav-active');
        }
    }
    window.addEventListener('scroll', solOnScroll, { passive: true });
    solOnScroll();

    /* ---- Auto-reopen modal after validation failure ---- */
    @if(session('open_modal'))
    (function () {
        var modalId = '{{ session('open_modal') }}' === 'question' ? 'solQuestionModal' : 'solShowingModal';
        var el = document.getElementById(modalId);
        if (el && typeof bootstrap !== 'undefined') {
            var m = bootstrap.Modal.getOrCreate(el);
            m.show();
        }
    }());
    @endif

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

    /* ---- Share listing (Web Share API with clipboard fallback, clipboard-guarded) ---- */
    function shareHandler() {
        var url = window.location.href;
        if (navigator.share) {
            navigator.share({ title: document.title, url: url }).catch(function () {});
        } else if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function () {
                alert('Link copied to clipboard!');
            }).catch(function () {
                alert('Share: ' + url);
            });
        } else {
            alert('Share: ' + url);
        }
    }
    var solShareBtn = document.getElementById('solShareBtn');
    if (solShareBtn) solShareBtn.addEventListener('click', shareHandler);
    var solMobileShareBtn = document.getElementById('solMobileShareBtn');
    if (solMobileShareBtn) solMobileShareBtn.addEventListener('click', shareHandler);

    /* ---- Hub: Copy Link and Native Share — both reuse shareHandler ---- */
    var solHubCopyBtn = document.getElementById('solHubCopyBtn');
    if (solHubCopyBtn) solHubCopyBtn.addEventListener('click', shareHandler);
    var solHubNativeShareBtn = document.getElementById('solHubNativeShareBtn');
    if (solHubNativeShareBtn && navigator.share) {
        solHubNativeShareBtn.style.display = '';
        solHubNativeShareBtn.addEventListener('click', shareHandler);
    }

    /* ---- Hero CTAs: scroll hub into view, then open modal after animation ---- */
    function openModal(targetSelector) {
        var el = document.querySelector(targetSelector);
        if (!el) return;
        var Modal = window.bootstrap && window.bootstrap.Modal;
        if (Modal) {
            Modal.getOrCreateInstance(el).show();
        }
    }
    document.querySelectorAll('.sol-hero-cta-btn[data-sol-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = this.getAttribute('data-sol-modal');
            var hub = document.getElementById('sol-interaction-hub');
            if (!hub) { openModal(target); return; }
            var offset = 82;
            var hubTop = hub.getBoundingClientRect().top + window.scrollY - offset;
            var alreadyVisible = hub.getBoundingClientRect().top >= 0 &&
                                 hub.getBoundingClientRect().top <= window.innerHeight;
            if (alreadyVisible) {
                openModal(target);
            } else {
                window.scrollTo({ top: hubTop, behavior: 'smooth' });
                setTimeout(function () { openModal(target); }, 420);
            }
        });
    });

    /* ---- Ask AI modal — live submit ---- */
    (function () {
        var submitBtn  = document.getElementById('solAiSubmitBtn');
        var textarea   = document.getElementById('solAiTextarea');
        var resultDiv  = document.getElementById('solAiResult');
        var spinner    = document.getElementById('solAiSubmitSpinner');
        var icon       = document.getElementById('solAiSubmitIcon');
        var label      = document.getElementById('solAiSubmitLabel');
        var modalEl    = document.getElementById('solAiModal');
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
                    html += '<div class="ask-ai-chip-wrap" id="solAiFollowUpChips">';
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

        function escHtml(str) {
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
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
                    body: JSON.stringify({ listing_type: 'seller', listing_id: listingId, question: question })
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

    /* ---- Ask AI Suggestion Chips ---- */
    (function () {
        var STORAGE_KEY = 'askAiUsedQuestions';
        var modalEl   = document.getElementById('solAiModal');
        var chipsWrap = document.getElementById('solAiSuggestions');
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
                var ta = document.getElementById('solAiTextarea');
                if (ta) { ta.value = q; ta.focus(); }
                markUsed(q);
                btn.style.display = 'none';
            });
            btn.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); btn.click(); }
            });
        });
    }());

})();
</script>
@endpush
