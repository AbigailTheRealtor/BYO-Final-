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

{{-- ── Property Being Offered (buyer/tenant role only) ── --}}
@php
    $_pRole = $terminalLeaf->role ?? ($rootOffer->role ?? '');
    $_hasPropData = in_array($_pRole, ['buyer', 'tenant']) && isset($rootMetas) &&
        ($rootMetas->get('prop_type') || $rootMetas->get('prop_street') || $rootMetas->get('prop_mls_number') || $rootMetas->get('match_explanation'));
@endphp
@if($_hasPropData)
<div class="section-box">
    <div class="section-header">Property Being Offered</div>
    <div class="section-body">
        @php
            $pm = $rootMetas;
            $fmtDatePdf = function ($v) {
                if (!$v) return '—';
                try { return \Carbon\Carbon::parse($v)->format('F j, Y'); }
                catch (\Throwable $e) { return '—'; }
            };
        @endphp
        @if($pm->get('prop_street') || $pm->get('prop_city') || $pm->get('prop_state'))
        <h3>Property Address</h3>
        @if($pm->get('prop_street'))
        <div class="meta-row">
            <span class="meta-label">Street</span>
            <span class="meta-value">{{ $pm->get('prop_street') }}</span>
        </div>
        @endif
        @if($pm->get('prop_city') || $pm->get('prop_state') || $pm->get('prop_zip'))
        <div class="meta-row">
            <span class="meta-label">City / State / ZIP</span>
            <span class="meta-value">{{ implode(', ', array_filter([$pm->get('prop_city'), $pm->get('prop_state'), $pm->get('prop_zip')])) }}</span>
        </div>
        @endif
        @endif

        @if($pm->get('prop_type') || $pm->get('prop_subtype') || $pm->get('prop_listing_status') || $pm->get('prop_mls_number') || $pm->get('prop_listing_url'))
        <h3>Property Identification</h3>
        @if($pm->get('prop_type'))
        <div class="meta-row">
            <span class="meta-label">Property Type</span>
            <span class="meta-value">{{ $pm->get('prop_type') }}</span>
        </div>
        @endif
        @if($pm->get('prop_subtype'))
        <div class="meta-row">
            <span class="meta-label">Style / Subtype</span>
            <span class="meta-value">{{ $pm->get('prop_subtype') }}</span>
        </div>
        @endif
        @if($pm->get('prop_listing_status'))
        <div class="meta-row">
            <span class="meta-label">Listing Status</span>
            <span class="meta-value">{{ $pm->get('prop_listing_status') }}</span>
        </div>
        @endif
        @if($pm->get('prop_mls_number'))
        <div class="meta-row">
            <span class="meta-label">MLS #</span>
            <span class="meta-value">{{ $pm->get('prop_mls_number') }}</span>
        </div>
        @endif
        @if($pm->get('prop_listing_url'))
        <div class="meta-row">
            <span class="meta-label">Listing URL</span>
            <span class="meta-value">{{ $pm->get('prop_listing_url') }}</span>
        </div>
        @endif
        @endif

        @php
            $pdfAttrBedrooms  = $pm->get('prop_attr_bedrooms') === 'Other' && $pm->get('prop_attr_other_bedrooms')
                ? $pm->get('prop_attr_other_bedrooms') : $pm->get('prop_attr_bedrooms');
            $pdfAttrBathrooms = $pm->get('prop_attr_bathrooms') === 'Other' && $pm->get('prop_attr_other_bathrooms')
                ? $pm->get('prop_attr_other_bathrooms') : $pm->get('prop_attr_bathrooms');
            $pdfPoolTypes = array_filter([
                $pm->get('prop_attr_pool_private')  ? 'Private'   : null,
                $pm->get('prop_attr_pool_community') ? 'Community' : null,
            ]);
        @endphp
        @if($pm->get('prop_attr_condition') || $pdfAttrBedrooms || $pdfAttrBathrooms || $pm->get('prop_attr_heated_sqft') || $pm->get('prop_attr_total_sqft') || $pm->get('prop_attr_total_acreage') || $pm->get('prop_attr_garage') || $pm->get('prop_attr_pool') || $pm->get('prop_attr_year_built') || $pm->get('prop_attr_zoning'))
        <h3>Property Attributes</h3>
        @if($pm->get('prop_attr_condition'))
        <div class="meta-row">
            <span class="meta-label">Property Condition</span>
            <span class="meta-value">{{ $pm->get('prop_attr_condition') }}</span>
        </div>
        @endif
        @if($pdfAttrBedrooms)
        <div class="meta-row">
            <span class="meta-label">Bedrooms</span>
            <span class="meta-value">{{ $pdfAttrBedrooms }}</span>
        </div>
        @endif
        @if($pdfAttrBathrooms)
        <div class="meta-row">
            <span class="meta-label">Bathrooms</span>
            <span class="meta-value">{{ $pdfAttrBathrooms }}</span>
        </div>
        @endif
        @if($pm->get('prop_attr_heated_sqft'))
        <div class="meta-row">
            <span class="meta-label">Heated SqFt</span>
            <span class="meta-value">{{ $pm->get('prop_attr_heated_sqft') }}</span>
        </div>
        @endif
        @if($pm->get('prop_attr_net_leasable_sqft'))
        <div class="meta-row">
            <span class="meta-label">Net Leasable SqFt</span>
            <span class="meta-value">{{ $pm->get('prop_attr_net_leasable_sqft') }}</span>
        </div>
        @endif
        @if($pm->get('prop_attr_total_sqft'))
        <div class="meta-row">
            <span class="meta-label">Total SqFt</span>
            <span class="meta-value">{{ $pm->get('prop_attr_total_sqft') }}</span>
        </div>
        @endif
        @if($pm->get('prop_attr_sqft_source'))
        <div class="meta-row">
            <span class="meta-label">SqFt Source</span>
            <span class="meta-value">{{ $pm->get('prop_attr_sqft_source') }}</span>
        </div>
        @endif
        @if($pm->get('prop_attr_total_acreage'))
        <div class="meta-row">
            <span class="meta-label">Total Acreage</span>
            <span class="meta-value">{{ $pm->get('prop_attr_total_acreage') }}</span>
        </div>
        @endif
        @if($pm->get('prop_attr_garage'))
        <div class="meta-row">
            <span class="meta-label">Garage</span>
            <span class="meta-value">{{ $pm->get('prop_attr_garage') }}{{ $pm->get('prop_attr_garage_spaces') ? ' (' . $pm->get('prop_attr_garage_spaces') . ' spaces)' : '' }}</span>
        </div>
        @endif
        @if($pm->get('prop_attr_pool'))
        <div class="meta-row">
            <span class="meta-label">Pool</span>
            <span class="meta-value">{{ $pm->get('prop_attr_pool') }}{{ count($pdfPoolTypes) ? ' — ' . implode(', ', $pdfPoolTypes) : '' }}</span>
        </div>
        @endif
        @if($pm->get('prop_attr_year_built'))
        <div class="meta-row">
            <span class="meta-label">Year Built</span>
            <span class="meta-value">{{ $pm->get('prop_attr_year_built') }}</span>
        </div>
        @endif
        @if($pm->get('prop_attr_zoning'))
        <div class="meta-row">
            <span class="meta-label">Zoning</span>
            <span class="meta-value">{{ $pm->get('prop_attr_zoning') }}</span>
        </div>
        @endif
        @endif

        @if($pm->get('prop_virtual_tour_url') || $pm->get('prop_video_url'))
        <h3>Media Links</h3>
        @if($pm->get('prop_virtual_tour_url'))
        <div class="meta-row">
            <span class="meta-label">Virtual Tour</span>
            <span class="meta-value">{{ $pm->get('prop_virtual_tour_url') }}</span>
        </div>
        @endif
        @if($pm->get('prop_video_url'))
        <div class="meta-row">
            <span class="meta-label">Video</span>
            <span class="meta-value">{{ $pm->get('prop_video_url') }}</span>
        </div>
        @endif
        @endif

        @if($pm->get('prop_available_date') || $pm->get('prop_occupancy_status') || $pm->get('prop_showing_availability'))
        <h3>Availability</h3>
        @if($pm->get('prop_available_date'))
        <div class="meta-row">
            <span class="meta-label">Available Date</span>
            <span class="meta-value">{{ $fmtDatePdf($pm->get('prop_available_date')) }}</span>
        </div>
        @endif
        @if($pm->get('prop_occupancy_status'))
        <div class="meta-row">
            <span class="meta-label">Occupancy Status</span>
            <span class="meta-value">{{ $pm->get('prop_occupancy_status') }}</span>
        </div>
        @endif
        @if($pm->get('prop_showing_availability'))
        <div class="meta-row">
            <span class="meta-label">Showing Availability</span>
            <span class="meta-value">{{ $pm->get('prop_showing_availability') }}</span>
        </div>
        @endif
        @endif

        @if($pm->get('match_explanation') || $pm->get('match_compromise_note'))
        <h3>Match Explanation</h3>
        @if($pm->get('match_explanation'))
        <div class="meta-row">
            <span class="meta-label">Why It Matches</span>
            <span class="meta-value" style="white-space:pre-wrap;">{{ $pm->get('match_explanation') }}</span>
        </div>
        @endif
        @if($pm->get('match_compromise_note'))
        <div class="meta-row">
            <span class="meta-label">Compromises / Notes</span>
            <span class="meta-value" style="white-space:pre-wrap;">{{ $pm->get('match_compromise_note') }}</span>
        </div>
        @endif
        @endif
    </div>
</div>
@endif

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
