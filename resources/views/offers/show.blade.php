@extends('layouts.main')

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-12">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Offer Detail</h2>
                <button onclick="window.history.back()" class="btn btn-secondary btn-sm">Back</button>
            </div>

            {{-- Offer Summary Card --}}
            <div class="card mb-4">
                <div class="card-header">
                    <strong>Offer Information</strong>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Offer ID</dt>
                        <dd class="col-sm-9">{{ $offer->id }}</dd>

                        <dt class="col-sm-3">Status</dt>
                        <dd class="col-sm-9">
                            @php
                                $statusColors = [
                                    'draft'     => 'secondary',
                                    'submitted' => 'primary',
                                    'countered' => 'warning',
                                    'accepted'  => 'success',
                                    'rejected'  => 'danger',
                                    'withdrawn' => 'dark',
                                    'expired'   => 'secondary',
                                    'cancelled' => 'danger',
                                ];
                                $color = $statusColors[$offer->status] ?? 'secondary';
                            @endphp
                            <span class="badge bg-{{ $color }} text-capitalize">{{ $offer->status }}</span>
                        </dd>

                        @if($offer->parent_offer_id)
                        <dt class="col-sm-3">Parent Offer ID</dt>
                        <dd class="col-sm-9">{{ $offer->parent_offer_id }}</dd>
                        @endif

                        <dt class="col-sm-3">Created At</dt>
                        <dd class="col-sm-9">{{ $offer->created_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>

                        <dt class="col-sm-3">Submitted At</dt>
                        <dd class="col-sm-9">{{ $offer->submitted_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>
                    </dl>
                </div>
            </div>

            {{-- Flash Messages --}}
            @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif
            @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            {{-- Offer Terms Card --}}
            @php
                $offerStatus = $offer->status;
                $isOwner    = Auth::id() === $offer->user_id;
                $canEdit    = $isOwner && $offerStatus === 'draft';
                $safeDate   = function ($v) {
                    if (!$v) return '—';
                    try { return \Carbon\Carbon::parse($v)->format('Y-m-d'); }
                    catch (\Throwable $e) { return '—'; }
                };
            @endphp
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Offer Terms</strong>
                    @if($canEdit)
                        <span class="badge bg-success">Editable</span>
                    @else
                        <span class="badge bg-secondary">Locked</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($canEdit)
                    @include('offers._offer_terms_form', ['mode' => 'draft_terms', 'formData' => $metas])
                    @else
                    {{-- Read-only display --}}
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Offer Expires At</dt>
                        <dd class="col-sm-9">{{ $safeDate($metas->get('expires_at')) }}</dd>

                        @if($offerType === 'sale')
                        @php
                            $ftRaw = $metas->get('financing_type');
                            $ftLabel = $ftRaw ?: '—';
                        @endphp
                        <dt class="col-sm-3">Offer Price</dt>
                        <dd class="col-sm-9">{{ $metas->get('offer_price') ? '$' . number_format($metas->get('offer_price')) : '—' }}</dd>

                        <dt class="col-sm-3">Earnest Deposit</dt>
                        <dd class="col-sm-9">
                        @php
                            $_edVal  = $metas->get('earnest_deposit');
                            $_edUnit = $metas->get('earnest_deposit_unit') ?? '$';
                        @endphp
                        {{ $_edVal !== null && $_edVal !== '' ? ($_edUnit === '%' ? $_edVal . '%' : '$' . number_format($_edVal)) : '—' }}
                        </dd>

                        <dt class="col-sm-3">Financing Type</dt>
                        <dd class="col-sm-9">{{ $ftLabel }}</dd>

                        @if($ftRaw === 'Assumable')
                        <dt class="col-sm-3">Interested in Assumable Financing?</dt>
                        <dd class="col-sm-9">{{ $metas->get('assumable_interest') ?: '—' }}</dd>
                        @if($metas->get('assumable_interest') === 'Yes')
                        <dt class="col-sm-3">Max Interest Rate Would Accept</dt>
                        <dd class="col-sm-9">{{ $metas->get('assumable_max_interest_rate') !== null && $metas->get('assumable_max_interest_rate') !== '' ? $metas->get('assumable_max_interest_rate') . '%' : '—' }}</dd>
                        <dt class="col-sm-3">Max Monthly Payment (P&I) Would Accept</dt>
                        <dd class="col-sm-9">{{ $metas->get('assumable_max_monthly_payment') ? '$' . number_format($metas->get('assumable_max_monthly_payment')) : '—' }}</dd>
                        <dt class="col-sm-3">Cash to Bridge the Gap</dt>
                        <dd class="col-sm-9">{{ $metas->get('assumable_bridge_gap_cash') ? '$' . number_format($metas->get('assumable_bridge_gap_cash')) : '—' }}</dd>
                        @endif
                        @endif

                        @if($ftRaw === 'Cryptocurrency')
                        <dt class="col-sm-3">Cryptocurrency Type</dt>
                        <dd class="col-sm-9">{{ $metas->get('cryptocurrency_type') ?: '—' }}</dd>
                        <dt class="col-sm-3">% Paid with Crypto</dt>
                        <dd class="col-sm-9">{{ $metas->get('crypto_percentage') !== null && $metas->get('crypto_percentage') !== '' ? $metas->get('crypto_percentage') . '%' : '—' }}</dd>
                        <dt class="col-sm-3">Exchange/Conversion Method</dt>
                        <dd class="col-sm-9">{{ $metas->get('crypto_exchange_method') ?: '—' }}</dd>
                        @endif

                        @if($ftRaw === 'Exchange/Trade')
                        @php
                            $_exItem = $metas->get('exchange_item');
                            $_exItemLabel = ($_exItem === 'Other' && $metas->get('other_exchange_item')) ? 'Other — ' . $metas->get('other_exchange_item') : $_exItem;
                        @endphp
                        @if($_exItem) <dt class="col-sm-3">Exchange Item</dt><dd class="col-sm-9">{{ $_exItemLabel }}</dd> @endif
                        @if($metas->get('exchange_item_value')) <dt class="col-sm-3">Estimated Value</dt><dd class="col-sm-9">${{ number_format($metas->get('exchange_item_value')) }}</dd> @endif
                        @if($metas->get('exchange_item_condition')) <dt class="col-sm-3">Condition</dt><dd class="col-sm-9">{{ $metas->get('exchange_item_condition') }}</dd> @endif
                        @if($metas->get('additional_cash')) <dt class="col-sm-3">Additional Cash</dt><dd class="col-sm-9">${{ number_format($metas->get('additional_cash')) }}</dd> @endif
                        @if($metas->get('value_determination')) <dt class="col-sm-3">Value Determined By</dt><dd class="col-sm-9">{{ $metas->get('value_determination') }}</dd> @endif
                        @if($metas->get('exchange_transfer_method')) <dt class="col-sm-3">Transfer Method</dt><dd class="col-sm-9">{{ $metas->get('exchange_transfer_method') }}</dd> @endif
                        @if($metas->get('exchange_liens')) <dt class="col-sm-3">Liens / Encumbrances</dt><dd class="col-sm-9">{{ $metas->get('exchange_liens') }}{{ $metas->get('exchange_liens') === 'Yes' && $metas->get('exchange_liens_details') ? ' — ' . $metas->get('exchange_liens_details') : '' }}</dd> @endif
                        @if($metas->get('exchange_inspection_rights')) <dt class="col-sm-3">Inspection Rights</dt><dd class="col-sm-9">{{ $metas->get('exchange_inspection_rights') }}</dd> @endif
                        @endif

                        @if($ftRaw === 'Seller Financing')
                        @php
                            $_sfAmt     = $metas->get('seller_financing_amount');
                            $_sfAmtType = $metas->get('seller_financing_amount_type') ?? '$';
                            $_sfAmtFmt  = $_sfAmt !== null && $_sfAmt !== ''
                                ? ($_sfAmtType === '%' ? $_sfAmt . '%' : '$' . number_format($_sfAmt))
                                : null;
                            $_sfDpAmt  = $metas->get('sf_down_payment_amount');
                            $_sfDpType = $metas->get('sf_down_payment_type') ?? '$';
                            $_sfDpFmt  = $_sfDpAmt !== null && $_sfDpAmt !== ''
                                ? ($_sfDpType === '%' ? $_sfDpAmt . '%' : '$' . number_format($_sfDpAmt))
                                : null;
                        @endphp
                        @if($metas->get('sf_purchase_price')) <dt class="col-sm-3">Desired Purchase Price</dt><dd class="col-sm-9">${{ number_format($metas->get('sf_purchase_price')) }}</dd> @endif
                        @if($_sfDpFmt) <dt class="col-sm-3">Desired Down Payment</dt><dd class="col-sm-9">{{ $_sfDpFmt }}</dd> @endif
                        @if($_sfAmtFmt) <dt class="col-sm-3">Financing Amount</dt><dd class="col-sm-9">{{ $_sfAmtFmt }}</dd> @endif
                        @if($metas->get('seller_financing_rate') !== null && $metas->get('seller_financing_rate') !== '') <dt class="col-sm-3">Interest Rate</dt><dd class="col-sm-9">{{ $metas->get('seller_financing_rate') }}%</dd> @endif
                        @if($metas->get('seller_financing_term')) <dt class="col-sm-3">Loan Term</dt><dd class="col-sm-9">{{ $metas->get('seller_financing_term') }}</dd> @endif
                        @if($metas->get('seller_financing_amortization'))
                        <dt class="col-sm-3">Amortization Type</dt>
                        <dd class="col-sm-9">{{ $metas->get('seller_financing_amortization') === 'Other' ? ($metas->get('seller_financing_amortization_other') ?: 'Other') : $metas->get('seller_financing_amortization') }}</dd>
                        @endif
                        @if($metas->get('seller_financing_payment_frequency'))
                        <dt class="col-sm-3">Payment Frequency</dt>
                        <dd class="col-sm-9">{{ $metas->get('seller_financing_payment_frequency') === 'Other' ? ($metas->get('seller_financing_payment_frequency_other') ?: 'Other') : $metas->get('seller_financing_payment_frequency') }}</dd>
                        @endif
                        @if($metas->get('seller_financing_balloon') === 'Yes')
                        <dt class="col-sm-3">Balloon Payment</dt>
                        <dd class="col-sm-9">Yes{{ $metas->get('seller_financing_balloon_amount') ? ' — $' . number_format($metas->get('seller_financing_balloon_amount')) : '' }}{{ $metas->get('seller_financing_balloon_date') ? ' due ' . $metas->get('seller_financing_balloon_date') : '' }}</dd>
                        @endif
                        @if($metas->get('prepayment_penalty'))
                        <dt class="col-sm-3">Prepayment Penalty</dt>
                        <dd class="col-sm-9">{{ $metas->get('prepayment_penalty') }}{{ $metas->get('prepayment_penalty') === 'Yes' && $metas->get('prepayment_penalty_amount') ? ' — $' . number_format($metas->get('prepayment_penalty_amount')) : '' }}</dd>
                        @endif
                        @if($metas->get('seller_late_fee_amount')) <dt class="col-sm-3">Late Payment Fee</dt><dd class="col-sm-9">{{ $metas->get('seller_late_fee_amount') }}</dd> @endif
                        @endif

                        @if($ftRaw === 'Lease Option')
                        @if($metas->get('lease_option_price')) <dt class="col-sm-3">Lease Option Price</dt><dd class="col-sm-9">${{ number_format($metas->get('lease_option_price')) }}</dd> @endif
                        @if($metas->get('lease_option_payment')) <dt class="col-sm-3">Monthly Payment</dt><dd class="col-sm-9">${{ number_format($metas->get('lease_option_payment')) }}</dd> @endif
                        @if($metas->get('lease_option_duration')) <dt class="col-sm-3">Lease Duration</dt><dd class="col-sm-9">{{ $metas->get('lease_option_duration') }} months</dd> @endif
                        @if($metas->get('has_option_fee')) <dt class="col-sm-3">Option Fee</dt><dd class="col-sm-9">{{ $metas->get('has_option_fee') }}{{ $metas->get('has_option_fee') === 'Yes' && $metas->get('option_fee_amount') ? ' — $' . number_format($metas->get('option_fee_amount')) : '' }}</dd> @endif
                        @if($metas->get('lease_option_fee_credit')) <dt class="col-sm-3">Fee Credit Toward Price</dt><dd class="col-sm-9">{{ $metas->get('lease_option_fee_credit') }}{{ $metas->get('lease_option_fee_credit') === 'Partial' && $metas->get('lease_option_fee_credit_pct') !== null && $metas->get('lease_option_fee_credit_pct') !== '' ? ' — ' . $metas->get('lease_option_fee_credit_pct') . '%' : '' }}</dd> @endif
                        @if($metas->get('lease_option_maintenance')) <dt class="col-sm-3">Maintenance</dt><dd class="col-sm-9">{{ $metas->get('lease_option_maintenance') }}</dd> @endif
                        @if($metas->get('lease_option_conditions')) <dt class="col-sm-3">Conditions</dt><dd class="col-sm-9">{{ $metas->get('lease_option_conditions') }}</dd> @endif
                        @if($metas->get('lease_option_terms')) <dt class="col-sm-3">Specific Terms</dt><dd class="col-sm-9">{{ $metas->get('lease_option_terms') }}</dd> @endif
                        @if($metas->get('lease_option_extension_terms')) <dt class="col-sm-3">Extension Terms</dt><dd class="col-sm-9">{{ $metas->get('lease_option_extension_terms') }}</dd> @endif
                        @endif

                        @if($ftRaw === 'Lease Purchase')
                        @if($metas->get('lease_purchase_price')) <dt class="col-sm-3">Lease Purchase Price</dt><dd class="col-sm-9">${{ number_format($metas->get('lease_purchase_price')) }}</dd> @endif
                        @if($metas->get('lease_purchase_payment')) <dt class="col-sm-3">Monthly Payment</dt><dd class="col-sm-9">${{ number_format($metas->get('lease_purchase_payment')) }}</dd> @endif
                        @if($metas->get('lease_purchase_duration')) <dt class="col-sm-3">Lease Duration</dt><dd class="col-sm-9">{{ $metas->get('lease_purchase_duration') }} months</dd> @endif
                        @if($metas->get('lease_purchase_rent_credit')) <dt class="col-sm-3">Rent Credit</dt><dd class="col-sm-9">{{ $metas->get('lease_purchase_rent_credit') }}{{ in_array($metas->get('lease_purchase_rent_credit'), ['Yes','Partial']) && $metas->get('lease_purchase_rent_credit_amount') ? ' — $' . number_format($metas->get('lease_purchase_rent_credit_amount')) . '/mo' : '' }}</dd> @endif
                        @if($metas->get('lease_purchase_deposit')) <dt class="col-sm-3">Non-Refundable Deposit</dt><dd class="col-sm-9">${{ number_format($metas->get('lease_purchase_deposit')) }}</dd> @endif
                        @if($metas->get('lease_purchase_maintenance')) <dt class="col-sm-3">Maintenance</dt><dd class="col-sm-9">{{ $metas->get('lease_purchase_maintenance') }}</dd> @endif
                        @if($metas->get('lease_purchase_conditions')) <dt class="col-sm-3">Conditions</dt><dd class="col-sm-9">{{ $metas->get('lease_purchase_conditions') }}</dd> @endif
                        @if($metas->get('lease_purchase_terms')) <dt class="col-sm-3">Specific Terms</dt><dd class="col-sm-9">{{ $metas->get('lease_purchase_terms') }}</dd> @endif
                        @if($metas->get('lease_purchase_extension_terms')) <dt class="col-sm-3">Extension Terms</dt><dd class="col-sm-9">{{ $metas->get('lease_purchase_extension_terms') }}</dd> @endif
                        @endif

                        @if($ftRaw === 'Non-Fungible Token (NFT)')
                        @if($metas->get('nft_description')) <dt class="col-sm-3">NFT Description</dt><dd class="col-sm-9">{{ $metas->get('nft_description') }}</dd> @endif
                        @if($metas->get('nft_percentage') !== null && $metas->get('nft_percentage') !== '') <dt class="col-sm-3">% Paid with NFT</dt><dd class="col-sm-9">{{ $metas->get('nft_percentage') }}%</dd> @endif
                        @if($metas->get('cash_percentage_nft') !== null && $metas->get('cash_percentage_nft') !== '') <dt class="col-sm-3">% Paid with Cash</dt><dd class="col-sm-9">{{ $metas->get('cash_percentage_nft') }}%</dd> @endif
                        @if($metas->get('nft_valuation_method')) <dt class="col-sm-3">NFT Valuation Method</dt><dd class="col-sm-9">{{ $metas->get('nft_valuation_method') }}</dd> @endif
                        @if($metas->get('nft_transfer_method')) <dt class="col-sm-3">NFT Transfer Method</dt><dd class="col-sm-9">{{ $metas->get('nft_transfer_method') }}</dd> @endif
                        @if($metas->get('nft_gas_fees')) <dt class="col-sm-3">Gas Fees</dt><dd class="col-sm-9">{{ $metas->get('nft_gas_fees') }}</dd> @endif
                        @endif

                        @if($ftRaw === 'Other' && $metas->get('other_financing_details'))
                        <dt class="col-sm-3">Other Financing Details</dt>
                        <dd class="col-sm-9" style="white-space:pre-wrap;">{{ $metas->get('other_financing_details') }}</dd>
                        @endif

                        @php
                            $_dpRoVal  = $metas->get('down_payment_value') ?? $metas->get('down_payment_percent');
                            $_dpRoUnit = $metas->get('down_payment_unit') ?? ($metas->get('down_payment_percent') !== null ? '%' : '$');
                        @endphp
                        <dt class="col-sm-3">Down Payment</dt>
                        <dd class="col-sm-9">{{ $_dpRoVal !== null && $_dpRoVal !== '' ? ($_dpRoUnit === '%' ? $_dpRoVal . '%' : '$' . number_format($_dpRoVal)) : '—' }}</dd>

                        <dt class="col-sm-3">Financing Contingency</dt>
                        <dd class="col-sm-9">
                            {{ $metas->get('financing_contingency') ? 'Yes' : 'No' }}
                            @if($metas->get('financing_contingency') && $metas->get('financing_contingency_days'))
                                ({{ $metas->get('financing_contingency_days') }} days)
                            @endif
                        </dd>

                        <dt class="col-sm-3">Inspection Contingency</dt>
                        <dd class="col-sm-9">
                            {{ $metas->get('inspection_contingency') ? 'Yes' : 'No' }}
                            @if($metas->get('inspection_contingency') && $metas->get('inspection_contingency_days'))
                                ({{ $metas->get('inspection_contingency_days') }} days)
                            @endif
                        </dd>

                        <dt class="col-sm-3">Appraisal Contingency</dt>
                        <dd class="col-sm-9">
                            {{ $metas->get('appraisal_contingency') ? 'Yes' : 'No' }}
                            @if($metas->get('appraisal_contingency') && $metas->get('appraisal_contingency_days'))
                                ({{ $metas->get('appraisal_contingency_days') }} days)
                            @endif
                        </dd>

                        <dt class="col-sm-3">Closing Date</dt>
                        <dd class="col-sm-9">{{ $safeDate($metas->get('closing_date')) }}</dd>

                        <dt class="col-sm-3">Possession Date</dt>
                        <dd class="col-sm-9">{{ $safeDate($metas->get('possession_date')) }}</dd>

                        {{-- Purchase Terms --}}
                        @if($metas->get('initial_deposit_amount') !== null && $metas->get('initial_deposit_amount') !== '')
                        @php
                            $_initTfDisplay  = $metas->get('initial_deposit_timeframe');
                            if ($_initTfDisplay === 'Other' && $metas->get('initial_deposit_timeframe_other')) {
                                $_initTfDisplay = $metas->get('initial_deposit_timeframe_other');
                            }
                            $_initDepUnit = $metas->get('initial_deposit_amount_unit') ?? '$';
                            $_initDepFmt  = $_initDepUnit === '%'
                                ? $metas->get('initial_deposit_amount') . '%'
                                : '$' . number_format($metas->get('initial_deposit_amount'));
                        @endphp
                        <dt class="col-sm-3">Initial Deposit Amount</dt>
                        <dd class="col-sm-9">{{ $_initDepFmt }}{{ $_initTfDisplay ? ' — ' . $_initTfDisplay : '' }}</dd>
                        @endif

                        @if($metas->get('additional_deposit_amount') !== null && $metas->get('additional_deposit_amount') !== '')
                        @php
                            $_addTfDisplay = $metas->get('additional_deposit_timeframe');
                            if ($_addTfDisplay === 'Other' && $metas->get('additional_deposit_timeframe_other')) {
                                $_addTfDisplay = $metas->get('additional_deposit_timeframe_other');
                            }
                            $_addDepUnit = $metas->get('additional_deposit_amount_unit') ?? '$';
                            $_addDepFmt  = $_addDepUnit === '%'
                                ? $metas->get('additional_deposit_amount') . '%'
                                : '$' . number_format($metas->get('additional_deposit_amount'));
                        @endphp
                        <dt class="col-sm-3">Additional Deposit Amount</dt>
                        <dd class="col-sm-9">{{ $_addDepFmt }}{{ $_addTfDisplay ? ' — ' . $_addTfDisplay : '' }}</dd>
                        @endif

                        <dt class="col-sm-3">Sale of Buyer's Property Contingency</dt>
                        <dd class="col-sm-9">
                            {{ $metas->get('sale_of_buyer_property_contingency') ? 'Yes' : 'No' }}
                            @if($metas->get('sale_of_buyer_property_contingency') && $metas->get('sale_of_buyer_property_contingency_days'))
                                ({{ $metas->get('sale_of_buyer_property_contingency_days') }} days)
                            @endif
                        </dd>

                        @if($metas->get('possession_notes'))
                        <dt class="col-sm-3">Possession Notes</dt>
                        <dd class="col-sm-9" style="white-space: pre-wrap;">{{ $metas->get('possession_notes') }}</dd>
                        @endif

                        @if($metas->get('seller_contribution_requested'))
                        <dt class="col-sm-3">Seller Contribution Requested</dt>
                        <dd class="col-sm-9">
                            {{ $metas->get('seller_contribution_requested') }}
                            @if($metas->get('seller_contribution_requested') === 'Yes' && $metas->get('seller_contribution_details'))
                                — {{ $metas->get('seller_contribution_details') }}
                            @endif
                        </dd>
                        @endif

                        @if($metas->get('included_personal_property'))
                        <dt class="col-sm-3">Included Personal Property</dt>
                        <dd class="col-sm-9">{{ $metas->get('included_personal_property') }}</dd>
                        @endif

                        @if($metas->get('excluded_items'))
                        <dt class="col-sm-3">Excluded Items</dt>
                        <dd class="col-sm-9">{{ $metas->get('excluded_items') }}</dd>
                        @endif

                        @if($metas->get('home_warranty_requested'))
                        <dt class="col-sm-3">Home Warranty Requested</dt>
                        <dd class="col-sm-9">
                            {{ $metas->get('home_warranty_requested') }}
                            @if($metas->get('home_warranty_requested') === 'Yes' && $metas->get('home_warranty_details'))
                                — {{ $metas->get('home_warranty_details') }}
                            @endif
                        </dd>
                        @endif

                        @endif

                        @if(in_array($offerType, ['rental', 'lease']))
                        <dt class="col-sm-3">Monthly Rent</dt>
                        <dd class="col-sm-9">{{ $metas->get('monthly_rent') ? '$' . number_format($metas->get('monthly_rent')) : '—' }}</dd>

                        <dt class="col-sm-3">Security Deposit</dt>
                        <dd class="col-sm-9">{{ $metas->get('security_deposit') ? '$' . number_format($metas->get('security_deposit')) : '—' }}</dd>

                        <dt class="col-sm-3">Move-in Date</dt>
                        <dd class="col-sm-9">{{ $safeDate($metas->get('move_in_date')) }}</dd>

                        @if($offerType === 'lease')
                        <dt class="col-sm-3">Lease Term</dt>
                        <dd class="col-sm-9">{{ $metas->get('lease_term_months') ? $metas->get('lease_term_months') . ' months' : '—' }}</dd>
                        @endif
                        @endif

                        <dt class="col-sm-3">Custom Terms</dt>
                        <dd class="col-sm-9" style="white-space: pre-wrap;">{{ $metas->get('custom_terms') ?: '—' }}</dd>

                    </dl>
                    @endif
                </div>
            </div>

            {{-- Negotiation Timeline --}}
            <div class="card mb-4" id="offer-timeline">
                <div class="card-header">
                    <strong>Negotiation Timeline</strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Offer ID</th>
                                    <th>Parent Offer ID</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th>Submitted At</th>
                                    <th>Event Count</th>
                                    <th>Latest Event Type</th>
                                    <th>Latest Event At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($timeline as $item)
                                <tr @if($item['offer_id'] === $offer->id) class="table-active" @endif>
                                    <td>{{ $item['offer_id'] }}</td>
                                    <td>{{ $item['parent_offer_id'] ?? '—' }}</td>
                                    <td>
                                        @php $tColor = $statusColors[$item['status']] ?? 'secondary'; @endphp
                                        <span class="badge bg-{{ $tColor }} text-capitalize">{{ $item['status'] }}</span>
                                    </td>
                                    <td>{{ $item['created_at'] ?? '—' }}</td>
                                    <td>{{ $item['submitted_at'] ?? '—' }}</td>
                                    <td>{{ $item['event_count'] }}</td>
                                    <td>{{ $item['latest_event_type'] ?? '—' }}</td>
                                    <td>{{ $item['latest_event_at'] ?? '—' }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No timeline data available.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Available Actions --}}
            {{-- can_expire is intentionally not shown. Submit/Accept/Reject/Withdraw POST via named routes when enabled. --}}
            {{-- Counter has dedicated three-branch logic below the shared loop. Disabled actions render as bare disabled buttons. --}}
            <style>
                #submit-offer-action-btn { background:#2563eb; border-color:#2563eb; color:#fff; font-weight:600; }
                #submit-offer-action-btn:hover { background:#1d4ed8; border-color:#1d4ed8; }
                #submit-offer-action-btn:disabled { background:#93c5fd; border-color:#93c5fd; color:#fff; }
                .btn:disabled, .btn[disabled], .btn[aria-disabled="true"] { opacity:.55; cursor:not-allowed; }
                .btn-success:disabled  { background-color:#198754; border-color:#198754; color:#fff; }
                .btn-danger:disabled   { background-color:#dc3545; border-color:#dc3545; color:#fff; }
                .btn-warning:disabled  { background-color:#ffc107; border-color:#ffc107; color:#212529; }
                .btn-outline-secondary:disabled { background-color:transparent; color:#6c757d; border-color:#6c757d; }
            </style>
            <div class="card mb-4">
                <div class="card-header">
                    <strong>Available Actions</strong>
                </div>
                <div class="card-body">
                    @php
                        $actionButtons = [
                            'can_submit'        => ['label' => 'Submit Offer',  'btn' => 'btn-primary',           'reason_key' => 'submit',        'route' => 'offers.submit',   'hide_for_submitter' => false],
                            'can_accept'        => ['label' => 'Accept',         'btn' => 'btn-success',           'reason_key' => 'accept',        'route' => 'offers.accept',   'hide_for_submitter' => true],
                            'can_reject'        => ['label' => 'Reject',         'btn' => 'btn-danger',            'reason_key' => 'reject',        'route' => 'offers.reject',   'hide_for_submitter' => true],
                            'can_withdraw'      => ['label' => 'Withdraw',       'btn' => 'btn-outline-secondary', 'reason_key' => 'withdraw',      'route' => 'offers.withdraw', 'hide_for_submitter' => false],
                            'can_view_timeline' => ['label' => 'View Timeline',  'btn' => 'btn-outline-info',      'reason_key' => 'view_timeline', 'route' => null,              'hide_for_submitter' => false],
                        ];
                        $counterReason    = $actions['reasons']['counter'] ?? '';
                        $actorIsSubmitter = auth()->id() !== null && (int) auth()->id() === (int) $offer->user_id;
                    @endphp
                    <div class="d-flex flex-wrap gap-3 align-items-start">
                        @foreach($actionButtons as $flag => $cfg)
                            @php
                                $allowed = !empty($actions[$flag]);
                                $reason  = $allowed ? '' : ($actions['reasons'][$cfg['reason_key']] ?? '');
                            @endphp
                            {{-- Accept and Reject are hidden entirely for the submitter — they belong to the other party. --}}
                            @if($actorIsSubmitter && $cfg['hide_for_submitter'])
                                @continue
                            @endif
                            {{-- Disabled / not-permitted: render a disabled button with the reason if one is provided. --}}
                            @if(!$allowed)
                                @if($reason)
                                    <div class="d-flex flex-column align-items-start" style="min-width: 130px;">
                                        <button type="button" class="btn {{ $cfg['btn'] }} btn-sm" disabled>{{ $cfg['label'] }}</button>
                                        <small class="text-muted mt-1">{{ $reason }}</small>
                                    </div>
                                @endif
                                @continue
                            @endif
                            <div class="d-flex flex-column align-items-start" style="min-width: 130px;">
                                @if($cfg['route'])
                                    {{-- Enabled action with a route: POST form --}}
                                    <form method="POST" action="{{ route($cfg['route'], $offer) }}">
                                        @csrf
                                        <button type="submit" class="btn {{ $cfg['btn'] }} btn-sm"@if($flag === 'can_submit') id="submit-offer-action-btn"@endif>{{ $cfg['label'] }}</button>
                                    </form>
                                @else
                                    {{-- Enabled action with no route (e.g. View Timeline): anchor to on-page section --}}
                                    <a href="#offer-timeline" class="btn {{ $cfg['btn'] }} btn-sm">{{ $cfg['label'] }}</a>
                                @endif
                            </div>
                        @endforeach

                        {{-- Counter: hidden entirely for the submitter. --}}
                        @if(!$actorIsSubmitter)
                            @if(!empty($actions['can_counter']))
                                {{-- can_counter=true: full counter form pre-populated from current offer terms --}}
                                <div class="w-100 mt-3">
                                    <div class="card border-warning">
                                        <div class="card-header bg-warning bg-opacity-10 fw-semibold">Submit Counter Offer</div>
                                        <div class="card-body">
                                            @include('offers._offer_terms_form', ['mode' => 'counter_terms', 'formData' => $counterDefaults])
                                        </div>
                                    </div>
                                </div>
                            @elseif($counterReason)
                                {{-- can_counter=false with a reason: disabled button so the user knows why --}}
                                <div class="d-flex flex-column align-items-start" style="min-width: 130px;">
                                    <button type="button" class="btn btn-warning btn-sm" disabled>Counter</button>
                                    <small class="text-muted mt-1">{{ $counterReason }}</small>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
