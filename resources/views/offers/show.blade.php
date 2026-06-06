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
                        <div x-data="{
                            finType: '{{ $_ft }}',
                            finCont: {{ old('financing_contingency', $metas->get('financing_contingency')) ? 'true' : 'false' }},
                            inspCont: {{ old('inspection_contingency', $metas->get('inspection_contingency')) ? 'true' : 'false' }},
                            saleCont: {{ old('sale_of_buyer_property_contingency', $metas->get('sale_of_buyer_property_contingency')) ? 'true' : 'false' }},
                            sellerContrib: '{{ old('seller_contribution_requested', $metas->get('seller_contribution_requested') ?? '') }}',
                            homeWarranty: '{{ old('home_warranty_requested', $metas->get('home_warranty_requested') ?? '') }}',
                            initTf: '{{ $_initTf }}',
                            addTf: '{{ $_addTf }}'
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
                                    <input type="number" name="offer_price" class="form-control" min="0" step="1000"
                                        placeholder="Enter offer price (e.g., 450000)"
                                        value="{{ old('offer_price', $metas->get('offer_price')) }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">
                                    Earnest Deposit ($)
                                    <span class="ms-1" data-bs-toggle="tooltip" data-bs-html="true"
                                        title="The good-faith deposit accompanying your offer, typically held in escrow until closing.">
                                        <i class="fa-solid fa-circle-info" style="color:#2563eb;cursor:pointer;font-size:0.85rem;"></i>
                                    </span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                                    <input type="number" name="earnest_deposit" class="form-control" min="0" step="100"
                                        placeholder="Enter earnest deposit (e.g., 5000)"
                                        value="{{ old('earnest_deposit', $metas->get('earnest_deposit')) }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">
                                    Down Payment (%)
                                    <span class="ms-1" data-bs-toggle="tooltip" data-bs-html="true"
                                        title="The percentage of the purchase price you plan to pay upfront, outside of any financed amount.">
                                        <i class="fa-solid fa-circle-info" style="color:#2563eb;cursor:pointer;font-size:0.85rem;"></i>
                                    </span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-percent"></i></span>
                                    <input type="number" name="down_payment_percent" class="form-control" min="0" max="100" step="0.5"
                                        placeholder="Enter down payment % (e.g., 20)"
                                        value="{{ old('down_payment_percent', $metas->get('down_payment_percent')) }}">
                                </div>
                            </div>
                        </div>

                        {{-- Initial Deposit --}}
                        <div class="row g-3 mb-3">
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">Initial Deposit Amount</label>
                                <input type="text" name="initial_deposit_amount" class="form-control"
                                    placeholder="Enter initial deposit amount (e.g., 5000 or 3%)"
                                    value="{{ old('initial_deposit_amount', $metas->get('initial_deposit_amount')) }}">
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
                                <input type="text" name="additional_deposit_amount" class="form-control"
                                    placeholder="Enter additional deposit (e.g., 10000 or 2%)"
                                    value="{{ old('additional_deposit_amount', $metas->get('additional_deposit_amount')) }}">
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
                                        <input type="number" name="outstanding_balance" class="form-control" min="0"
                                            placeholder="Enter balance (e.g., 250000)"
                                            value="{{ old('outstanding_balance', $metas->get('outstanding_balance')) }}">
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
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Item Offered for Exchange</label>
                                    <input type="text" name="exchange_item" class="form-control"
                                        placeholder="Enter item offered (e.g., Another home, Vehicle, Boat)"
                                        value="{{ old('exchange_item', $metas->get('exchange_item')) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Estimated Value ($)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                                        <input type="number" name="exchange_item_value" class="form-control" min="0"
                                            placeholder="Enter value (e.g., 75000)"
                                            value="{{ old('exchange_item_value', $metas->get('exchange_item_value')) }}">
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Seller Financing conditional sub-fields --}}
                        <div x-show="finType === 'Seller Financing'" class="border rounded p-3 mb-3 bg-light">
                            <h6 class="fw-semibold mb-3">Seller Financing Details</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Financed Amount ($)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                                        <input type="number" name="seller_financing_amount" class="form-control" min="0"
                                            placeholder="Enter financed amount (e.g., 400000)"
                                            value="{{ old('seller_financing_amount', $metas->get('seller_financing_amount')) }}">
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
                                        placeholder="Enter days (e.g., 21)"
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
                                        placeholder="Enter days (e.g., 10)"
                                        value="{{ old('inspection_contingency_days', $metas->get('inspection_contingency_days')) }}">
                                </div>
                            </div>

                            {{-- Appraisal Contingency --}}
                            <div class="contingency-item">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="appr_cont_terms" name="appraisal_contingency"
                                        value="1"
                                        {{ old('appraisal_contingency', $metas->get('appraisal_contingency')) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="appr_cont_terms">
                                        Appraisal Contingency
                                        <span class="ms-1" data-bs-toggle="tooltip" data-bs-html="true"
                                            title="Protects you if the property appraises below the agreed purchase price, allowing you to renegotiate or withdraw.">
                                            <i class="fa-solid fa-circle-info" style="color:#2563eb;cursor:pointer;font-size:0.8rem;"></i>
                                        </span>
                                    </label>
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
                                        placeholder="Enter days (e.g., 30)"
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
                                placeholder="Enter included items (e.g., Refrigerator, Washer/Dryer, Dining Room Chandelier)"
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
                        <dd class="col-sm-9">{{ $metas->get('earnest_deposit') ? '$' . number_format($metas->get('earnest_deposit')) : '—' }}</dd>

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
                        <dt class="col-sm-3">Exchange Item</dt>
                        <dd class="col-sm-9">{{ $metas->get('exchange_item') ?: '—' }}</dd>
                        <dt class="col-sm-3">Estimated Value</dt>
                        <dd class="col-sm-9">{{ $metas->get('exchange_item_value') ? '$' . number_format($metas->get('exchange_item_value')) : '—' }}</dd>
                        @endif

                        @if($ftRaw === 'Seller Financing')
                        <dt class="col-sm-3">Financed Amount</dt>
                        <dd class="col-sm-9">{{ $metas->get('seller_financing_amount') ? '$' . number_format($metas->get('seller_financing_amount')) : '—' }}</dd>
                        <dt class="col-sm-3">Interest Rate</dt>
                        <dd class="col-sm-9">{{ $metas->get('seller_financing_rate') !== null && $metas->get('seller_financing_rate') !== '' ? $metas->get('seller_financing_rate') . '%' : '—' }}</dd>
                        <dt class="col-sm-3">Loan Term</dt>
                        <dd class="col-sm-9">{{ $metas->get('seller_financing_term') ?: '—' }}</dd>
                        @endif

                        <dt class="col-sm-3">Down Payment %</dt>
                        <dd class="col-sm-9">{{ $metas->get('down_payment_percent') !== null && $metas->get('down_payment_percent') !== '' ? $metas->get('down_payment_percent') . '%' : '—' }}</dd>

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
                        <dd class="col-sm-9">{{ $metas->get('appraisal_contingency') ? 'Yes' : 'No' }}</dd>

                        <dt class="col-sm-3">Closing Date</dt>
                        <dd class="col-sm-9">{{ $safeDate($metas->get('closing_date')) }}</dd>

                        <dt class="col-sm-3">Possession Date</dt>
                        <dd class="col-sm-9">{{ $safeDate($metas->get('possession_date')) }}</dd>

                        {{-- Purchase Terms --}}
                        @if($metas->get('initial_deposit_amount'))
                        @php
                            $_initTfDisplay = $metas->get('initial_deposit_timeframe');
                            if ($_initTfDisplay === 'Other' && $metas->get('initial_deposit_timeframe_other')) {
                                $_initTfDisplay = $metas->get('initial_deposit_timeframe_other');
                            }
                        @endphp
                        <dt class="col-sm-3">Initial Deposit Amount</dt>
                        <dd class="col-sm-9">{{ $metas->get('initial_deposit_amount') }}{{ $_initTfDisplay ? ' — ' . $_initTfDisplay : '' }}</dd>
                        @endif

                        @if($metas->get('additional_deposit_amount'))
                        @php
                            $_addTfDisplay = $metas->get('additional_deposit_timeframe');
                            if ($_addTfDisplay === 'Other' && $metas->get('additional_deposit_timeframe_other')) {
                                $_addTfDisplay = $metas->get('additional_deposit_timeframe_other');
                            }
                        @endphp
                        <dt class="col-sm-3">Additional Deposit Amount</dt>
                        <dd class="col-sm-9">{{ $metas->get('additional_deposit_amount') }}{{ $_addTfDisplay ? ' — ' . $_addTfDisplay : '' }}</dd>
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
                                        <button type="submit" class="btn {{ $cfg['btn'] }} btn-sm">{{ $cfg['label'] }}</button>
                                    </form>
                                @elseif($allowed)
                                    {{-- Enabled action with no route (e.g. View Timeline): plain enabled button --}}
                                    <button type="button" class="btn {{ $cfg['btn'] }} btn-sm">{{ $cfg['label'] }}</button>
                                @else
                                    {{-- Disabled action: bare button, no form --}}
                                    <button type="button" class="btn {{ $cfg['btn'] }} btn-sm" disabled title="{{ $reason }}" aria-disabled="true" tabindex="-1">{{ $cfg['label'] }}</button>
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
