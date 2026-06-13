<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>Offer Detail — #{{ $terminalLeaf->id }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 11px; color: #222; line-height: 1.5; padding: 30px 36px; }
    h1 { font-size: 17px; font-weight: bold; margin-bottom: 4px; }
    h2 { font-size: 13px; font-weight: bold; margin: 18px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
    h3 { font-size: 11px; font-weight: bold; color: #555; text-transform: uppercase; letter-spacing: .03em; margin: 12px 0 4px; }
    .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 16px; }
    .meta-row { display: block; overflow: hidden; margin-bottom: 2px; }
    .meta-label { display: inline-block; width: 210px; font-weight: bold; color: #444; vertical-align: top; }
    .meta-value { display: inline-block; width: calc(100% - 215px); vertical-align: top; }
    .status-badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: .05em; }
    .badge-accepted  { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
    .badge-rejected  { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .badge-withdrawn { background: #e5e7eb; color: #1f2937; border: 1px solid #9ca3af; }
    .badge-expired   { background: #f3f4f6; color: #6b7280; border: 1px solid #d1d5db; }
    .badge-cancelled { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .outcome-banner { padding: 10px 14px; border-radius: 4px; margin-bottom: 16px; }
    .banner-accepted  { background: #d1fae5; border: 1px solid #6ee7b7; }
    .banner-rejected  { background: #fee2e2; border: 1px solid #fca5a5; }
    .banner-withdrawn { background: #e5e7eb; border: 1px solid #9ca3af; }
    .banner-expired   { background: #f3f4f6; border: 1px solid #d1d5db; }
    .banner-cancelled { background: #fee2e2; border: 1px solid #fca5a5; }
    .banner-title { font-size: 14px; font-weight: bold; margin-bottom: 2px; }
    .section-box { border: 1px solid #ddd; border-radius: 3px; margin-bottom: 14px; }
    .section-header { background: #f5f5f5; padding: 5px 10px; font-weight: bold; font-size: 11px; border-bottom: 1px solid #ddd; }
    .section-body { padding: 8px 10px; }
    .chain-row { display: block; overflow: hidden; padding: 3px 0; border-bottom: 1px solid #eee; }
    .chain-row:last-child { border-bottom: none; }
    .footer { margin-top: 24px; border-top: 1px solid #ccc; padding-top: 8px; font-size: 9px; color: #888; }
    .notice { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 3px; padding: 8px 10px; color: #555; }
    .terms-section-header { font-size: 10px; font-weight: bold; color: #555; text-transform: uppercase; letter-spacing: .03em; margin: 10px 0 4px; border-bottom: 1px solid #eee; padding-bottom: 2px; }
    /* Bootstrap dl/dt/dd grid overrides for dompdf */
    p.fw-semibold { font-weight: bold; font-size: 10px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.03em; border-bottom: 1px solid #eee; padding-bottom: 2px; margin: 10px 0 4px; }
    dl.row { display: block; overflow: hidden; margin-bottom: 4px; }
    dl.row dt, dl.row dd { display: inline-block; vertical-align: top; margin: 0 0 2px 0; padding: 0; }
    dl.row dt.col-sm-3 { width: 28%; font-weight: bold; color: #444; }
    dl.row dd.col-sm-9 { width: 70%; }
    dl.row dt.col-sm-12 { display: block; width: 100%; }
    .mb-0 { margin-bottom: 0; }
    .mb-1 { margin-bottom: 4px; }
    .mt-2 { margin-top: 8px; }
    .mt-3 { margin-top: 12px; }
</style>
</head>
<body>

@php
    $fmtDateTime = function ($v) {
        if (!$v) return '—';
        try { return \Carbon\Carbon::parse($v)->format('F j, Y \a\t g:i A'); }
        catch (\Throwable $e) { return '—'; }
    };
    $fmtDate = function ($v) {
        if (!$v) return '—';
        try { return \Carbon\Carbon::parse($v)->format('F j, Y'); }
        catch (\Throwable $e) { return '—'; }
    };
    $fmtMoney = function ($v) {
        if ($v === null || $v === '') return '—';
        $clean = str_replace(',', '', (string) $v);
        return is_numeric($clean) ? '$' . number_format((float) $clean) : (string) $v;
    };
    $statusLabels = [
        'accepted'  => 'Offer Accepted',
        'rejected'  => 'Offer Rejected',
        'withdrawn' => 'Offer Withdrawn',
        'expired'   => 'Offer Expired',
        'cancelled' => 'Offer Cancelled',
    ];
    $statusLabel  = $statusLabels[$terminalLeaf->status] ?? ucfirst($terminalLeaf->status);
    $bannerClass  = 'banner-' . $terminalLeaf->status;
    $badgeClass   = 'badge-' . $terminalLeaf->status;
    $terminalHeadings = [
        'accepted'  => 'Accepted Offer Terms',
        'rejected'  => 'Rejected Offer Terms',
        'withdrawn' => 'Withdrawn Offer Terms',
        'expired'   => 'Expired Offer Terms',
        'cancelled' => 'Cancelled Offer Terms',
    ];
    $termsHeading = $terminalHeadings[$terminalLeaf->status] ?? 'Offer Terms at Conclusion';
    $chainRoot    = $chainCollection->first();
    $chainRef     = $chainRoot ? 'Chain starting at Offer #' . $chainRoot->id : '—';
@endphp

{{-- ── Header ── --}}
<div class="header">
    <h1>Offer Detail</h1>
    <span style="font-size:10px;color:#666;">Generated {{ now()->format('F j, Y \a\t g:i A') }} &nbsp;&bull;&nbsp; Bid Your Offer Platform</span>
</div>

{{-- ── Offer Information ── --}}
<div class="section-box">
    <div class="section-header">Offer Information</div>
    <div class="section-body">
        <div class="meta-row">
            <span class="meta-label">Offer ID</span>
            <span class="meta-value">#{{ $terminalLeaf->id }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-label">Status</span>
            <span class="meta-value">
                <span class="status-badge {{ $badgeClass }}">{{ $terminalLeaf->status }}</span>
            </span>
        </div>
        <div class="meta-row">
            <span class="meta-label">Negotiation Chain Reference</span>
            <span class="meta-value">{{ $chainRef }}</span>
        </div>
        @if($terminalLeaf->parent_offer_id)
        <div class="meta-row">
            <span class="meta-label">Parent Offer ID</span>
            <span class="meta-value">#{{ $terminalLeaf->parent_offer_id }}</span>
        </div>
        @endif
        <div class="meta-row">
            <span class="meta-label">Offer Type</span>
            <span class="meta-value">{{ ucfirst($offerType) }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-label">Created At</span>
            <span class="meta-value">{{ $fmtDateTime($terminalLeaf->created_at) }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-label">Submitted At</span>
            <span class="meta-value">{{ $fmtDateTime($terminalLeaf->submitted_at) }}</span>
        </div>
    </div>
</div>

{{-- ── Status Banner ── --}}
<div class="outcome-banner {{ $bannerClass }}">
    <div class="banner-title">{{ $statusLabel }}</div>
    <div style="font-size:10px;color:#444;">{{ $fmtDateTime($terminalOutcomeAt) }}</div>
</div>

{{-- ── Terms Snapshot ── --}}
<div class="section-box">
    <div class="section-header">{{ $termsHeading }}</div>
    <div class="section-body">
        @if($snapshotMissing)
        <div class="notice">
            <strong>Terms not available.</strong>
            No terms were recorded for this offer. This may occur for offers that were resolved before any terms were entered.
        </div>
        @elseif($finalTerms->isEmpty())
        <div class="notice">No terms data found for this offer.</div>
        @else

        @include('offers._offer_terms_display', ['metas' => $finalTerms, 'offerType' => $offerType])

        @endif
    </div>
</div>

{{-- ── Negotiation Chain ── --}}
@if($chainCollection->count() > 1)
<div class="section-box">
    <div class="section-header">Negotiation Chain</div>
    <div class="section-body">
        @foreach($chainCollection as $chainOffer)
        <div class="chain-row">
            @if(!$loop->first)<span style="color:#999;margin-right:4px;">↓</span>@endif
            <strong>Offer #{{ $chainOffer->id }}</strong>
            &nbsp;&mdash;&nbsp;
            <span class="status-badge badge-{{ $chainOffer->status }}">{{ $chainOffer->status }}</span>
            &nbsp;&mdash;&nbsp;
            <span style="color:#666;">{{ $chainOffer->created_at?->format('M j, Y g:i A') }}</span>
            @if($chainOffer->id === $terminalLeaf->id)
                <em style="color:#666;margin-left:6px;">(this offer)</em>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ── Footer ── --}}
<div class="footer">
    Offer #{{ $terminalLeaf->id }} &bull; {{ $statusLabel }} on {{ $fmtDateTime($terminalOutcomeAt) }} &bull; Generated by Bid Your Offer
</div>

</body>
</html>
