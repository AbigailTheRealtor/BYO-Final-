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

        @php
            $skipKeys = ['offer_type', 'accepted_terms_snapshot'];
            $fieldLabels = [
                'offer_price'                       => 'Offer Price',
                'earnest_deposit'                   => 'Earnest Deposit',
                'earnest_deposit_unit'              => 'Earnest Deposit Unit',
                'financing_type'                    => 'Financing Type',
                'down_payment_value'                => 'Down Payment',
                'down_payment_unit'                 => 'Down Payment Unit',
                'financing_contingency'             => 'Financing Contingency',
                'financing_contingency_days'        => 'Financing Contingency Days',
                'inspection_contingency'            => 'Inspection Contingency',
                'inspection_contingency_days'       => 'Inspection Contingency Days',
                'appraisal_contingency'             => 'Appraisal Contingency',
                'appraisal_contingency_days'        => 'Appraisal Contingency Days',
                'sale_of_buyer_property_contingency'      => 'Sale of Buyer Property Contingency',
                'sale_of_buyer_property_contingency_days' => 'Sale Contingency Days',
                'closing_date'                      => 'Closing Date',
                'possession_date'                   => 'Possession Date',
                'possession_notes'                  => 'Possession Notes',
                'expires_at'                        => 'Response Requested By',
                'seller_contribution_requested'     => 'Seller Contribution Requested',
                'seller_contribution_details'       => 'Seller Contribution Details',
                'home_warranty_requested'           => 'Home Warranty Requested',
                'home_warranty_details'             => 'Home Warranty Details',
                'included_personal_property'        => 'Included Personal Property',
                'excluded_items'                    => 'Excluded Items',
                'custom_terms'                      => 'Custom Terms',
                'notes'                             => 'Notes',
                // Rental/lease
                'monthly_rent'                      => 'Monthly Rent',
                'lease_term_months'                 => 'Lease Term (months)',
                'security_deposit'                  => 'Security Deposit',
                'last_month_rent_offered'           => 'Last Month Rent Offered',
                'move_in_funds'                     => 'Move-In Funds',
                'move_in_date'                      => 'Move-In Date',
                'utilities_terms'                   => 'Utilities',
                'maintenance_responsibility'        => 'Maintenance Responsibility',
                'parking_terms'                     => 'Parking Terms',
                'additional_lease_terms'            => 'Additional Lease Terms',
                'num_occupants'                     => 'Number of Occupants',
                'has_pets'                          => 'Pets',
                'pet_details'                       => 'Pet Details',
                'smoking_preference'                => 'Smoking',
                'monthly_income'                    => 'Est. Monthly Income',
                'credit_score_range'                => 'Credit Score Range',
                'screening_notes'                   => 'About Applicant',
                'screening_concerns'                => 'Rental History Disclosure',
                'screening_concerns_details'        => 'Disclosure Details',
                'message_to_landlord'               => 'Message to Landlord',
                // Financing sub-fields
                'assumable_interest'                => 'Assumable Interest',
                'assumable_max_interest_rate'       => 'Max Assumable Rate',
                'assumable_max_monthly_payment'     => 'Max Monthly Payment (Assumable)',
                'assumable_bridge_gap_cash'         => 'Bridge Gap Cash',
                'cryptocurrency_type'               => 'Cryptocurrency Type',
                'crypto_percentage'                 => '% Paid with Crypto',
                'crypto_exchange_method'            => 'Crypto Exchange Method',
                'exchange_item'                     => 'Exchange Item',
                'other_exchange_item'               => 'Exchange Item (Other)',
                'exchange_item_value'               => 'Exchange Item Value',
                'exchange_item_condition'           => 'Exchange Item Condition',
                'additional_cash'                   => 'Additional Cash',
                'value_determination'               => 'Value Determined By',
                'exchange_transfer_method'          => 'Exchange Transfer Method',
                'exchange_liens'                    => 'Exchange Liens',
                'exchange_liens_details'            => 'Exchange Lien Details',
                'exchange_inspection_rights'        => 'Exchange Inspection Rights',
                'sf_purchase_price'                 => 'SF Desired Purchase Price',
                'sf_down_payment_amount'            => 'SF Down Payment',
                'sf_down_payment_type'              => 'SF Down Payment Type',
                'seller_financing_amount'           => 'SF Financing Amount',
                'seller_financing_amount_type'      => 'SF Financing Amount Type',
                'seller_financing_rate'             => 'SF Interest Rate',
                'seller_financing_term'             => 'SF Loan Term',
                'seller_financing_amortization'     => 'SF Amortization',
                'seller_financing_payment_frequency'=> 'SF Payment Frequency',
                'seller_financing_balloon'          => 'SF Balloon Payment',
                'seller_financing_balloon_amount'   => 'SF Balloon Amount',
                'seller_financing_balloon_date'     => 'SF Balloon Due Date',
                'prepayment_penalty'                => 'Prepayment Penalty',
                'prepayment_penalty_amount'         => 'Prepayment Penalty Amount',
                'seller_late_fee_amount'            => 'Late Payment Fee',
                'lease_option_price'                => 'Lease Option Price',
                'lease_option_payment'              => 'Lease Option Monthly Payment',
                'lease_option_duration'             => 'Lease Option Duration (months)',
                'has_option_fee'                    => 'Option Fee',
                'option_fee_amount'                 => 'Option Fee Amount',
                'lease_option_fee_credit'           => 'Option Fee Credit',
                'lease_option_maintenance'          => 'Lease Option Maintenance',
                'lease_purchase_price'              => 'Lease Purchase Price',
                'lease_purchase_payment'            => 'Lease Purchase Monthly Payment',
                'lease_purchase_duration'           => 'Lease Purchase Duration (months)',
                'lease_purchase_rent_credit'        => 'Lease Purchase Rent Credit',
                'lease_purchase_deposit'            => 'Lease Purchase Non-Refundable Deposit',
                'lease_purchase_maintenance'        => 'Lease Purchase Maintenance',
                'nft_description'                   => 'NFT Description',
                'nft_percentage'                    => '% Paid with NFT',
                'cash_percentage_nft'               => '% Paid with Cash (NFT)',
                'nft_valuation_method'              => 'NFT Valuation Method',
                'nft_transfer_method'               => 'NFT Transfer Method',
                'nft_gas_fees'                      => 'NFT Gas Fees',
                'other_financing_details'           => 'Other Financing Details',
                'initial_deposit_amount'            => 'Initial Deposit Amount',
                'initial_deposit_timeframe'         => 'Initial Deposit Timeframe',
                'additional_deposit_amount'         => 'Additional Deposit Amount',
                'additional_deposit_timeframe'      => 'Additional Deposit Timeframe',
            ];
            $moneyKeys = [
                'offer_price','earnest_deposit','down_payment_value','additional_cash',
                'exchange_item_value','sf_purchase_price','sf_down_payment_amount',
                'seller_financing_amount','seller_financing_balloon_amount',
                'prepayment_penalty_amount','option_fee_amount','lease_option_price',
                'lease_option_payment','lease_purchase_price','lease_purchase_payment',
                'lease_purchase_deposit','lease_purchase_rent_credit_amount',
                'initial_deposit_amount','additional_deposit_amount',
                'assumable_max_monthly_payment','assumable_bridge_gap_cash',
                'monthly_rent','security_deposit','move_in_funds','monthly_income',
            ];
        @endphp

        @foreach($finalTerms as $key => $value)
            @if(in_array($key, $skipKeys)) @continue @endif
            @if($value === null || $value === '') @continue @endif
            @php
                $label = $fieldLabels[$key] ?? ucwords(str_replace('_', ' ', $key));
                $display = $value;
                if (in_array($key, $moneyKeys) && is_numeric(str_replace(',', '', (string) $value))) {
                    $display = '$' . number_format((float) str_replace(',', '', (string) $value));
                } elseif ($key === 'financing_contingency' || $key === 'inspection_contingency' || $key === 'appraisal_contingency' || $key === 'sale_of_buyer_property_contingency') {
                    $display = $value ? 'Yes' : 'No';
                } elseif (in_array($key, ['closing_date','possession_date','expires_at','move_in_date'])) {
                    $display = $fmtDate($value);
                } elseif ($key === 'seller_financing_rate' || $key === 'assumable_max_interest_rate' || $key === 'crypto_percentage' || $key === 'nft_percentage' || $key === 'cash_percentage_nft' || $key === 'lease_option_fee_credit_pct') {
                    $display = $value . '%';
                }
            @endphp
            <div class="meta-row">
                <span class="meta-label">{{ $label }}</span>
                <span class="meta-value" style="white-space:pre-wrap;">{{ $display }}</span>
            </div>
        @endforeach

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
