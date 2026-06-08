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

            {{-- Success Flash --}}
            @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
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
                    <style>
                        #save-offer-terms-btn { background:#2563eb; border-color:#2563eb; color:#fff; font-weight:600; }
                        #save-offer-terms-btn:hover { background:#1d4ed8; border-color:#1d4ed8; }
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
                    <form method="POST" action="{{ route('offers.terms', $offer) }}">
                        @csrf

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
                            $_ft     = old('financing_type',              $metas->get('financing_type') ?? '');
                            $_initTf = old('initial_deposit_timeframe',   $metas->get('initial_deposit_timeframe') ?? '');
                            $_addTf  = old('additional_deposit_timeframe',$metas->get('additional_deposit_timeframe') ?? '');
                        @endphp
                        @php
                            $_downPayVal      = old('down_payment_value',  $metas->get('down_payment_value')  ?? $metas->get('down_payment_percent') ?? '');
                            $_downPayUnit     = old('down_payment_unit',   $metas->get('down_payment_unit')   ?? '%');
                            $_earnestDepUnit  = old('earnest_deposit_unit',         $metas->get('earnest_deposit_unit')         ?? '$');
                            $_initDepUnit     = old('initial_deposit_amount_unit',  $metas->get('initial_deposit_amount_unit')  ?? '$');
                            $_addDepUnit      = old('additional_deposit_amount_unit',$metas->get('additional_deposit_amount_unit') ?? '$');
                            $_sfDpType        = old('sf_down_payment_type',         $metas->get('sf_down_payment_type')         ?? '$');
                            $_sfAmountType    = old('seller_financing_amount_type', $metas->get('seller_financing_amount_type') ?? '$');
                            $fmtMoney = function($v) {
                                if ($v === null || $v === '') return '';
                                $clean = str_replace(',', '', (string) $v);
                                return is_numeric($clean) ? number_format((int) $clean) : (string) $v;
                            };
                        @endphp
                        <div x-data="{
                            finType: '{{ $_ft }}',
                            finCont: {{ old('financing_contingency', $metas->get('financing_contingency')) ? 'true' : 'false' }},
                            inspCont: {{ old('inspection_contingency', $metas->get('inspection_contingency')) ? 'true' : 'false' }},
                            apprCont: {{ old('appraisal_contingency', $metas->get('appraisal_contingency')) ? 'true' : 'false' }},
                            saleCont: {{ old('sale_of_buyer_property_contingency', $metas->get('sale_of_buyer_property_contingency')) ? 'true' : 'false' }},
                            sellerContrib: '{{ old('seller_contribution_requested', $metas->get('seller_contribution_requested') ?? '') }}',
                            homeWarranty: '{{ old('home_warranty_requested', $metas->get('home_warranty_requested') ?? '') }}',
                            initTf: '{{ $_initTf }}',
                            addTf: '{{ $_addTf }}',
                            earnestDepUnit: '{{ old('earnest_deposit_unit', $metas->get('earnest_deposit_unit') ?? '$') }}',
                            initDepUnit: '{{ old('initial_deposit_amount_unit', $metas->get('initial_deposit_amount_unit') ?? '$') }}',
                            addDepUnit: '{{ old('additional_deposit_amount_unit', $metas->get('additional_deposit_amount_unit') ?? '$') }}',
                            downPayUnit: '{{ $_downPayUnit }}',
                            sfBalloon: '{{ old('seller_financing_balloon', $metas->get('seller_financing_balloon') ?? '') }}',
                            sfAmortType: '{{ old('seller_financing_amortization', $metas->get('seller_financing_amortization') ?? '') }}',
                            sfPayFreq: '{{ old('seller_financing_payment_frequency', $metas->get('seller_financing_payment_frequency') ?? '') }}',
                            sfAmountType: '{{ old('seller_financing_amount_type', $metas->get('seller_financing_amount_type') ?? '$') }}',
                            sfDpType: '{{ old('sf_down_payment_type', $metas->get('sf_down_payment_type') ?? '$') }}',
                            sfPrePayPenalty: '{{ old('prepayment_penalty', $metas->get('prepayment_penalty') ?? '') }}',
                            exchangeItemType: '{{ old('exchange_item', $metas->get('exchange_item') ?? '') }}',
                            exchangeLiens: '{{ old('exchange_liens', $metas->get('exchange_liens') ?? '') }}',
                            leaseOptFee: '{{ old('has_option_fee', $metas->get('has_option_fee') ?? '') }}',
                            leaseOptFeeCredit: '{{ old('lease_option_fee_credit', $metas->get('lease_option_fee_credit') ?? '') }}',
                            leasePurchRentCredit: '{{ old('lease_purchase_rent_credit', $metas->get('lease_purchase_rent_credit') ?? '') }}'
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
                                        value="{{ $fmtMoney(old('offer_price', $metas->get('offer_price'))) }}">
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
                                        value="{{ $_earnestDepUnit !== '%' ? $fmtMoney(old('earnest_deposit', $metas->get('earnest_deposit'))) : old('earnest_deposit', $metas->get('earnest_deposit')) }}">
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
                                        value="{{ $_initDepUnit !== '%' ? $fmtMoney(old('initial_deposit_amount', $metas->get('initial_deposit_amount'))) : old('initial_deposit_amount', $metas->get('initial_deposit_amount')) }}">
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
                                        value="{{ old('initial_deposit_timeframe_other', $metas->get('initial_deposit_timeframe_other')) }}">
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
                                        value="{{ $_addDepUnit !== '%' ? $fmtMoney(old('additional_deposit_amount', $metas->get('additional_deposit_amount'))) : old('additional_deposit_amount', $metas->get('additional_deposit_amount')) }}">
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
                                        value="{{ old('additional_deposit_timeframe_other', $metas->get('additional_deposit_timeframe_other')) }}">
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
                        <div x-show="finType === 'Assumable'" class="border rounded p-3 mb-3 bg-light">
                            <h6 class="fw-semibold mb-3">Assumable Details</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Assumable Loan Terms</label>
                                    <input type="text" name="assumable_terms" class="form-control"
                                        placeholder="Enter loan terms (e.g., $250,000 remaining at 4.25% fixed for 20 years)"
                                        value="{{ old('assumable_terms', $metas->get('assumable_terms')) }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Loan Type</label>
                                    <select name="assumable_loan_type" class="form-select">
                                        <option value="">Select</option>
                                        @foreach(['FHA', 'VA', 'USDA'] as $lt)
                                        <option value="{{ $lt }}" {{ old('assumable_loan_type', $metas->get('assumable_loan_type')) === $lt ? 'selected' : '' }}>{{ $lt }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Interest Rate (%)</label>
                                    <div class="input-group">
                                        <input type="number" name="assumable_interest_rate" class="form-control" min="0" max="100" step="0.01"
                                            placeholder="Enter rate (e.g., 4.25)"
                                            value="{{ old('assumable_interest_rate', $metas->get('assumable_interest_rate')) }}">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Outstanding Balance ($)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                                        <input type="text" inputmode="numeric" name="outstanding_balance" class="form-control" data-money-input="true"
                                            placeholder="Enter balance (e.g., 250,000)"
                                            value="{{ $fmtMoney(old('outstanding_balance', $metas->get('outstanding_balance'))) }}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Loan Term Remaining</label>
                                    <input type="text" name="assumable_loan_term_remaining" class="form-control"
                                        placeholder="Enter term remaining (e.g., 25 years)"
                                        value="{{ old('assumable_loan_term_remaining', $metas->get('assumable_loan_term_remaining')) }}">
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
                                        value="{{ old('cryptocurrency_type', $metas->get('cryptocurrency_type')) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">% of Price Paid with Crypto</label>
                                    <div class="input-group">
                                        <input type="number" name="crypto_percentage" class="form-control" min="0" max="100" step="1"
                                            placeholder="Enter percentage (e.g., 50)"
                                            value="{{ old('crypto_percentage', $metas->get('crypto_percentage')) }}">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Exchange / Conversion Method</label>
                                    <input type="text" name="crypto_exchange_method" class="form-control"
                                        placeholder="Enter method (e.g., Spot price at closing via Coinbase)"
                                        value="{{ old('crypto_exchange_method', $metas->get('crypto_exchange_method')) }}">
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
                                        <option value="{{ $ei }}" {{ old('exchange_item', $metas->get('exchange_item')) === $ei ? 'selected' : '' }}>{{ $ei }}</option>
                                        @endforeach
                                    </select>
                                    <div x-show="exchangeItemType === 'Other'" class="mt-2">
                                        <input type="text" name="other_exchange_item" class="form-control"
                                            placeholder="Enter item (e.g., Private Jet, Yacht)"
                                            value="{{ old('other_exchange_item', $metas->get('other_exchange_item')) }}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Estimated Value ($)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                                        <input type="text" inputmode="numeric" name="exchange_item_value" class="form-control" data-money-input="true"
                                            placeholder="Enter value (e.g., 75,000)"
                                            value="{{ $fmtMoney(old('exchange_item_value', $metas->get('exchange_item_value'))) }}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Condition of Item</label>
                                    <select name="exchange_item_condition" class="form-select">
                                        <option value="">Select</option>
                                        @foreach(['New','Like New','Excellent','Very Good','Good','Fair','Repair','Salvage Condition'] as $cond)
                                        <option value="{{ $cond }}" {{ old('exchange_item_condition', $metas->get('exchange_item_condition')) === $cond ? 'selected' : '' }}>{{ $cond }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Additional Cash Offered ($)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                                        <input type="text" inputmode="numeric" name="additional_cash" class="form-control" data-money-input="true"
                                            placeholder="Enter additional cash offered (e.g., 25,000)"
                                            value="{{ $fmtMoney(old('additional_cash', $metas->get('additional_cash'))) }}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Value Determined By</label>
                                    <input type="text" name="value_determination" class="form-control"
                                        placeholder="Enter valuation method (e.g., Licensed appraisal, Online valuation)"
                                        value="{{ old('value_determination', $metas->get('value_determination')) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Transfer Method / Logistics</label>
                                    <input type="text" name="exchange_transfer_method" class="form-control"
                                        placeholder="Enter transfer method (e.g., Title transfer, Bill of sale, Delivery at closing)"
                                        value="{{ old('exchange_transfer_method', $metas->get('exchange_transfer_method')) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Liens / Encumbrances?</label>
                                    <select name="exchange_liens" class="form-select" x-model="exchangeLiens">
                                        <option value="">Select</option>
                                        <option value="Yes" {{ old('exchange_liens', $metas->get('exchange_liens')) === 'Yes' ? 'selected' : '' }}>Yes</option>
                                        <option value="No" {{ old('exchange_liens', $metas->get('exchange_liens')) === 'No' ? 'selected' : '' }}>No</option>
                                    </select>
                                    <div x-show="exchangeLiens === 'Yes'" class="mt-2">
                                        <input type="text" name="exchange_liens_details" class="form-control"
                                            placeholder="Enter lien details (e.g., Auto loan balance, UCC filing)"
                                            value="{{ old('exchange_liens_details', $metas->get('exchange_liens_details')) }}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Inspection / Verification Rights</label>
                                    <select name="exchange_inspection_rights" class="form-select">
                                        <option value="">Select</option>
                                        <option value="Yes" {{ old('exchange_inspection_rights', $metas->get('exchange_inspection_rights')) === 'Yes' ? 'selected' : '' }}>Yes</option>
                                        <option value="No" {{ old('exchange_inspection_rights', $metas->get('exchange_inspection_rights')) === 'No' ? 'selected' : '' }}>No</option>
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
                                            value="{{ $fmtMoney(old('sf_purchase_price', $metas->get('sf_purchase_price'))) }}">
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
                                            value="{{ $_sfDpType !== '%' ? $fmtMoney(old('sf_down_payment_amount', $metas->get('sf_down_payment_amount'))) : old('sf_down_payment_amount', $metas->get('sf_down_payment_amount')) }}">
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
                                            value="{{ $_sfAmountType !== '%' ? $fmtMoney(old('seller_financing_amount', $metas->get('seller_financing_amount'))) : old('seller_financing_amount', $metas->get('seller_financing_amount')) }}">
                                        <span class="input-group-text" x-show="sfAmountType === '%'">%</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Proposed Interest Rate (%)</label>
                                    <div class="input-group">
                                        <input type="number" name="seller_financing_rate" class="form-control" min="0" max="100" step="0.01"
                                            placeholder="Enter rate (e.g., 6.5)"
                                            value="{{ old('seller_financing_rate', $metas->get('seller_financing_rate')) }}">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Loan Term</label>
                                    <input type="text" name="seller_financing_term" class="form-control"
                                        placeholder="Enter loan term (e.g., 30 years)"
                                        value="{{ old('seller_financing_term', $metas->get('seller_financing_term')) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Amortization Type</label>
                                    <select name="seller_financing_amortization" class="form-select" x-model="sfAmortType">
                                        <option value="">Select</option>
                                        <option value="Fully Amortizing" {{ old('seller_financing_amortization', $metas->get('seller_financing_amortization')) === 'Fully Amortizing' ? 'selected' : '' }}>Fully Amortizing</option>
                                        <option value="Interest-Only" {{ old('seller_financing_amortization', $metas->get('seller_financing_amortization')) === 'Interest-Only' ? 'selected' : '' }}>Interest-Only</option>
                                        <option value="Other" {{ old('seller_financing_amortization', $metas->get('seller_financing_amortization')) === 'Other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                    <div x-show="sfAmortType === 'Other'" class="mt-2">
                                        <input type="text" name="seller_financing_amortization_other" class="form-control"
                                            placeholder="Enter custom amortization type (e.g., Graduated Payments)"
                                            value="{{ old('seller_financing_amortization_other', $metas->get('seller_financing_amortization_other')) }}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Payment Frequency</label>
                                    <select name="seller_financing_payment_frequency" class="form-select" x-model="sfPayFreq">
                                        <option value="">Select</option>
                                        @foreach(['Monthly','Bi-Weekly','Quarterly','Annually','Other'] as $pf)
                                        <option value="{{ $pf }}" {{ old('seller_financing_payment_frequency', $metas->get('seller_financing_payment_frequency')) === $pf ? 'selected' : '' }}>{{ $pf }}</option>
                                        @endforeach
                                    </select>
                                    <div x-show="sfPayFreq === 'Other'" class="mt-2">
                                        <input type="text" name="seller_financing_payment_frequency_other" class="form-control"
                                            placeholder="Enter payment schedule (e.g., Semi-Annual)"
                                            value="{{ old('seller_financing_payment_frequency_other', $metas->get('seller_financing_payment_frequency_other')) }}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Balloon Payment</label>
                                    <select name="seller_financing_balloon" class="form-select" x-model="sfBalloon">
                                        <option value="">Select</option>
                                        <option value="Yes" {{ old('seller_financing_balloon', $metas->get('seller_financing_balloon')) === 'Yes' ? 'selected' : '' }}>Yes</option>
                                        <option value="No" {{ old('seller_financing_balloon', $metas->get('seller_financing_balloon')) === 'No' ? 'selected' : '' }}>No</option>
                                    </select>
                                    <div x-show="sfBalloon === 'Yes'" class="mt-2">
                                        <div class="input-group mb-1">
                                            <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                                            <input type="text" inputmode="numeric" name="seller_financing_balloon_amount" class="form-control" data-money-input="true"
                                                placeholder="Enter balloon amount (e.g., 100,000)"
                                                value="{{ $fmtMoney(old('seller_financing_balloon_amount', $metas->get('seller_financing_balloon_amount'))) }}">
                                        </div>
                                        <input type="text" name="seller_financing_balloon_date" class="form-control"
                                            placeholder="Enter due date (e.g., 5 Years)"
                                            value="{{ old('seller_financing_balloon_date', $metas->get('seller_financing_balloon_date')) }}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Prepayment Penalty</label>
                                    <select name="prepayment_penalty" class="form-select" x-model="sfPrePayPenalty">
                                        <option value="">Select</option>
                                        <option value="Yes" {{ old('prepayment_penalty', $metas->get('prepayment_penalty')) === 'Yes' ? 'selected' : '' }}>Yes</option>
                                        <option value="No" {{ old('prepayment_penalty', $metas->get('prepayment_penalty')) === 'No' ? 'selected' : '' }}>No</option>
                                    </select>
                                    <div x-show="sfPrePayPenalty === 'Yes'" class="mt-2">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                                            <input type="text" inputmode="numeric" name="prepayment_penalty_amount" class="form-control" data-money-input="true"
                                                placeholder="Enter penalty amount (e.g., 5,000)"
                                                value="{{ $fmtMoney(old('prepayment_penalty_amount', $metas->get('prepayment_penalty_amount'))) }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Late Payment Fee</label>
                                    <input type="text" name="seller_late_fee_amount" class="form-control"
                                        placeholder="Enter late payment fee (e.g., $100 after 10 days, or 5% after 15 days)"
                                        value="{{ old('seller_late_fee_amount', $metas->get('seller_late_fee_amount')) }}">
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
                                            value="{{ $fmtMoney(old('lease_option_price', $metas->get('lease_option_price'))) }}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Monthly Payment ($)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                                        <input type="text" inputmode="numeric" name="lease_option_payment" class="form-control" data-money-input="true"
                                            placeholder="Enter monthly payment (e.g., 2,500)"
                                            value="{{ $fmtMoney(old('lease_option_payment', $metas->get('lease_option_payment'))) }}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Lease Duration (Months)</label>
                                    <input type="number" name="lease_option_duration" class="form-control" min="1"
                                        placeholder="Enter duration in months (e.g., 12)"
                                        value="{{ old('lease_option_duration', $metas->get('lease_option_duration')) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Option Fee Offered?</label>
                                    <select name="has_option_fee" class="form-select" x-model="leaseOptFee">
                                        <option value="">Select</option>
                                        <option value="Yes" {{ old('has_option_fee', $metas->get('has_option_fee')) === 'Yes' ? 'selected' : '' }}>Yes</option>
                                        <option value="No" {{ old('has_option_fee', $metas->get('has_option_fee')) === 'No' ? 'selected' : '' }}>No</option>
                                    </select>
                                    <div x-show="leaseOptFee === 'Yes'" class="mt-2">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                                            <input type="text" inputmode="numeric" name="option_fee_amount" class="form-control" data-money-input="true"
                                                placeholder="Enter option fee amount (e.g., 15,000)"
                                                value="{{ $fmtMoney(old('option_fee_amount', $metas->get('option_fee_amount'))) }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Fee Credit Toward Price?</label>
                                    <select name="lease_option_fee_credit" class="form-select" x-model="leaseOptFeeCredit">
                                        <option value="">Select</option>
                                        <option value="Yes" {{ old('lease_option_fee_credit', $metas->get('lease_option_fee_credit')) === 'Yes' ? 'selected' : '' }}>Yes</option>
                                        <option value="No" {{ old('lease_option_fee_credit', $metas->get('lease_option_fee_credit')) === 'No' ? 'selected' : '' }}>No</option>
                                        <option value="Partial" {{ old('lease_option_fee_credit', $metas->get('lease_option_fee_credit')) === 'Partial' ? 'selected' : '' }}>Partial</option>
                                    </select>
                                    <div x-show="leaseOptFeeCredit === 'Partial'" class="mt-2">
                                        <input type="number" name="lease_option_fee_credit_pct" class="form-control" min="0" max="100"
                                            placeholder="Credit percentage (e.g., 50)"
                                            value="{{ old('lease_option_fee_credit_pct', $metas->get('lease_option_fee_credit_pct')) }}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Maintenance Responsibility</label>
                                    <select name="lease_option_maintenance" class="form-select">
                                        <option value="">Select</option>
                                        @foreach(['Seller','Tenant-Buyer','Shared'] as $mr)
                                        <option value="{{ $mr }}" {{ old('lease_option_maintenance', $metas->get('lease_option_maintenance')) === $mr ? 'selected' : '' }}>{{ $mr }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Conditions / Requirements</label>
                                    <input type="text" name="lease_option_conditions" class="form-control"
                                        placeholder="Enter conditions (e.g., Option exercisable after 12 months, Property must pass inspection)"
                                        value="{{ old('lease_option_conditions', $metas->get('lease_option_conditions')) }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Specific Terms</label>
                                    <input type="text" name="lease_option_terms" class="form-control"
                                        placeholder="Enter specific terms (e.g., Buyer may conduct inspections during lease term)"
                                        value="{{ old('lease_option_terms', $metas->get('lease_option_terms')) }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Extension Terms</label>
                                    <input type="text" name="lease_option_extension_terms" class="form-control"
                                        placeholder="Enter extension terms (e.g., Tenant-Buyer may extend for 6 months with additional $5,000 fee)"
                                        value="{{ old('lease_option_extension_terms', $metas->get('lease_option_extension_terms')) }}">
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
                                            value="{{ $fmtMoney(old('lease_purchase_price', $metas->get('lease_purchase_price'))) }}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Monthly Payment ($)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                                        <input type="text" inputmode="numeric" name="lease_purchase_payment" class="form-control" data-money-input="true"
                                            placeholder="Enter monthly payment (e.g., 5,000)"
                                            value="{{ $fmtMoney(old('lease_purchase_payment', $metas->get('lease_purchase_payment'))) }}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Lease Duration (Months)</label>
                                    <input type="number" name="lease_purchase_duration" class="form-control" min="1"
                                        placeholder="Enter duration in months (e.g., 12)"
                                        value="{{ old('lease_purchase_duration', $metas->get('lease_purchase_duration')) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Rent Credit Toward Price?</label>
                                    <select name="lease_purchase_rent_credit" class="form-select" x-model="leasePurchRentCredit">
                                        <option value="">Select</option>
                                        <option value="Yes" {{ old('lease_purchase_rent_credit', $metas->get('lease_purchase_rent_credit')) === 'Yes' ? 'selected' : '' }}>Yes</option>
                                        <option value="No" {{ old('lease_purchase_rent_credit', $metas->get('lease_purchase_rent_credit')) === 'No' ? 'selected' : '' }}>No</option>
                                        <option value="Partial" {{ old('lease_purchase_rent_credit', $metas->get('lease_purchase_rent_credit')) === 'Partial' ? 'selected' : '' }}>Partial</option>
                                    </select>
                                    <div x-show="leasePurchRentCredit === 'Yes' || leasePurchRentCredit === 'Partial'" class="mt-2">
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" name="lease_purchase_rent_credit_amount" class="form-control" min="0"
                                                placeholder="Rent credit amount per month (e.g., 500)"
                                                value="{{ old('lease_purchase_rent_credit_amount', $metas->get('lease_purchase_rent_credit_amount')) }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Non-Refundable Deposit ($)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                                        <input type="text" inputmode="numeric" name="lease_purchase_deposit" class="form-control" data-money-input="true"
                                            placeholder="Enter deposit amount (e.g., 10,000)"
                                            value="{{ $fmtMoney(old('lease_purchase_deposit', $metas->get('lease_purchase_deposit'))) }}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Maintenance Responsibility</label>
                                    <select name="lease_purchase_maintenance" class="form-select">
                                        <option value="">Select</option>
                                        @foreach(['Seller','Tenant-Buyer','Shared'] as $mr)
                                        <option value="{{ $mr }}" {{ old('lease_purchase_maintenance', $metas->get('lease_purchase_maintenance')) === $mr ? 'selected' : '' }}>{{ $mr }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Conditions / Requirements</label>
                                    <input type="text" name="lease_purchase_conditions" class="form-control"
                                        placeholder="Enter conditions (e.g., Buyer must secure financing by lease end, Property must appraise at agreed value)"
                                        value="{{ old('lease_purchase_conditions', $metas->get('lease_purchase_conditions')) }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Specific Terms</label>
                                    <input type="text" name="lease_purchase_terms" class="form-control"
                                        placeholder="Enter specific terms (e.g., Rent credits apply toward purchase, Option to buy after 12 months)"
                                        value="{{ old('lease_purchase_terms', $metas->get('lease_purchase_terms')) }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Extension Terms</label>
                                    <input type="text" name="lease_purchase_extension_terms" class="form-control"
                                        placeholder="Enter extension terms (e.g., Lease may be extended 6 months with adjusted purchase price)"
                                        value="{{ old('lease_purchase_extension_terms', $metas->get('lease_purchase_extension_terms')) }}">
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
                                        value="{{ old('nft_description', $metas->get('nft_description')) }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">% of Price Paid with NFT</label>
                                    <div class="input-group">
                                        <input type="number" name="nft_percentage" class="form-control" min="0" max="100" step="1"
                                            placeholder="Enter NFT percentage (e.g., 40)"
                                            value="{{ old('nft_percentage', $metas->get('nft_percentage')) }}">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">% to be Paid with Cash</label>
                                    <div class="input-group">
                                        <input type="number" name="cash_percentage_nft" class="form-control" min="0" max="100" step="1"
                                            placeholder="Enter cash percentage (e.g., 60)"
                                            value="{{ old('cash_percentage_nft', $metas->get('cash_percentage_nft')) }}">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">NFT Valuation Method</label>
                                    <input type="text" name="nft_valuation_method" class="form-control"
                                        placeholder="Enter valuation method (e.g., Floor price on OpenSea, Independent appraisal)"
                                        value="{{ old('nft_valuation_method', $metas->get('nft_valuation_method')) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">NFT Transfer Method</label>
                                    <input type="text" name="nft_transfer_method" class="form-control"
                                        placeholder="Enter transfer method (e.g., MetaMask, OpenSea, Propy Title, Escrow smart contract)"
                                        value="{{ old('nft_transfer_method', $metas->get('nft_transfer_method')) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Gas Fees Responsibility</label>
                                    <select name="nft_gas_fees" class="form-select">
                                        <option value="">Select</option>
                                        @foreach(['Buyer','Seller','Split'] as $gf)
                                        <option value="{{ $gf }}" {{ old('nft_gas_fees', $metas->get('nft_gas_fees')) === $gf ? 'selected' : '' }}>{{ $gf }}</option>
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
                                    value="{{ old('other_financing_details', $metas->get('other_financing_details')) }}">
                            </div>
                        </div>

                        {{-- ── Section 3: Contingencies ── --}}
                        <h6 class="offer-section-header">Contingencies</h6>
                        <div class="contingency-group mb-3">
                            {{-- Financing Contingency --}}
                            <div class="contingency-item">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="fin_cont_terms" name="financing_contingency"
                                        value="1" x-model="finCont"
                                        {{ old('financing_contingency', $metas->get('financing_contingency')) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="fin_cont_terms">
                                        Financing Contingency
                                        <span class="ms-1" data-bs-toggle="tooltip" data-bs-html="true"
                                            title="Makes your offer contingent on obtaining mortgage financing. Protects you if your loan approval falls through within the stated period.">
                                            <i class="fa-solid fa-circle-info" style="color:#2563eb;cursor:pointer;font-size:0.8rem;"></i>
                                        </span>
                                    </label>
                                </div>
                                <div x-show="finCont" class="contingency-days">
                                    <label class="form-label small mb-1">Contingency Period (days)</label>
                                    <input type="number" name="financing_contingency_days" class="form-control form-control-sm w-auto" min="1" max="365"
                                        placeholder="Enter days (e.g., 21)" style="min-width:9rem"
                                        value="{{ old('financing_contingency_days', $metas->get('financing_contingency_days')) }}">
                                </div>
                            </div>

                            {{-- Inspection Contingency --}}
                            <div class="contingency-item">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="insp_cont_terms" name="inspection_contingency"
                                        value="1" x-model="inspCont"
                                        {{ old('inspection_contingency', $metas->get('inspection_contingency')) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="insp_cont_terms">
                                        Inspection Contingency
                                        <span class="ms-1" data-bs-toggle="tooltip" data-bs-html="true"
                                            title="Allows you to negotiate repairs or withdraw your offer based on inspection findings within the stated period.">
                                            <i class="fa-solid fa-circle-info" style="color:#2563eb;cursor:pointer;font-size:0.8rem;"></i>
                                        </span>
                                    </label>
                                </div>
                                <div x-show="inspCont" class="contingency-days">
                                    <label class="form-label small mb-1">Inspection Period (days)</label>
                                    <input type="number" name="inspection_contingency_days" class="form-control form-control-sm w-auto" min="1" max="365"
                                        placeholder="Enter days (e.g., 10)" style="min-width:9rem"
                                        value="{{ old('inspection_contingency_days', $metas->get('inspection_contingency_days')) }}">
                                </div>
                            </div>

                            {{-- Appraisal Contingency --}}
                            <div class="contingency-item">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="appr_cont_terms" name="appraisal_contingency"
                                        value="1" x-model="apprCont"
                                        {{ old('appraisal_contingency', $metas->get('appraisal_contingency')) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="appr_cont_terms">
                                        Appraisal Contingency
                                        <span class="ms-1" data-bs-toggle="tooltip" data-bs-html="true"
                                            title="Protects you if the property appraises below the agreed purchase price, allowing you to renegotiate or withdraw.">
                                            <i class="fa-solid fa-circle-info" style="color:#2563eb;cursor:pointer;font-size:0.8rem;"></i>
                                        </span>
                                    </label>
                                </div>
                                <div x-show="apprCont" class="contingency-days">
                                    <label class="form-label small mb-1">Appraisal Period (days)</label>
                                    <input type="number" name="appraisal_contingency_days" class="form-control form-control-sm w-auto" min="1" max="365"
                                        placeholder="Enter days (e.g., 15)" style="min-width:9rem"
                                        value="{{ old('appraisal_contingency_days', $metas->get('appraisal_contingency_days')) }}">
                                </div>
                            </div>

                            {{-- Sale of Buyer Property Contingency --}}
                            <div class="contingency-item">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="sale_cont_terms" name="sale_of_buyer_property_contingency"
                                        value="1" x-model="saleCont"
                                        {{ old('sale_of_buyer_property_contingency', $metas->get('sale_of_buyer_property_contingency')) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="sale_cont_terms">
                                        Sale of Buyer's Property Contingency
                                        <span class="ms-1" data-bs-toggle="tooltip" data-bs-html="true"
                                            title="Makes your offer contingent on the successful sale of your current home within the stated period.">
                                            <i class="fa-solid fa-circle-info" style="color:#2563eb;cursor:pointer;font-size:0.8rem;"></i>
                                        </span>
                                    </label>
                                </div>
                                <div x-show="saleCont" class="contingency-days">
                                    <label class="form-label small mb-1">Contingency Period (days)</label>
                                    <input type="number" name="sale_of_buyer_property_contingency_days" class="form-control form-control-sm w-auto" min="1" max="365"
                                        placeholder="Enter days (e.g., 30)" style="min-width:9rem"
                                        value="{{ old('sale_of_buyer_property_contingency_days', $metas->get('sale_of_buyer_property_contingency_days')) }}">
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
                                        value="{{ old('closing_date', ($v = $metas->get('closing_date')) ? $safeDate($v) : '') }}">
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
                                        value="{{ old('possession_date', ($v = $metas->get('possession_date')) ? $safeDate($v) : '') }}">
                                </div>
                            </div>
                        </div>

                        {{-- Possession Notes --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Possession Notes <span class="text-muted small">(optional)</span></label>
                            <textarea name="possession_notes" class="form-control" rows="3"
                                placeholder="Enter possession notes (e.g., Requesting possession at closing, or seller may need up to 7 days post-close)">{{ old('possession_notes', $metas->get('possession_notes')) }}</textarea>
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
                                <option value="Yes" {{ old('seller_contribution_requested', $metas->get('seller_contribution_requested')) === 'Yes' ? 'selected' : '' }}>Yes</option>
                                <option value="No" {{ old('seller_contribution_requested', $metas->get('seller_contribution_requested')) === 'No' ? 'selected' : '' }}>No</option>
                            </select>
                            <div x-show="sellerContrib === 'Yes'" class="mt-2">
                                <input type="text" name="seller_contribution_details" class="form-control"
                                    placeholder="Enter contribution details (e.g., $5,000 toward buyer closing costs)"
                                    value="{{ old('seller_contribution_details', $metas->get('seller_contribution_details')) }}">
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
                                <option value="Yes" {{ old('home_warranty_requested', $metas->get('home_warranty_requested')) === 'Yes' ? 'selected' : '' }}>Yes</option>
                                <option value="No" {{ old('home_warranty_requested', $metas->get('home_warranty_requested')) === 'No' ? 'selected' : '' }}>No</option>
                            </select>
                            <div x-show="homeWarranty === 'Yes'" class="mt-2">
                                <input type="text" name="home_warranty_details" class="form-control"
                                    placeholder="Enter warranty details (e.g., $500 one-year home warranty through American Home Shield)"
                                    value="{{ old('home_warranty_details', $metas->get('home_warranty_details')) }}">
                            </div>
                        </div>

                        {{-- Included Personal Property --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Included Personal Property</label>
                            <input type="text" name="included_personal_property" class="form-control"
                                placeholder="Enter included items (e.g., Refrigerator, Washer/dryer, Dining room chandelier)"
                                value="{{ old('included_personal_property', $metas->get('included_personal_property')) }}">
                        </div>

                        {{-- Excluded Items --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Excluded Items</label>
                            <input type="text" name="excluded_items" class="form-control"
                                placeholder="Enter excluded items (e.g., Antique light fixture in dining room, Detached storage shed)"
                                value="{{ old('excluded_items', $metas->get('excluded_items')) }}">
                        </div>

                        </div>{{-- end x-data --}}
                        @endif

                        {{-- Rental/Lease-specific fields --}}
                        @if(in_array($offerType, ['rental', 'lease']))
                        <h6 class="fw-semibold text-muted mt-3 mb-2">{{ ucfirst($offerType) }} Terms</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Monthly Rent ($)</label>
                                <input type="number" name="monthly_rent" class="form-control" min="0" step="50"
                                    value="{{ old('monthly_rent', $metas->get('monthly_rent')) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Security Deposit ($)</label>
                                <input type="number" name="security_deposit" class="form-control" min="0" step="50"
                                    value="{{ old('security_deposit', $metas->get('security_deposit')) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Move-in Date</label>
                                <input type="date" name="move_in_date" class="form-control"
                                    value="{{ old('move_in_date', ($v = $metas->get('move_in_date')) ? $safeDate($v) : '') }}">
                            </div>
                        </div>
                        @if($offerType === 'lease')
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Lease Term (Months)</label>
                            <input type="number" name="lease_term_months" class="form-control w-auto" min="1" max="360"
                                value="{{ old('lease_term_months', $metas->get('lease_term_months')) }}">
                        </div>
                        @endif
                        @endif

                        {{-- ── Section 6: Internal Notes & Expiration ── --}}
                        <h6 class="offer-section-header">Internal Notes &amp; Expiration</h6>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Custom Terms / Special Conditions</label>
                            <textarea name="custom_terms" class="form-control" rows="4"
                                placeholder="Enter any special conditions, addendums, or custom terms">{{ old('custom_terms', $metas->get('custom_terms')) }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Private Notes <span class="text-muted small">(not shown to the other party)</span></label>
                            <textarea name="notes" class="form-control" rows="3"
                                placeholder="Enter private notes for your reference">{{ old('notes', $metas->get('notes')) }}</textarea>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">
                                    Offer Expires At
                                    <span class="ms-1" data-bs-toggle="tooltip" data-bs-html="true"
                                        title="The deadline by which the seller must respond to this offer. After this date the offer will be considered withdrawn.">
                                        <i class="fa-solid fa-circle-info" style="color:#2563eb;cursor:pointer;font-size:0.85rem;"></i>
                                    </span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-regular fa-calendar"></i></span>
                                    <input type="date" name="expires_at" class="form-control"
                                        value="{{ old('expires_at', ($v = $metas->get('expires_at')) ? $safeDate($v) : '') }}">
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" id="save-offer-terms-btn" class="btn btn-primary px-4">Save Offer Terms</button>
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
                        // Money input helpers — matching project-standard pattern
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
                                    // formatWithCommas on input = live comma insertion while typing
                                    inp.addEventListener('input', function () { formatWithCommas(this); });
                                    inp.addEventListener('blur', function () { reformatNumber(this); });
                                    inp.addEventListener('paste', handlePaste);
                                }
                                if (inp.value && !offerTermsIsPercentMode(inp)) { reformatNumber(inp); }
                            });
                        }

                        document.addEventListener('DOMContentLoaded', function () {
                            initOfferTermsMoneyInputs();

                            var form = document.querySelector('form[action*="terms"]');
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
                        <dt class="col-sm-3">Assumable Loan Terms</dt>
                        <dd class="col-sm-9">{{ $metas->get('assumable_terms') ?: '—' }}</dd>
                        <dt class="col-sm-3">Loan Type</dt>
                        <dd class="col-sm-9">{{ $metas->get('assumable_loan_type') ?: '—' }}</dd>
                        <dt class="col-sm-3">Interest Rate</dt>
                        <dd class="col-sm-9">{{ $metas->get('assumable_interest_rate') !== null && $metas->get('assumable_interest_rate') !== '' ? $metas->get('assumable_interest_rate') . '%' : '—' }}</dd>
                        <dt class="col-sm-3">Outstanding Balance</dt>
                        <dd class="col-sm-9">{{ $metas->get('outstanding_balance') ? '$' . number_format($metas->get('outstanding_balance')) : '—' }}</dd>
                        <dt class="col-sm-3">Loan Term Remaining</dt>
                        <dd class="col-sm-9">{{ $metas->get('assumable_loan_term_remaining') ?: '—' }}</dd>
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

                        @if($isOwner)
                        <dt class="col-sm-3">Private Notes</dt>
                        <dd class="col-sm-9" style="white-space: pre-wrap;">{{ $metas->get('notes') ?: '—' }}</dd>
                        @endif
                    </dl>
                    @endif
                </div>
            </div>

            {{-- Negotiation Timeline --}}
            <div class="card mb-4">
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
            </style>
            <div class="card mb-4">
                <div class="card-header">
                    <strong>Available Actions</strong>
                </div>
                <div class="card-body">
                    @php
                        $actionButtons = [
                            'can_submit'        => ['label' => 'Submit Offer',  'btn' => 'btn-primary',           'reason_key' => 'submit',        'route' => 'offers.submit'],
                            'can_accept'        => ['label' => 'Accept',         'btn' => 'btn-success',           'reason_key' => 'accept',        'route' => 'offers.accept'],
                            'can_reject'        => ['label' => 'Reject',         'btn' => 'btn-danger',            'reason_key' => 'reject',        'route' => 'offers.reject'],
                            'can_withdraw'      => ['label' => 'Withdraw',       'btn' => 'btn-outline-secondary', 'reason_key' => 'withdraw',      'route' => 'offers.withdraw'],
                            'can_view_timeline' => ['label' => 'View Timeline',  'btn' => 'btn-outline-info',      'reason_key' => 'view_timeline', 'route' => null],
                        ];
                        $counterReason = $actions['reasons']['counter'] ?? '';
                    @endphp
                    <div class="d-flex flex-wrap gap-3 align-items-start">
                        @foreach($actionButtons as $flag => $cfg)
                            @php
                                $allowed = !empty($actions[$flag]);
                                $reason  = $allowed ? '' : ($actions['reasons'][$cfg['reason_key']] ?? '');
                            @endphp
                            <div class="d-flex flex-column align-items-start" style="min-width: 130px;">
                                @if($allowed && $cfg['route'])
                                    {{-- Enabled action with a route: POST form --}}
                                    <form method="POST" action="{{ route($cfg['route'], $offer) }}">
                                        @csrf
                                        <button type="submit" class="btn {{ $cfg['btn'] }} btn-sm"@if($flag === 'can_submit') id="submit-offer-action-btn"@endif>{{ $cfg['label'] }}</button>
                                    </form>
                                @elseif($allowed)
                                    {{-- Enabled action with no route (e.g. View Timeline): plain enabled button --}}
                                    <button type="button" class="btn {{ $cfg['btn'] }} btn-sm">{{ $cfg['label'] }}</button>
                                @else
                                    {{-- Disabled action: bare button, no form --}}
                                    <button type="button" class="btn {{ $cfg['btn'] }} btn-sm"@if($flag === 'can_submit') id="submit-offer-action-btn"@endif disabled title="{{ $reason }}" aria-disabled="true" tabindex="-1">{{ $cfg['label'] }}</button>
                                    @if($reason)
                                        <small class="text-muted mt-1 px-1" style="font-size: 0.75rem; line-height: 1.3;">{{ $reason }}</small>
                                    @endif
                                @endif
                            </div>
                        @endforeach

                        {{-- Counter: three-branch logic --}}
                        @if(!empty($actions['can_counter']))
                            {{-- can_counter=true: real POST form with expires_at date input --}}
                            <div class="d-flex flex-column align-items-start" style="min-width: 130px;">
                                <form method="POST" action="{{ route('offers.counter', $offer) }}">
                                    @csrf
                                    <div class="mb-2">
                                        <input type="date" name="expires_at" class="form-control form-control-sm">
                                    </div>
                                    <button type="submit" class="btn btn-warning btn-sm">Counter</button>
                                </form>
                            </div>
                        @elseif($counterReason !== '')
                            {{-- can_counter=false with reason: disabled button with tooltip and reason text --}}
                            <div class="d-flex flex-column align-items-start" style="min-width: 130px;">
                                <button type="button" class="btn btn-warning btn-sm" disabled title="{{ $counterReason }}" aria-disabled="true" tabindex="-1">Counter</button>
                                <small class="text-muted mt-1 px-1" style="font-size: 0.75rem; line-height: 1.3;">{{ $counterReason }}</small>
                            </div>
                        @endif
                        {{-- can_counter=false with empty reason: nothing rendered --}}
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
