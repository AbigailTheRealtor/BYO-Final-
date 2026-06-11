{{--
    Shared offer-terms form partial.

    Required variables passed by caller:
      $mode      — 'draft_terms' | 'counter_terms'
      $formData  — Illuminate\Support\Collection of meta key→value pairs
      $offer     — App\Models\Offer  (available from parent scope via @include)
      $offerType — 'sale' | 'rental' | 'lease'  (available from parent scope)

    $safeDate closure is available from the parent view scope.
--}}
@php
    $fmtMoney = function($v) {
        if ($v === null || $v === '') return '';
        $clean = str_replace(',', '', (string) $v);
        return is_numeric($clean) ? number_format((int) $clean) : (string) $v;
    };
    $formAction   = $mode === 'draft_terms'
        ? route('offers.terms',   $offer)
        : route('offers.counter', $offer);
    $submitLabel  = $mode === 'draft_terms' ? 'Save Offer Terms' : 'Submit Counter Offer';
    $submitBtnId  = $mode === 'draft_terms' ? 'save-offer-terms-btn' : 'counter-offer-submit-btn';
    $formId       = 'offer-terms-form-' . $mode;
@endphp
<style>
    #save-offer-terms-btn,
    #counter-offer-submit-btn { background:#2563eb; border-color:#2563eb; color:#fff; font-weight:600; }
    #save-offer-terms-btn:hover,
    #counter-offer-submit-btn:hover { background:#1d4ed8; border-color:#1d4ed8; }
    /* Tailwind Preflight sets background-color:transparent on [type='submit'], which
       beats Bootstrap's .btn-success when Tailwind loads after Bootstrap. Scoped ID
       override (higher specificity) restores the correct green appearance. */
    #save-and-submit-offer-btn { background-color:#198754 !important; border-color:#198754 !important; color:#fff !important; font-weight:600; }
    #save-and-submit-offer-btn:hover { background-color:#157347 !important; border-color:#146c43 !important; color:#fff !important; }
    .offer-section-header {
        font-size: 0.9rem;
        font-weight: 600;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 0.4rem;
        margin-top: 1.5rem;
        margin-bottom: 1rem;
    }
    .offer-section-header:first-child { margin-top: 0; }
    .contingency-group { display: flex; flex-direction: column; gap: 0.5rem; }
    .contingency-item { padding: 0.5rem 0.75rem; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 0.375rem; }
    .contingency-days { margin-top: 0.35rem; padding-left: 2.25rem; }
</style>
<form id="{{ $formId }}" method="POST" action="{{ $formAction }}">
    @csrf
    <input type="hidden" name="_offer_terms_present" value="1">

    @if($errors->any())
    <div class="alert alert-danger mb-3">
        <ul class="mb-0 ps-3">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- Sale-specific fields --}}
    @if($offerType === 'sale')
    @php
        $_ft     = old('financing_type',              $formData->get('financing_type') ?? '');
        $_initTf = old('initial_deposit_timeframe',   $formData->get('initial_deposit_timeframe') ?? '');
        $_addTf  = old('additional_deposit_timeframe',$formData->get('additional_deposit_timeframe') ?? '');
        $_downPayVal      = old('down_payment_value',  $formData->get('down_payment_value')  ?? $formData->get('down_payment_percent') ?? '');
        $_downPayUnit     = old('down_payment_unit',   $formData->get('down_payment_unit')   ?? '%');
        $_earnestDepUnit  = old('earnest_deposit_unit',         $formData->get('earnest_deposit_unit')         ?? '$');
        $_initDepUnit     = old('initial_deposit_amount_unit',  $formData->get('initial_deposit_amount_unit')  ?? '$');
        $_addDepUnit      = old('additional_deposit_amount_unit',$formData->get('additional_deposit_amount_unit') ?? '$');
        $_sfDpType        = old('sf_down_payment_type',         $formData->get('sf_down_payment_type')         ?? '$');
        $_sfAmountType    = old('seller_financing_amount_type', $formData->get('seller_financing_amount_type') ?? '$');
    @endphp
    <div x-data="{
        finType: '{{ $_ft }}',
        finCont: {{ old('financing_contingency', $formData->get('financing_contingency')) ? 'true' : 'false' }},
        inspCont: {{ old('inspection_contingency', $formData->get('inspection_contingency')) ? 'true' : 'false' }},
        apprCont: {{ old('appraisal_contingency', $formData->get('appraisal_contingency')) ? 'true' : 'false' }},
        saleCont: {{ old('sale_of_buyer_property_contingency', $formData->get('sale_of_buyer_property_contingency')) ? 'true' : 'false' }},
        sellerContrib: '{{ old('seller_contribution_requested', $formData->get('seller_contribution_requested') ?? '') }}',
        homeWarranty: '{{ old('home_warranty_requested', $formData->get('home_warranty_requested') ?? '') }}',
        initTf: '{{ $_initTf }}',
        addTf: '{{ $_addTf }}',
        earnestDepUnit: '{{ old('earnest_deposit_unit', $formData->get('earnest_deposit_unit') ?? '$') }}',
        initDepUnit: '{{ old('initial_deposit_amount_unit', $formData->get('initial_deposit_amount_unit') ?? '$') }}',
        addDepUnit: '{{ old('additional_deposit_amount_unit', $formData->get('additional_deposit_amount_unit') ?? '$') }}',
        downPayUnit: '{{ $_downPayUnit }}',
        sfBalloon: '{{ old('seller_financing_balloon', $formData->get('seller_financing_balloon') ?? '') }}',
        sfAmortType: '{{ old('seller_financing_amortization', $formData->get('seller_financing_amortization') ?? '') }}',
        sfPayFreq: '{{ old('seller_financing_payment_frequency', $formData->get('seller_financing_payment_frequency') ?? '') }}',
        sfAmountType: '{{ old('seller_financing_amount_type', $formData->get('seller_financing_amount_type') ?? '$') }}',
        sfDpType: '{{ old('sf_down_payment_type', $formData->get('sf_down_payment_type') ?? '$') }}',
        sfPrePayPenalty: '{{ old('prepayment_penalty', $formData->get('prepayment_penalty') ?? '') }}',
        exchangeItemType: '{{ old('exchange_item', $formData->get('exchange_item') ?? '') }}',
        exchangeLiens: '{{ old('exchange_liens', $formData->get('exchange_liens') ?? '') }}',
        leaseOptFee: '{{ old('has_option_fee', $formData->get('has_option_fee') ?? '') }}',
        leaseOptFeeCredit: '{{ old('lease_option_fee_credit', $formData->get('lease_option_fee_credit') ?? '') }}',
        leasePurchRentCredit: '{{ old('lease_purchase_rent_credit', $formData->get('lease_purchase_rent_credit') ?? '') }}'
    }">

    {{-- ── Section 1: Purchase Price & Deposits ── --}}
    <h6 class="offer-section-header">Purchase Price &amp; Deposits</h6>
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <label class="form-label fw-semibold">
                Offer Price ($)
                <span class="ms-1" data-bs-toggle="tooltip" data-bs-html="true"
                    title="The total purchase price you are offering for the property.">
                    <i class="fa-solid fa-circle-info" style="color:#2563eb;cursor:pointer;font-size:0.85rem;"></i>
                </span>
            </label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                <input type="text" inputmode="numeric" name="offer_price" class="form-control" data-money-input="true"
                    placeholder="Enter offer price (e.g., 450,000)"
                    value="{{ $fmtMoney(old('offer_price', $formData->get('offer_price'))) }}">
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">
                Earnest Deposit
                <span class="ms-1" data-bs-toggle="tooltip" data-bs-html="true"
                    title="The good-faith deposit accompanying your offer, typically held in escrow until closing.">
                    <i class="fa-solid fa-circle-info" style="color:#2563eb;cursor:pointer;font-size:0.85rem;"></i>
                </span>
            </label>
            <div class="input-group">
                <select name="earnest_deposit_unit" class="form-select" style="max-width:70px;" x-model="earnestDepUnit">
                    <option value="$">$</option>
                    <option value="%">%</option>
                </select>
                <input type="text" inputmode="numeric" name="earnest_deposit" class="form-control" data-money-input="true" data-unit-select="earnest_deposit_unit"
                    :placeholder="earnestDepUnit === '%' ? 'Enter earnest deposit % (e.g., 1.5)' : 'Enter earnest deposit (e.g., 5,000)'"
                    value="{{ $_earnestDepUnit !== '%' ? $fmtMoney(old('earnest_deposit', $formData->get('earnest_deposit'))) : old('earnest_deposit', $formData->get('earnest_deposit')) }}">
                <span class="input-group-text" x-show="earnestDepUnit === '%'">%</span>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">
                Down Payment
                <span class="ms-1" data-bs-toggle="tooltip" data-bs-html="true"
                    title="The amount or percentage of the purchase price you plan to pay upfront, outside of any financed amount.">
                    <i class="fa-solid fa-circle-info" style="color:#2563eb;cursor:pointer;font-size:0.85rem;"></i>
                </span>
            </label>
            <div class="input-group">
                <select name="down_payment_unit" class="form-select" style="max-width:70px;" x-model="downPayUnit">
                    <option value="$">$</option>
                    <option value="%">%</option>
                </select>
                <input type="text" inputmode="numeric" name="down_payment_value" class="form-control" data-money-input="true" data-unit-select="down_payment_unit"
                    :placeholder="downPayUnit === '%' ? 'Enter down payment % (e.g., 20)' : 'Enter down payment amount (e.g., 90,000)'"
                    value="{{ $_downPayUnit !== '%' ? $fmtMoney(old('down_payment_value', $_downPayVal)) : old('down_payment_value', $_downPayVal) }}">
                <span class="input-group-text" x-show="downPayUnit === '%'">%</span>
            </div>
        </div>
    </div>

    {{-- Initial Deposit --}}
    <div class="row g-3 mb-3">
        <div class="col-md-5">
            <label class="form-label fw-semibold">Initial Deposit Amount</label>
            <div class="input-group">
                <select name="initial_deposit_amount_unit" class="form-select" style="max-width:70px;" x-model="initDepUnit">
                    <option value="$">$</option>
                    <option value="%">%</option>
                </select>
                <input type="text" inputmode="numeric" name="initial_deposit_amount" class="form-control" data-money-input="true" data-unit-select="initial_deposit_amount_unit"
                    :placeholder="initDepUnit === '%' ? 'Enter initial deposit % (e.g., 1)' : 'Enter initial deposit (e.g., 5,000)'"
                    value="{{ $_initDepUnit !== '%' ? $fmtMoney(old('initial_deposit_amount', $formData->get('initial_deposit_amount'))) : old('initial_deposit_amount', $formData->get('initial_deposit_amount')) }}">
                <span class="input-group-text" x-show="initDepUnit === '%'">%</span>
            </div>
        </div>
        <div class="col-md-5">
            <label class="form-label fw-semibold">Initial Deposit Timeframe</label>
            <select name="initial_deposit_timeframe" class="form-select" x-model="initTf">
                <option value="">Select</option>
                @foreach(['Within 1 Day','Within 3 Days','Within 5 Days','Within 7 Days','Within 10 Days','Within 14 Days','At Closing','Other'] as $opt)
                <option value="{{ $opt }}" {{ $_initTf === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
            <div x-show="initTf === 'Other'" class="mt-2">
                <input type="text" name="initial_deposit_timeframe_other" class="form-control"
                    placeholder="Enter timeframe (e.g., Within 21 Days)"
                    value="{{ old('initial_deposit_timeframe_other', $formData->get('initial_deposit_timeframe_other')) }}">
            </div>
        </div>
    </div>

    {{-- Additional Deposit --}}
    <div class="row g-3 mb-3">
        <div class="col-md-5">
            <label class="form-label fw-semibold">Additional Deposit Amount</label>
            <div class="input-group">
                <select name="additional_deposit_amount_unit" class="form-select" style="max-width:70px;" x-model="addDepUnit">
                    <option value="$">$</option>
                    <option value="%">%</option>
                </select>
                <input type="text" inputmode="numeric" name="additional_deposit_amount" class="form-control" data-money-input="true" data-unit-select="additional_deposit_amount_unit"
                    :placeholder="addDepUnit === '%' ? 'Enter additional deposit % (e.g., 2)' : 'Enter additional deposit (e.g., 10,000)'"
                    value="{{ $_addDepUnit !== '%' ? $fmtMoney(old('additional_deposit_amount', $formData->get('additional_deposit_amount'))) : old('additional_deposit_amount', $formData->get('additional_deposit_amount')) }}">
                <span class="input-group-text" x-show="addDepUnit === '%'">%</span>
            </div>
        </div>
        <div class="col-md-5">
            <label class="form-label fw-semibold">Additional Deposit Timeframe</label>
            <select name="additional_deposit_timeframe" class="form-select" x-model="addTf">
                <option value="">Select</option>
                @foreach(['Within 1 Day','Within 3 Days','Within 5 Days','Within 7 Days','Within 10 Days','Within 14 Days','At Closing','Other'] as $opt)
                <option value="{{ $opt }}" {{ $_addTf === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
            <div x-show="addTf === 'Other'" class="mt-2">
                <input type="text" name="additional_deposit_timeframe_other" class="form-control"
                    placeholder="Enter timeframe (e.g., Within 21 Days)"
                    value="{{ old('additional_deposit_timeframe_other', $formData->get('additional_deposit_timeframe_other')) }}">
            </div>
        </div>
    </div>

    {{-- ── Section 2: Financing ── --}}
    <h6 class="offer-section-header">Financing</h6>
    <div class="row g-3 mb-3">
        <div class="col-md-5">
            <label class="form-label fw-semibold">
                Financing Type
                <span class="ms-1" data-bs-toggle="tooltip" data-bs-html="true"
                    title="The method you plan to use to fund this purchase. Select one that best describes how the transaction will be financed.">
                    <i class="fa-solid fa-circle-info" style="color:#2563eb;cursor:pointer;font-size:0.85rem;"></i>
                </span>
            </label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-landmark"></i></span>
                <select name="financing_type" class="form-select" x-model="finType">
                    <option value="">Select</option>
                    @foreach([
                        'Assumable',
                        'Cash',
                        'Conventional',
                        'FHA',
                        'Jumbo',
                        'VA',
                        'No-Doc',
                        'Non-QM',
                        'USDA',
                        'Cryptocurrency',
                        'Exchange/Trade',
                        'Lease Option',
                        'Lease Purchase',
                        'Non-Fungible Token (NFT)',
                        'Seller Financing',
                        'Other',
                    ] as $ftOpt)
                    <option value="{{ $ftOpt }}" {{ $_ft === $ftOpt ? 'selected' : '' }}>{{ $ftOpt }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Assumable conditional sub-fields --}}
    <div x-show="finType === 'Assumable'" class="border rounded p-3 mb-3 bg-light"
         x-data="{ assumableInterest: '{{ old('assumable_interest', $formData->get('assumable_interest')) }}' }">
        <h6 class="fw-semibold mb-3">Assumable Financing</h6>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Interested in Assumable Financing?</label>
                <select name="assumable_interest" class="form-select"
                    x-model="assumableInterest"
                    @change="if (assumableInterest !== 'Yes') {
                        $el.closest('.row').querySelectorAll('[data-assumable-optional]').forEach(function(el) {
                            el.value = '';
                        });
                    }">
                    <option value="">Select</option>
                    <option value="Yes" {{ old('assumable_interest', $formData->get('assumable_interest')) === 'Yes' ? 'selected' : '' }}>Yes</option>
                    <option value="No" {{ old('assumable_interest', $formData->get('assumable_interest')) === 'No' ? 'selected' : '' }}>No</option>
                </select>
            </div>
            <div class="col-md-6" x-show="assumableInterest === 'Yes'">
                <label class="form-label fw-semibold">Maximum Interest Rate You Would Accept (%)</label>
                <div class="input-group">
                    <input type="number" name="assumable_max_interest_rate" class="form-control" min="0" max="100" step="0.01"
                        placeholder="Enter maximum acceptable rate (e.g., 5)"
                        data-assumable-optional
                        value="{{ old('assumable_max_interest_rate', $formData->get('assumable_max_interest_rate')) }}">
                    <span class="input-group-text">%</span>
                </div>
            </div>
            <div class="col-md-6" x-show="assumableInterest === 'Yes'">
                <label class="form-label fw-semibold">Maximum Monthly Payment (P&amp;I) You Would Accept</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                    <input type="text" inputmode="numeric" name="assumable_max_monthly_payment" class="form-control" data-money-input="true"
                        placeholder="Enter maximum monthly payment (e.g., 2,000)"
                        data-assumable-optional
                        value="{{ $fmtMoney(old('assumable_max_monthly_payment', $formData->get('assumable_max_monthly_payment'))) }}">
                </div>
            </div>
            <div class="col-md-6" x-show="assumableInterest === 'Yes'">
                <label class="form-label fw-semibold">Cash Available to Bridge the Gap</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                    <input type="text" inputmode="numeric" name="assumable_bridge_gap_cash" class="form-control" data-money-input="true"
                        placeholder="Enter bridge gap cash amount (e.g., 50,000)"
                        data-assumable-optional
                        value="{{ $fmtMoney(old('assumable_bridge_gap_cash', $formData->get('assumable_bridge_gap_cash'))) }}">
                </div>
            </div>
        </div>
    </div>

    {{-- Cryptocurrency conditional sub-fields --}}
    <div x-show="finType === 'Cryptocurrency'" class="border rounded p-3 mb-3 bg-light">
        <h6 class="fw-semibold mb-3">Cryptocurrency Details</h6>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Cryptocurrency Type</label>
                <input type="text" name="cryptocurrency_type" class="form-control"
                    placeholder="Enter currency type (e.g., Bitcoin, Ethereum)"
                    value="{{ old('cryptocurrency_type', $formData->get('cryptocurrency_type')) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">% of Price Paid with Crypto</label>
                <div class="input-group">
                    <input type="number" name="crypto_percentage" class="form-control" min="0" max="100" step="1"
                        placeholder="Enter percentage (e.g., 50)"
                        value="{{ old('crypto_percentage', $formData->get('crypto_percentage')) }}">
                    <span class="input-group-text">%</span>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Exchange / Conversion Method</label>
                <input type="text" name="crypto_exchange_method" class="form-control"
                    placeholder="Enter method (e.g., Spot price at closing via Coinbase)"
                    value="{{ old('crypto_exchange_method', $formData->get('crypto_exchange_method')) }}">
            </div>
        </div>
    </div>

    {{-- Exchange/Trade conditional sub-fields --}}
    <div x-show="finType === 'Exchange/Trade'" class="border rounded p-3 mb-3 bg-light">
        <h6 class="fw-semibold mb-3">Exchange / Trade Details</h6>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Item Offered for Exchange</label>
                <select name="exchange_item" class="form-select" x-model="exchangeItemType">
                    <option value="">Select</option>
                    @foreach(['Another Home','Artwork','Boat','Jewelry','Motorhome','Vehicle','Other'] as $ei)
                    <option value="{{ $ei }}" {{ old('exchange_item', $formData->get('exchange_item')) === $ei ? 'selected' : '' }}>{{ $ei }}</option>
                    @endforeach
                </select>
                <div x-show="exchangeItemType === 'Other'" class="mt-2">
                    <input type="text" name="other_exchange_item" class="form-control"
                        placeholder="Enter item (e.g., Private Jet, Yacht)"
                        value="{{ old('other_exchange_item', $formData->get('other_exchange_item')) }}">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Estimated Value ($)</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                    <input type="text" inputmode="numeric" name="exchange_item_value" class="form-control" data-money-input="true"
                        placeholder="Enter value (e.g., 75,000)"
                        value="{{ $fmtMoney(old('exchange_item_value', $formData->get('exchange_item_value'))) }}">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Condition of Item</label>
                <select name="exchange_item_condition" class="form-select">
                    <option value="">Select</option>
                    @foreach(['New','Like New','Excellent','Very Good','Good','Fair','Repair','Salvage Condition'] as $cond)
                    <option value="{{ $cond }}" {{ old('exchange_item_condition', $formData->get('exchange_item_condition')) === $cond ? 'selected' : '' }}>{{ $cond }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Additional Cash Offered ($)</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                    <input type="text" inputmode="numeric" name="additional_cash" class="form-control" data-money-input="true"
                        placeholder="Enter additional cash offered (e.g., 25,000)"
                        value="{{ $fmtMoney(old('additional_cash', $formData->get('additional_cash'))) }}">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Value Determined By</label>
                <input type="text" name="value_determination" class="form-control"
                    placeholder="Enter valuation method (e.g., Licensed appraisal, Online valuation)"
                    value="{{ old('value_determination', $formData->get('value_determination')) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Transfer Method / Logistics</label>
                <input type="text" name="exchange_transfer_method" class="form-control"
                    placeholder="Enter transfer method (e.g., Title transfer, Bill of sale, Delivery at closing)"
                    value="{{ old('exchange_transfer_method', $formData->get('exchange_transfer_method')) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Liens / Encumbrances?</label>
                <select name="exchange_liens" class="form-select" x-model="exchangeLiens">
                    <option value="">Select</option>
                    <option value="Yes" {{ old('exchange_liens', $formData->get('exchange_liens')) === 'Yes' ? 'selected' : '' }}>Yes</option>
                    <option value="No" {{ old('exchange_liens', $formData->get('exchange_liens')) === 'No' ? 'selected' : '' }}>No</option>
                </select>
                <div x-show="exchangeLiens === 'Yes'" class="mt-2">
                    <input type="text" name="exchange_liens_details" class="form-control"
                        placeholder="Enter lien details (e.g., Auto loan balance, UCC filing)"
                        value="{{ old('exchange_liens_details', $formData->get('exchange_liens_details')) }}">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Inspection / Verification Rights</label>
                <select name="exchange_inspection_rights" class="form-select">
                    <option value="">Select</option>
                    <option value="Yes" {{ old('exchange_inspection_rights', $formData->get('exchange_inspection_rights')) === 'Yes' ? 'selected' : '' }}>Yes</option>
                    <option value="No" {{ old('exchange_inspection_rights', $formData->get('exchange_inspection_rights')) === 'No' ? 'selected' : '' }}>No</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Seller Financing conditional sub-fields --}}
    <div x-show="finType === 'Seller Financing'" class="border rounded p-3 mb-3 bg-light">
        <h6 class="fw-semibold mb-3">Seller Financing Details</h6>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Desired Purchase Price ($)</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                    <input type="text" inputmode="numeric" name="sf_purchase_price" class="form-control" data-money-input="true"
                        placeholder="Enter purchase price (e.g., 500,000)"
                        value="{{ $fmtMoney(old('sf_purchase_price', $formData->get('sf_purchase_price'))) }}">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Desired Down Payment</label>
                <div class="input-group">
                    <select name="sf_down_payment_type" class="form-select" style="max-width:70px;" x-model="sfDpType">
                        <option value="$">$</option>
                        <option value="%">%</option>
                    </select>
                    <input type="text" inputmode="numeric" name="sf_down_payment_amount" class="form-control" data-money-input="true" data-unit-select="sf_down_payment_type"
                        :placeholder="sfDpType === '%' ? 'Enter down payment % (e.g., 20)' : 'Enter down payment amount (e.g., 100,000)'"
                        value="{{ $_sfDpType !== '%' ? $fmtMoney(old('sf_down_payment_amount', $formData->get('sf_down_payment_amount'))) : old('sf_down_payment_amount', $formData->get('sf_down_payment_amount')) }}">
                    <span class="input-group-text" x-show="sfDpType === '%'">%</span>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Desired Financing Amount</label>
                <div class="input-group">
                    <select name="seller_financing_amount_type" class="form-select" style="max-width:70px;" x-model="sfAmountType">
                        <option value="$">$</option>
                        <option value="%">%</option>
                    </select>
                    <input type="text" inputmode="numeric" name="seller_financing_amount" class="form-control" data-money-input="true" data-unit-select="seller_financing_amount_type"
                        :placeholder="sfAmountType === '%' ? 'Enter financing amount % (e.g., 80)' : 'Enter financing amount (e.g., 400,000)'"
                        value="{{ $_sfAmountType !== '%' ? $fmtMoney(old('seller_financing_amount', $formData->get('seller_financing_amount'))) : old('seller_financing_amount', $formData->get('seller_financing_amount')) }}">
                    <span class="input-group-text" x-show="sfAmountType === '%'">%</span>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Proposed Interest Rate (%)</label>
                <div class="input-group">
                    <input type="number" name="seller_financing_rate" class="form-control" min="0" max="100" step="0.01"
                        placeholder="Enter rate (e.g., 6.5)"
                        value="{{ old('seller_financing_rate', $formData->get('seller_financing_rate')) }}">
                    <span class="input-group-text">%</span>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Loan Term</label>
                <input type="text" name="seller_financing_term" class="form-control"
                    placeholder="Enter loan term (e.g., 30 years)"
                    value="{{ old('seller_financing_term', $formData->get('seller_financing_term')) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Amortization Type</label>
                <select name="seller_financing_amortization" class="form-select" x-model="sfAmortType">
                    <option value="">Select</option>
                    <option value="Fully Amortizing" {{ old('seller_financing_amortization', $formData->get('seller_financing_amortization')) === 'Fully Amortizing' ? 'selected' : '' }}>Fully Amortizing</option>
                    <option value="Interest-Only" {{ old('seller_financing_amortization', $formData->get('seller_financing_amortization')) === 'Interest-Only' ? 'selected' : '' }}>Interest-Only</option>
                    <option value="Other" {{ old('seller_financing_amortization', $formData->get('seller_financing_amortization')) === 'Other' ? 'selected' : '' }}>Other</option>
                </select>
                <div x-show="sfAmortType === 'Other'" class="mt-2">
                    <input type="text" name="seller_financing_amortization_other" class="form-control"
                        placeholder="Enter custom amortization type (e.g., Graduated payments)"
                        value="{{ old('seller_financing_amortization_other', $formData->get('seller_financing_amortization_other')) }}">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Payment Frequency</label>
                <select name="seller_financing_payment_frequency" class="form-select" x-model="sfPayFreq">
                    <option value="">Select</option>
                    @foreach(['Monthly','Bi-Weekly','Quarterly','Annually','Other'] as $pf)
                    <option value="{{ $pf }}" {{ old('seller_financing_payment_frequency', $formData->get('seller_financing_payment_frequency')) === $pf ? 'selected' : '' }}>{{ $pf }}</option>
                    @endforeach
                </select>
                <div x-show="sfPayFreq === 'Other'" class="mt-2">
                    <input type="text" name="seller_financing_payment_frequency_other" class="form-control"
                        placeholder="Enter payment schedule (e.g., Semi-Annual)"
                        value="{{ old('seller_financing_payment_frequency_other', $formData->get('seller_financing_payment_frequency_other')) }}">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Balloon Payment</label>
                <select name="seller_financing_balloon" class="form-select" x-model="sfBalloon">
                    <option value="">Select</option>
                    <option value="Yes" {{ old('seller_financing_balloon', $formData->get('seller_financing_balloon')) === 'Yes' ? 'selected' : '' }}>Yes</option>
                    <option value="No" {{ old('seller_financing_balloon', $formData->get('seller_financing_balloon')) === 'No' ? 'selected' : '' }}>No</option>
                </select>
                <div x-show="sfBalloon === 'Yes'" class="mt-2">
                    <div class="input-group mb-1">
                        <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                        <input type="text" inputmode="numeric" name="seller_financing_balloon_amount" class="form-control" data-money-input="true"
                            placeholder="Enter balloon amount (e.g., 100,000)"
                            value="{{ $fmtMoney(old('seller_financing_balloon_amount', $formData->get('seller_financing_balloon_amount'))) }}">
                    </div>
                    <input type="text" name="seller_financing_balloon_date" class="form-control"
                        placeholder="Enter due date (e.g., 5 years)"
                        value="{{ old('seller_financing_balloon_date', $formData->get('seller_financing_balloon_date')) }}">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Prepayment Penalty</label>
                <select name="prepayment_penalty" class="form-select" x-model="sfPrePayPenalty">
                    <option value="">Select</option>
                    <option value="Yes" {{ old('prepayment_penalty', $formData->get('prepayment_penalty')) === 'Yes' ? 'selected' : '' }}>Yes</option>
                    <option value="No" {{ old('prepayment_penalty', $formData->get('prepayment_penalty')) === 'No' ? 'selected' : '' }}>No</option>
                </select>
                <div x-show="sfPrePayPenalty === 'Yes'" class="mt-2">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                        <input type="text" inputmode="numeric" name="prepayment_penalty_amount" class="form-control" data-money-input="true"
                            placeholder="Enter penalty amount (e.g., 5,000)"
                            value="{{ $fmtMoney(old('prepayment_penalty_amount', $formData->get('prepayment_penalty_amount'))) }}">
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Late Payment Fee</label>
                <input type="text" name="seller_late_fee_amount" class="form-control"
                    placeholder="Enter late payment fee (e.g., $100 after 10 days, or 5% after 15 days)"
                    value="{{ old('seller_late_fee_amount', $formData->get('seller_late_fee_amount')) }}">
            </div>
        </div>
    </div>

    {{-- Lease Option conditional sub-fields --}}
    <div x-show="finType === 'Lease Option'" class="border rounded p-3 mb-3 bg-light">
        <h6 class="fw-semibold mb-3">Lease Option Details</h6>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Offering Price ($)</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                    <input type="text" inputmode="numeric" name="lease_option_price" class="form-control" data-money-input="true"
                        placeholder="Enter offering price (e.g., 500,000)"
                        value="{{ $fmtMoney(old('lease_option_price', $formData->get('lease_option_price'))) }}">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Monthly Payment ($)</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                    <input type="text" inputmode="numeric" name="lease_option_payment" class="form-control" data-money-input="true"
                        placeholder="Enter monthly payment (e.g., 2,500)"
                        value="{{ $fmtMoney(old('lease_option_payment', $formData->get('lease_option_payment'))) }}">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Lease Duration (Months)</label>
                <input type="number" name="lease_option_duration" class="form-control" min="1"
                    placeholder="Enter duration in months (e.g., 12)"
                    value="{{ old('lease_option_duration', $formData->get('lease_option_duration')) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Option Fee Offered?</label>
                <select name="has_option_fee" class="form-select" x-model="leaseOptFee">
                    <option value="">Select</option>
                    <option value="Yes" {{ old('has_option_fee', $formData->get('has_option_fee')) === 'Yes' ? 'selected' : '' }}>Yes</option>
                    <option value="No" {{ old('has_option_fee', $formData->get('has_option_fee')) === 'No' ? 'selected' : '' }}>No</option>
                </select>
                <div x-show="leaseOptFee === 'Yes'" class="mt-2">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                        <input type="text" inputmode="numeric" name="option_fee_amount" class="form-control" data-money-input="true"
                            placeholder="Enter option fee amount (e.g., 15,000)"
                            value="{{ $fmtMoney(old('option_fee_amount', $formData->get('option_fee_amount'))) }}">
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Fee Credit Toward Price?</label>
                <select name="lease_option_fee_credit" class="form-select" x-model="leaseOptFeeCredit">
                    <option value="">Select</option>
                    <option value="Yes" {{ old('lease_option_fee_credit', $formData->get('lease_option_fee_credit')) === 'Yes' ? 'selected' : '' }}>Yes</option>
                    <option value="No" {{ old('lease_option_fee_credit', $formData->get('lease_option_fee_credit')) === 'No' ? 'selected' : '' }}>No</option>
                    <option value="Partial" {{ old('lease_option_fee_credit', $formData->get('lease_option_fee_credit')) === 'Partial' ? 'selected' : '' }}>Partial</option>
                </select>
                <div x-show="leaseOptFeeCredit === 'Partial'" class="mt-2">
                    <input type="number" name="lease_option_fee_credit_pct" class="form-control" min="0" max="100"
                        placeholder="Credit percentage (e.g., 50)"
                        value="{{ old('lease_option_fee_credit_pct', $formData->get('lease_option_fee_credit_pct')) }}">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Maintenance Responsibility</label>
                <select name="lease_option_maintenance" class="form-select">
                    <option value="">Select</option>
                    @foreach(['Seller','Tenant-Buyer','Shared'] as $mr)
                    <option value="{{ $mr }}" {{ old('lease_option_maintenance', $formData->get('lease_option_maintenance')) === $mr ? 'selected' : '' }}>{{ $mr }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Conditions / Requirements</label>
                <input type="text" name="lease_option_conditions" class="form-control"
                    placeholder="Enter conditions (e.g., Option exercisable after 12 months, Property must pass inspection)"
                    value="{{ old('lease_option_conditions', $formData->get('lease_option_conditions')) }}">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Specific Terms</label>
                <input type="text" name="lease_option_terms" class="form-control"
                    placeholder="Enter specific terms (e.g., Buyer may conduct inspections during lease term)"
                    value="{{ old('lease_option_terms', $formData->get('lease_option_terms')) }}">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Extension Terms</label>
                <input type="text" name="lease_option_extension_terms" class="form-control"
                    placeholder="Enter extension terms (e.g., Tenant-Buyer may extend for 6 months with additional $5,000 fee)"
                    value="{{ old('lease_option_extension_terms', $formData->get('lease_option_extension_terms')) }}">
            </div>
        </div>
    </div>

    {{-- Lease Purchase conditional sub-fields --}}
    <div x-show="finType === 'Lease Purchase'" class="border rounded p-3 mb-3 bg-light">
        <h6 class="fw-semibold mb-3">Lease Purchase Details</h6>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Purchase Price ($)</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                    <input type="text" inputmode="numeric" name="lease_purchase_price" class="form-control" data-money-input="true"
                        placeholder="Enter purchase price (e.g., 800,000)"
                        value="{{ $fmtMoney(old('lease_purchase_price', $formData->get('lease_purchase_price'))) }}">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Monthly Payment ($)</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                    <input type="text" inputmode="numeric" name="lease_purchase_payment" class="form-control" data-money-input="true"
                        placeholder="Enter monthly payment (e.g., 5,000)"
                        value="{{ $fmtMoney(old('lease_purchase_payment', $formData->get('lease_purchase_payment'))) }}">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Lease Duration (Months)</label>
                <input type="number" name="lease_purchase_duration" class="form-control" min="1"
                    placeholder="Enter duration in months (e.g., 12)"
                    value="{{ old('lease_purchase_duration', $formData->get('lease_purchase_duration')) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Rent Credit Toward Price?</label>
                <select name="lease_purchase_rent_credit" class="form-select" x-model="leasePurchRentCredit">
                    <option value="">Select</option>
                    <option value="Yes" {{ old('lease_purchase_rent_credit', $formData->get('lease_purchase_rent_credit')) === 'Yes' ? 'selected' : '' }}>Yes</option>
                    <option value="No" {{ old('lease_purchase_rent_credit', $formData->get('lease_purchase_rent_credit')) === 'No' ? 'selected' : '' }}>No</option>
                    <option value="Partial" {{ old('lease_purchase_rent_credit', $formData->get('lease_purchase_rent_credit')) === 'Partial' ? 'selected' : '' }}>Partial</option>
                </select>
                <div x-show="leasePurchRentCredit === 'Yes' || leasePurchRentCredit === 'Partial'" class="mt-2">
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" name="lease_purchase_rent_credit_amount" class="form-control" min="0"
                            placeholder="Rent credit amount per month (e.g., 500)"
                            value="{{ old('lease_purchase_rent_credit_amount', $formData->get('lease_purchase_rent_credit_amount')) }}">
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Non-Refundable Deposit ($)</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                    <input type="text" inputmode="numeric" name="lease_purchase_deposit" class="form-control" data-money-input="true"
                        placeholder="Enter deposit amount (e.g., 10,000)"
                        value="{{ $fmtMoney(old('lease_purchase_deposit', $formData->get('lease_purchase_deposit'))) }}">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Maintenance Responsibility</label>
                <select name="lease_purchase_maintenance" class="form-select">
                    <option value="">Select</option>
                    @foreach(['Seller','Tenant-Buyer','Shared'] as $mr)
                    <option value="{{ $mr }}" {{ old('lease_purchase_maintenance', $formData->get('lease_purchase_maintenance')) === $mr ? 'selected' : '' }}>{{ $mr }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Conditions / Requirements</label>
                <input type="text" name="lease_purchase_conditions" class="form-control"
                    placeholder="Enter conditions (e.g., Buyer must secure financing by lease end, Property must appraise at agreed value)"
                    value="{{ old('lease_purchase_conditions', $formData->get('lease_purchase_conditions')) }}">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Specific Terms</label>
                <input type="text" name="lease_purchase_terms" class="form-control"
                    placeholder="Enter specific terms (e.g., Right of first refusal if seller decides to sell)"
                    value="{{ old('lease_purchase_terms', $formData->get('lease_purchase_terms')) }}">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Extension Terms</label>
                <input type="text" name="lease_purchase_extension_terms" class="form-control"
                    placeholder="Enter extension terms (e.g., Lease may be extended 6 months with adjusted purchase price)"
                    value="{{ old('lease_purchase_extension_terms', $formData->get('lease_purchase_extension_terms')) }}">
            </div>
        </div>
    </div>

    {{-- NFT conditional sub-fields --}}
    <div x-show="finType === 'Non-Fungible Token (NFT)'" class="border rounded p-3 mb-3 bg-light">
        <h6 class="fw-semibold mb-3">Non-Fungible Token (NFT) Details</h6>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">NFT Description / Type</label>
                <input type="text" name="nft_description" class="form-control"
                    placeholder="Enter NFT type (e.g., Tokenized real estate, Digital artwork)"
                    value="{{ old('nft_description', $formData->get('nft_description')) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">% of Price Paid with NFT</label>
                <div class="input-group">
                    <input type="number" name="nft_percentage" class="form-control" min="0" max="100" step="1"
                        placeholder="Enter NFT percentage (e.g., 40)"
                        value="{{ old('nft_percentage', $formData->get('nft_percentage')) }}">
                    <span class="input-group-text">%</span>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">% to be Paid with Cash</label>
                <div class="input-group">
                    <input type="number" name="cash_percentage_nft" class="form-control" min="0" max="100" step="1"
                        placeholder="Enter cash percentage (e.g., 60)"
                        value="{{ old('cash_percentage_nft', $formData->get('cash_percentage_nft')) }}">
                    <span class="input-group-text">%</span>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">NFT Valuation Method</label>
                <input type="text" name="nft_valuation_method" class="form-control"
                    placeholder="Enter valuation method (e.g., Floor price on OpenSea, Independent appraisal)"
                    value="{{ old('nft_valuation_method', $formData->get('nft_valuation_method')) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">NFT Transfer Method</label>
                <input type="text" name="nft_transfer_method" class="form-control"
                    placeholder="Enter transfer method (e.g., MetaMask, OpenSea, Propy Title, Escrow smart contract)"
                    value="{{ old('nft_transfer_method', $formData->get('nft_transfer_method')) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Gas Fees Responsibility</label>
                <select name="nft_gas_fees" class="form-select">
                    <option value="">Select</option>
                    @foreach(['Buyer','Seller','Split'] as $gf)
                    <option value="{{ $gf }}" {{ old('nft_gas_fees', $formData->get('nft_gas_fees')) === $gf ? 'selected' : '' }}>{{ $gf }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Other Financing conditional sub-fields --}}
    <div x-show="finType === 'Other'" class="border rounded p-3 mb-3 bg-light">
        <h6 class="fw-semibold mb-3">Other Financing Details</h6>
        <div class="mb-2">
            <input type="text" name="other_financing_details" class="form-control"
                placeholder="Enter financing details (e.g., Gold bullion, Stock transfer, Private investment agreement)"
                value="{{ old('other_financing_details', $formData->get('other_financing_details')) }}">
        </div>
    </div>

    {{-- ── Section 3: Contingencies ── --}}
    <h6 class="offer-section-header">Contingencies</h6>
    <div class="contingency-group mb-3">
        {{-- Financing Contingency --}}
        <div class="contingency-item">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="fin_cont_{{ $mode }}" name="financing_contingency"
                    value="1" x-model="finCont"
                    {{ old('financing_contingency', $formData->get('financing_contingency')) ? 'checked' : '' }}>
                <label class="form-check-label fw-semibold" for="fin_cont_{{ $mode }}">
                    Financing Contingency
                    <span class="ms-1" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Makes your offer contingent on obtaining mortgage financing. Protects you if your loan approval falls through within the stated period.">
                        <i class="fa-solid fa-circle-info" style="color:#2563eb;cursor:pointer;font-size:0.8rem;"></i>
                    </span>
                </label>
            </div>
            <div x-show="finCont" class="contingency-days">
                <label class="form-label small mb-1">Contingency Period (days)</label>
                <input type="number" name="financing_contingency_days" class="form-control" min="1" max="365"
                    placeholder="Enter days (e.g., 21)" style="max-width: 450px; min-width: 18rem;"
                    value="{{ old('financing_contingency_days', $formData->get('financing_contingency_days')) }}">
            </div>
        </div>

        {{-- Inspection Contingency --}}
        <div class="contingency-item">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="insp_cont_{{ $mode }}" name="inspection_contingency"
                    value="1" x-model="inspCont"
                    {{ old('inspection_contingency', $formData->get('inspection_contingency')) ? 'checked' : '' }}>
                <label class="form-check-label fw-semibold" for="insp_cont_{{ $mode }}">
                    Inspection Contingency
                    <span class="ms-1" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Allows you to negotiate repairs or withdraw your offer based on inspection findings within the stated period.">
                        <i class="fa-solid fa-circle-info" style="color:#2563eb;cursor:pointer;font-size:0.8rem;"></i>
                    </span>
                </label>
            </div>
            <div x-show="inspCont" class="contingency-days">
                <label class="form-label small mb-1">Inspection Period (days)</label>
                <input type="number" name="inspection_contingency_days" class="form-control" min="1" max="365"
                    placeholder="Enter days (e.g., 7)" style="max-width: 450px; min-width: 18rem;"
                    value="{{ old('inspection_contingency_days', $formData->get('inspection_contingency_days')) }}">
            </div>
        </div>

        {{-- Appraisal Contingency --}}
        <div class="contingency-item">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="appr_cont_{{ $mode }}" name="appraisal_contingency"
                    value="1" x-model="apprCont"
                    {{ old('appraisal_contingency', $formData->get('appraisal_contingency')) ? 'checked' : '' }}>
                <label class="form-check-label fw-semibold" for="appr_cont_{{ $mode }}">
                    Appraisal Contingency
                    <span class="ms-1" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Protects you if the property appraises below the agreed purchase price, allowing you to renegotiate or withdraw.">
                        <i class="fa-solid fa-circle-info" style="color:#2563eb;cursor:pointer;font-size:0.8rem;"></i>
                    </span>
                </label>
            </div>
            <div x-show="apprCont" class="contingency-days">
                <label class="form-label small mb-1">Appraisal Period (days)</label>
                <input type="number" name="appraisal_contingency_days" class="form-control" min="1" max="365"
                    placeholder="Enter days (e.g., 15)" style="max-width: 450px; min-width: 18rem;"
                    value="{{ old('appraisal_contingency_days', $formData->get('appraisal_contingency_days')) }}">
            </div>
        </div>

        {{-- Sale of Buyer Property Contingency --}}
        <div class="contingency-item">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="sale_cont_{{ $mode }}" name="sale_of_buyer_property_contingency"
                    value="1" x-model="saleCont"
                    {{ old('sale_of_buyer_property_contingency', $formData->get('sale_of_buyer_property_contingency')) ? 'checked' : '' }}>
                <label class="form-check-label fw-semibold" for="sale_cont_{{ $mode }}">
                    Sale of Buyer's Property Contingency
                    <span class="ms-1" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Makes your offer contingent on the successful sale of your current home within the stated period.">
                        <i class="fa-solid fa-circle-info" style="color:#2563eb;cursor:pointer;font-size:0.8rem;"></i>
                    </span>
                </label>
            </div>
            <div x-show="saleCont" class="contingency-days">
                <label class="form-label small mb-1">Contingency Period (days)</label>
                <input type="number" name="sale_of_buyer_property_contingency_days" class="form-control" min="1" max="365"
                    placeholder="Enter days (e.g., 30)" style="max-width: 450px; min-width: 18rem;"
                    value="{{ old('sale_of_buyer_property_contingency_days', $formData->get('sale_of_buyer_property_contingency_days')) }}">
            </div>
        </div>
    </div>

    {{-- ── Section 4: Closing & Possession ── --}}
    <h6 class="offer-section-header">Closing &amp; Possession</h6>
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <label class="form-label fw-semibold">
                Closing Date
                <span class="ms-1" data-bs-toggle="tooltip" data-bs-html="true"
                    title="The target date to complete the transaction and transfer ownership of the property.">
                    <i class="fa-solid fa-circle-info" style="color:#2563eb;cursor:pointer;font-size:0.85rem;"></i>
                </span>
            </label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-regular fa-calendar"></i></span>
                <input type="date" name="closing_date" class="form-control"
                    value="{{ old('closing_date', ($v = $formData->get('closing_date')) ? $safeDate($v) : '') }}">
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">
                Possession Date
                <span class="ms-1" data-bs-toggle="tooltip" data-bs-html="true"
                    title="The date you expect to take physical possession of the property.">
                    <i class="fa-solid fa-circle-info" style="color:#2563eb;cursor:pointer;font-size:0.85rem;"></i>
                </span>
            </label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-regular fa-calendar"></i></span>
                <input type="date" name="possession_date" class="form-control"
                    value="{{ old('possession_date', ($v = $formData->get('possession_date')) ? $safeDate($v) : '') }}">
            </div>
        </div>
    </div>

    {{-- Possession Notes --}}
    <div class="mb-3">
        <label class="form-label fw-semibold">Possession Notes <span class="text-muted small">(optional)</span></label>
        <textarea name="possession_notes" class="form-control" rows="3"
            placeholder="Enter possession details or special arrangements (e.g., Possession at closing, Delayed possession, Early occupancy request)">{{ old('possession_notes', $formData->get('possession_notes')) }}</textarea>
    </div>

    {{-- ── Section 5: Additional Terms ── --}}
    <h6 class="offer-section-header">Additional Terms</h6>

    {{-- Seller Contribution --}}
    <div class="mb-3">
        <label class="form-label fw-semibold">
            Seller Contribution Requested
            <span class="ms-1" data-bs-toggle="tooltip" data-bs-html="true"
                title="Request the seller to contribute toward your closing costs or other buyer expenses as part of the offer.">
                <i class="fa-solid fa-circle-info" style="color:#2563eb;cursor:pointer;font-size:0.85rem;"></i>
            </span>
        </label>
        <select name="seller_contribution_requested" class="form-select w-auto" x-model="sellerContrib">
            <option value="">Select</option>
            <option value="Yes" {{ old('seller_contribution_requested', $formData->get('seller_contribution_requested')) === 'Yes' ? 'selected' : '' }}>Yes</option>
            <option value="No" {{ old('seller_contribution_requested', $formData->get('seller_contribution_requested')) === 'No' ? 'selected' : '' }}>No</option>
        </select>
        <div x-show="sellerContrib === 'Yes'" class="mt-2">
            <input type="text" name="seller_contribution_details" class="form-control"
                placeholder="Enter contribution details (e.g., $5,000 toward buyer closing costs)"
                value="{{ old('seller_contribution_details', $formData->get('seller_contribution_details')) }}">
        </div>
    </div>

    {{-- Home Warranty --}}
    <div class="mb-3">
        <label class="form-label fw-semibold">
            Home Warranty Requested
            <span class="ms-1" data-bs-toggle="tooltip" data-bs-html="true"
                title="Request a home warranty policy to cover appliances and systems for a period after closing.">
                <i class="fa-solid fa-circle-info" style="color:#2563eb;cursor:pointer;font-size:0.85rem;"></i>
            </span>
        </label>
        <select name="home_warranty_requested" class="form-select w-auto" x-model="homeWarranty">
            <option value="">Select</option>
            <option value="Yes" {{ old('home_warranty_requested', $formData->get('home_warranty_requested')) === 'Yes' ? 'selected' : '' }}>Yes</option>
            <option value="No" {{ old('home_warranty_requested', $formData->get('home_warranty_requested')) === 'No' ? 'selected' : '' }}>No</option>
        </select>
        <div x-show="homeWarranty === 'Yes'" class="mt-2">
            <input type="text" name="home_warranty_details" class="form-control"
                placeholder="Enter warranty details (e.g., $500 one-year home warranty through American Home Shield)"
                value="{{ old('home_warranty_details', $formData->get('home_warranty_details')) }}">
        </div>
    </div>

    {{-- Included Personal Property --}}
    <div class="mb-3">
        <label class="form-label fw-semibold">Included Personal Property</label>
        <input type="text" name="included_personal_property" class="form-control"
            placeholder="Enter included items (e.g., Refrigerator, Washer/dryer, Dining room chandelier)"
            value="{{ old('included_personal_property', $formData->get('included_personal_property')) }}">
    </div>

    {{-- Excluded Items --}}
    <div class="mb-3">
        <label class="form-label fw-semibold">Excluded Items</label>
        <input type="text" name="excluded_items" class="form-control"
            placeholder="Enter excluded items (e.g., Antique light fixture in dining room, Detached storage shed)"
            value="{{ old('excluded_items', $formData->get('excluded_items')) }}">
    </div>

    </div>{{-- end x-data --}}
    @endif

    {{-- Rental/Lease-specific fields --}}
    @if(in_array($offerType, ['rental', 'lease']))
    {{-- ── Section: Pre-Screening Information ── --}}
    <h6 class="offer-section-header">Pre-Screening Information</h6>
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <label class="form-label fw-semibold">Number of Occupants</label>
            <input type="number" name="num_occupants" class="form-control" min="1" max="99"
                placeholder="Enter number of occupants (e.g., 2)"
                value="{{ old('num_occupants', $formData->get('num_occupants')) }}">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Pets</label>
            <select name="has_pets" class="form-select"
                x-data x-model="$el.value"
                @change="$dispatch('rental-pets-changed', {val: $el.value})">
                <option value="">Select</option>
                @foreach(['Yes', 'No', 'Negotiable'] as $_po)
                <option value="{{ $_po }}" {{ old('has_pets', $formData->get('has_pets')) === $_po ? 'selected' : '' }}>{{ $_po }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6"
            x-data="{ show: {{ in_array(old('has_pets', $formData->get('has_pets')), ['Yes','Negotiable']) ? 'true' : 'false' }} }"
            @rental-pets-changed.window="show = ($event.detail.val === 'Yes' || $event.detail.val === 'Negotiable')">
            <label class="form-label fw-semibold" x-show="show">Pet Details</label>
            <input type="text" name="pet_details" class="form-control"
                placeholder="Enter pet details (e.g., One dog, House-trained, 35 lbs)"
                x-show="show"
                value="{{ old('pet_details', $formData->get('pet_details')) }}">
            <span x-show="!show" class="text-muted small">—</span>
        </div>
    </div>
    {{-- ── Screening Concerns ── --}}
    <div class="row g-3 mb-3"
        x-data="{ showConcerns: '{{ old('screening_concerns', $formData->get('screening_concerns')) }}' === 'Yes' }"
        @change="showConcerns = ($el.querySelector('[name=screening_concerns]') ? $el.querySelector('[name=screening_concerns]').value === 'Yes' : showConcerns)">
        <div class="col-md-4">
            <label class="form-label fw-semibold">Screening Concerns That May Affect Rental Approval</label>
            <select name="screening_concerns" class="form-select"
                x-data x-model="$el.value"
                @change="$dispatch('rental-concerns-changed', {val: $el.value})">
                <option value="">Select</option>
                <option value="Yes" {{ old('screening_concerns', $formData->get('screening_concerns')) === 'Yes' ? 'selected' : '' }}>Yes</option>
                <option value="No" {{ old('screening_concerns', $formData->get('screening_concerns')) === 'No' ? 'selected' : '' }}>No</option>
            </select>
        </div>
        <div class="col-md-8"
            x-data="{ show: {{ old('screening_concerns', $formData->get('screening_concerns')) === 'Yes' ? 'true' : 'false' }} }"
            @rental-concerns-changed.window="show = ($event.detail.val === 'Yes')">
            <label class="form-label fw-semibold" x-show="show">Screening Concern Details</label>
            <textarea name="screening_concerns_details" class="form-control" rows="3"
                x-show="show"
                placeholder="Enter screening concerns (e.g., Low credit, Prior eviction, Background check issues)">{{ old('screening_concerns_details', $formData->get('screening_concerns_details')) }}</textarea>
        </div>
    </div>{{-- end screening concerns row --}}

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <label class="form-label fw-semibold">Smoking</label>
            <select name="smoking_preference" class="form-select">
                <option value="">Select</option>
                <option value="No" {{ old('smoking_preference', $formData->get('smoking_preference')) === 'No' ? 'selected' : '' }}>Non-smoker</option>
                <option value="Yes" {{ old('smoking_preference', $formData->get('smoking_preference')) === 'Yes' ? 'selected' : '' }}>Smoker</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Est. Monthly Net Income</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                <input type="text" inputmode="numeric" name="monthly_income" class="form-control" data-money-input="true"
                    placeholder="Enter estimated monthly net income (e.g., 6,000)"
                    value="{{ $fmtMoney(old('monthly_income', $formData->get('monthly_income'))) }}">
            </div>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Credit Score Range</label>
            <input type="text" name="credit_score_range" class="form-control"
                placeholder="Enter your credit score range (e.g., 720–750)"
                value="{{ old('credit_score_range', $formData->get('credit_score_range')) }}">
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label fw-semibold">About Yourself <span class="text-muted small">(optional)</span></label>
        <textarea name="screening_notes" class="form-control" rows="3"
            placeholder="Enter a brief introduction about yourself (e.g., Employed 3+ years, Excellent rental history)">{{ old('screening_notes', $formData->get('screening_notes')) }}</textarea>
    </div>

    {{-- ── Section: Rental Application & Lease Terms ── --}}
    <h6 class="offer-section-header">Rental Application &amp; Lease Terms</h6>
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <label class="form-label fw-semibold">Proposed Monthly Rent</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                <input type="text" inputmode="numeric" name="monthly_rent" class="form-control" data-money-input="true"
                    placeholder="Enter your proposed monthly rent (e.g., 2,200)"
                    value="{{ $fmtMoney(old('monthly_rent', $formData->get('monthly_rent'))) }}">
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Proposed Security Deposit</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                <input type="text" inputmode="numeric" name="security_deposit" class="form-control" data-money-input="true"
                    placeholder="Enter your proposed security deposit (e.g., 2,200)"
                    value="{{ $fmtMoney(old('security_deposit', $formData->get('security_deposit'))) }}">
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Proposed Lease Length (Months)</label>
            <input type="number" name="lease_term_months" class="form-control" min="1" max="360"
                placeholder="Enter lease length in months (e.g., 12)"
                value="{{ old('lease_term_months', $formData->get('lease_term_months')) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Move-in / Lease Start Date</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-regular fa-calendar"></i></span>
                <input type="date" name="move_in_date" class="form-control"
                    value="{{ old('move_in_date', ($v = $formData->get('move_in_date')) ? $safeDate($v) : '') }}">
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Last Month's Rent Offered</label>
            <select name="last_month_rent_offered" class="form-select">
                <option value="">Select</option>
                <option value="Yes" {{ old('last_month_rent_offered', $formData->get('last_month_rent_offered')) === 'Yes' ? 'selected' : '' }}>Yes</option>
                <option value="No" {{ old('last_month_rent_offered', $formData->get('last_month_rent_offered')) === 'No' ? 'selected' : '' }}>No</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Total Move-in Funds Available</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                <input type="text" inputmode="numeric" name="move_in_funds" class="form-control" data-money-input="true"
                    placeholder="Enter total move-in funds available (e.g., 6,600)"
                    value="{{ $fmtMoney(old('move_in_funds', $formData->get('move_in_funds'))) }}">
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Utilities Responsibility</label>
            <input type="text" name="utilities_terms" class="form-control"
                placeholder="Enter utilities arrangement (e.g., Tenant pays electric and gas; landlord pays water)"
                value="{{ old('utilities_terms', $formData->get('utilities_terms')) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Maintenance Responsibility</label>
            <select name="maintenance_responsibility" class="form-select">
                <option value="">Select</option>
                @foreach(['Landlord', 'Tenant', 'Shared'] as $_mr)
                <option value="{{ $_mr }}" {{ old('maintenance_responsibility', $formData->get('maintenance_responsibility')) === $_mr ? 'selected' : '' }}>{{ $_mr }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Parking Terms <span class="text-muted small">(optional)</span></label>
            <input type="text" name="parking_terms" class="form-control"
                placeholder="Enter parking terms (e.g., 1 covered spot included)"
                value="{{ old('parking_terms', $formData->get('parking_terms')) }}">
        </div>
    </div>
    {{-- ── Section: Additional Terms ── --}}
    <h6 class="offer-section-header">Additional Terms</h6>
    <div class="mb-3">
        <label class="form-label fw-semibold">Additional Lease Terms / Requests <span class="text-muted small">(optional)</span></label>
        <textarea name="additional_lease_terms" class="form-control" rows="3"
            placeholder="Enter any additional terms or requests (e.g., Request to install EV charger, Month-to-month option after initial term)">{{ old('additional_lease_terms', $formData->get('additional_lease_terms')) }}</textarea>
    </div>
    <div class="mb-3">
        <label class="form-label fw-semibold">Additional Message to Landlord <span class="text-muted small">(optional)</span></label>
        <textarea name="message_to_landlord" class="form-control" rows="3"
            placeholder="Enter any additional message for the landlord (e.g., Available for a showing any weekday evening)">{{ old('message_to_landlord', $formData->get('message_to_landlord')) }}</textarea>
    </div>
    @endif

    {{-- ── Section 6: Additional Terms & Response Deadline ── --}}
    <h6 class="offer-section-header">Additional Terms &amp; Response Deadline</h6>
    <div class="mb-3">
        <label class="form-label fw-semibold">Custom Terms / Special Conditions</label>
        <textarea name="custom_terms" class="form-control" rows="4"
            placeholder="Enter any special conditions or custom terms">{{ old('custom_terms', $formData->get('custom_terms')) }}</textarea>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <label class="form-label fw-semibold">
                Response Requested By
                <span class="ms-1" data-bs-toggle="tooltip" data-bs-html="true"
                    title="The date by which the landlord should respond to this application. After this date the offer will be considered withdrawn.">
                    <i class="fa-solid fa-circle-info" style="color:#2563eb;cursor:pointer;font-size:0.85rem;"></i>
                </span>
            </label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-regular fa-calendar"></i></span>
                <input type="date" name="expires_at" class="form-control"
                    value="{{ old('expires_at', ($v = $formData->get('expires_at')) ? $safeDate($v) : '') }}">
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <button type="submit" id="{{ $submitBtnId }}" class="btn btn-primary px-4">{{ $submitLabel }}</button>
        @if($mode === 'draft_terms')
        <button type="submit"
                formaction="{{ route('offers.submit', $offer) }}"
                id="save-and-submit-offer-btn"
                class="btn btn-success px-4"
                onclick="return confirm('This will save your terms and submit the offer. Continue?')">
            Save &amp; Submit Offer
        </button>
        @endif
    </div>
</form>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            new bootstrap.Tooltip(el, { trigger: 'hover focus', container: 'body' });
        });
    });
</script>
<script>
    // Money input helpers — scoped to the offer terms form
    function offerTermsIsPercentMode(input) {
        var selName = input.getAttribute('data-unit-select');
        if (!selName) return false;
        var sel = document.querySelector('select[name="' + selName + '"]');
        return sel && sel.value === '%';
    }

    function formatWithCommas(input) {
        if (offerTermsIsPercentMode(input)) return;
        var value = input.value.replace(/[^\d.]/g, '');
        var parts = value.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        input.value = parts.length > 1 ? parts[0] + '.' + parts[1].substring(0, 2) : parts[0];
    }

    function validateInput(input) {
        if (offerTermsIsPercentMode(input)) return;
        var v = input.value;
        v = v.replace(/[^0-9.,]/g, '');
        var firstDot = v.indexOf('.');
        if (firstDot !== -1) {
            v = v.slice(0, firstDot + 1) + v.slice(firstDot + 1).replace(/\./g, '');
        }
        input.value = v;
    }

    function reformatNumber(input) {
        if (offerTermsIsPercentMode(input)) return;
        var v = input.value.replace(/,/g, '');
        var parts = v.split('.');
        var intPart = parts[0] || '';
        var decPart = parts[1] || '';
        if (decPart) decPart = decPart.slice(0, 2);
        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        input.value = decPart ? (intPart + '.' + decPart) : intPart;
    }

    function handlePaste(event) {
        if (offerTermsIsPercentMode(event.target)) return;
        event.preventDefault();
        var paste = (event.clipboardData || window.clipboardData).getData('text');
        var clean = paste.replace(/[^0-9.]/g, '');
        var parts = clean.split('.');
        if (parts.length > 2) {
            clean = parts[0] + '.' + parts.slice(1).join('');
        }
        var intPart = parts[0] || '';
        var decPart = parts[1] || '';
        if (decPart) decPart = decPart.slice(0, 2);
        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        event.target.value = decPart ? (intPart + '.' + decPart) : intPart;
        event.target.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function initOfferTermsMoneyInputs() {
        document.querySelectorAll('[data-money-input="true"]').forEach(function (inp) {
            if (!inp.dataset.moneyBound) {
                inp.dataset.moneyBound = '1';
                inp.addEventListener('input', function () { formatWithCommas(this); });
                inp.addEventListener('blur', function () { reformatNumber(this); });
                inp.addEventListener('paste', handlePaste);
            }
            if (inp.value && !offerTermsIsPercentMode(inp)) { reformatNumber(inp); }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initOfferTermsMoneyInputs();

        var form = document.getElementById('{{ $formId }}');
        if (form) {
            form.addEventListener('submit', function () {
                form.querySelectorAll('[data-money-input="true"]').forEach(function (inp) {
                    if (!offerTermsIsPercentMode(inp)) {
                        inp.value = inp.value.replace(/,/g, '');
                    }
                });
            });
        }
    });
</script>
