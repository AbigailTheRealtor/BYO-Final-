{{--
    Read-only offer terms display partial.

    Required variables:
      $metas     — Illuminate\Support\Collection (meta_key => meta_value)
      $offerType — 'sale' | 'rental' | 'lease'
--}}
@php
    $safeDate = function ($v) {
        if (!$v) return '—';
        try { return \Carbon\Carbon::parse($v)->format('Y-m-d'); }
        catch (\Throwable $e) { return '—'; }
    };
@endphp

<dl class="row mb-0">
    <dt class="col-sm-3">Offer Expires At</dt>
    <dd class="col-sm-9">{{ $safeDate($metas->get('expires_at')) }}</dd>

    @if($offerType === 'sale')
    @php
        $ftRaw   = $metas->get('financing_type');
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
        $_exItem      = $metas->get('exchange_item');
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
