@extends('layouts.main')

@push('styles')
<style>
    .counter-wrap {
        max-width: 860px;
        margin: 0 auto;
    }
    .counter-tab-nav .nav-link {
        color: #495057;
        font-weight: 600;
        font-size: .88rem;
        border-radius: 8px 8px 0 0;
        padding: .6rem 1.1rem;
    }
    .counter-tab-nav .nav-link.active {
        color: #049399;
        border-bottom-color: #fff;
        background: #fff;
        border-color: #dee2e6 #dee2e6 #fff;
    }
    .counter-tab-pane {
        background: #fff;
        border: 1px solid #dee2e6;
        border-top: none;
        border-radius: 0 0 10px 10px;
        padding: 1.5rem 1.75rem;
        margin-bottom: 1.5rem;
    }
    .counter-section-label {
        font-size: .78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #6c757d;
        margin-bottom: .6rem;
    }
    .comp-table {
        width: 100%;
        font-size: .9rem;
        border-collapse: collapse;
    }
    .comp-table tr:not(:last-child) td { border-bottom: 1px solid #f0f0f0; }
    .comp-table td { padding: .55rem .25rem; vertical-align: top; }
    .comp-table td:first-child {
        width: 45%;
        color: #6c757d;
        font-size: .82rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        padding-right: 1rem;
    }
    .comp-table td:last-child { color: #1a1a1a; }
    .service-check-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .service-check-list li label {
        display: flex;
        align-items: flex-start;
        gap: .55rem;
        cursor: pointer;
        font-size: .9rem;
        color: #1a1a1a;
    }
    .service-check-list li input[type="checkbox"] {
        margin-top: 3px;
        flex-shrink: 0;
        accent-color: #049399;
        width: 16px;
        height: 16px;
    }
    .counter-notice {
        background: #fff8e1;
        border: 1px solid #ffe082;
        border-radius: 8px;
        padding: 1rem 1.25rem;
        font-size: .9rem;
        color: #5d4037;
        line-height: 1.65;
        margin-bottom: 1.25rem;
    }
    .counter-notice strong { color: #e65100; }
    .submit-btn {
        background: #049399;
        border: none;
        border-radius: 7px;
        padding: 12px 36px;
        font-weight: 700;
        font-size: 1rem;
        color: #fff;
        transition: opacity .15s;
    }
    .submit-btn:hover:not(:disabled) { opacity: .85; }
    .submit-btn:disabled { opacity: .55; cursor: not-allowed; }
    .tab-nav-btn {
        background: #f0fafa;
        border: 1px solid #c8e8ea;
        border-radius: 7px;
        padding: 9px 22px;
        font-weight: 600;
        font-size: .9rem;
        color: #049399;
        cursor: pointer;
        transition: background .15s;
    }
    .tab-nav-btn:hover { background: #e0f5f5; }
    .ack-section-header {
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        padding: .7rem 1.25rem;
        font-weight: 700;
        font-size: .87rem;
        display: flex;
        align-items: center;
        gap: .45rem;
        color: #333;
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .ack-section-header i { color: #049399; }
    .ack-section-body { padding: 1.2rem 1.4rem; }
    /* Comp form styles */
    .cc-group {
        margin-bottom: 1.25rem;
    }
    .cc-group label.cc-label {
        font-weight: 600;
        font-size: .875rem;
        color: #333;
        margin-bottom: .35rem;
        display: block;
    }
    .cc-sub {
        margin-top: .5rem;
        padding-left: 1rem;
        border-left: 2px solid #dee2e6;
    }
    .cc-conditional {
        display: none;
    }
    .cc-section-divider {
        border-top: 1px solid #dee2e6;
        margin: 1.5rem 0 1.25rem;
    }
    .cc-section-heading {
        font-size: .78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6c757d;
        margin-bottom: 1rem;
    }
    .additional-requested-wrap {
        border-top: 1px solid #dee2e6;
        margin-top: 1.5rem;
        padding-top: 1.25rem;
    }
    .detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .5rem 1.5rem;
        font-size: .9rem;
    }
    .detail-grid dt {
        font-size: .78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #6c757d;
        margin-bottom: 1px;
    }
    .detail-grid dd {
        color: #1a1a1a;
        margin: 0 0 .6rem 0;
    }
    .service-bullet-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .service-bullet-list li {
        font-size: .9rem;
        color: #1a1a1a;
        padding: 2px 0;
    }
    .service-bullet-list li::before {
        content: "✓ ";
        color: #049399;
        font-weight: 700;
    }
</style>
@endpush

@section('content')
@php $viewerIsAgent = auth()->check() && auth()->user()->user_type === 'agent'; @endphp
<div class="buyerOfferContentDetails py-4">
<div class="container counter-wrap">

    @php
        $agentFullName    = trim(($mapped['first_name'] ?? '') . ' ' . ($mapped['last_name'] ?? ''));
        $agentDisplayName = $agentFullName ?: ($agent->name ?? 'This Agent');
        $roleLabel        = \App\Models\AgentDefaultProfile::roleLabel($role);
        $propLabel        = \App\Models\AgentDefaultProfile::propertyLabel($propertyType);

        // Helper: resolve counter_comp value first, then mapped, then ''
        $cc  = $pending['counter_comp'] ?? [];
        $ccv = fn(string $k) => $cc[$k] ?? $mapped[$k] ?? '';
    @endphp

    {{-- Breadcrumb --}}
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('search.agents') }}">Browse Agents</a></li>
                <li class="breadcrumb-item">
                    <a href="{{ route('hire.agent.direct.preview', ['agentId' => $agent->id, 'role' => $role, 'propertyType' => $propertyType]) }}">
                        Review Agent Terms
                    </a>
                </li>
                <li class="breadcrumb-item active">Request Changes</li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-1">Request Changes / Counter Terms</h4>
        <p class="text-muted" style="font-size:.93rem;">
            Adjust the services and compensation terms you want, add any notes, and provide your contact details.
            The agent will review your counter request and respond directly.
        </p>
    </div>

    {{-- Flash errors --}}
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Agent summary strip --}}
    <div class="card mb-3" style="border-radius:10px;border-color:#dee2e6;">
        <div class="card-body d-flex align-items-center gap-3 py-3">
            <x-avatar-img :avatar="$agent->avatar" alt="Agent avatar"
                 style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid #c8e8ea;flex-shrink:0;" />
            <div>
                <div class="fw-bold" style="font-size:1rem;">{{ $agentDisplayName }}</div>
                <div class="text-muted small">
                    <span class="badge" style="background:#e8f7f7;color:#036b70;font-size:.75rem;">{{ $roleLabel }}</span>
                    <span class="badge ms-1" style="background:#f0f4ff;color:#4a5aaa;font-size:.75rem;">{{ $propLabel }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Counter notice --}}
    <div class="counter-notice">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>
        <strong>This is a counter request, not a final agreement.</strong>
        Adjust the services and comp terms below, add any notes in the Additional Terms tab, then review and submit.
        Submitting sends your proposed counter terms to the agent for review.
        The agent may accept, counter, or decline before anything is finalized.
    </div>

    <form method="POST"
          action="{{ route('hire.agent.direct.counter.submit', ['agentId' => $agent->id, 'role' => $role, 'propertyType' => $propertyType]) }}"
          id="counter-form"
          onsubmit="return counterFormSubmit(this)">
        @csrf
        <input type="hidden" name="_counter_nonce" value="{{ $pending['counter_nonce'] ?? '' }}">

        {{-- Tab navigation --}}
        <ul class="nav nav-tabs counter-tab-nav" id="counterTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-services-btn" data-bs-toggle="tab"
                        data-bs-target="#tab-services" type="button" role="tab">
                    <i class="fa-solid fa-square-check me-1"></i> Services
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-comp-btn" data-bs-toggle="tab"
                        data-bs-target="#tab-comp" type="button" role="tab">
                    <i class="fa-solid fa-file-lines me-1"></i> Comp Terms
                </button>
            </li>
            @if($viewerIsAgent)
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-referral-btn" data-bs-toggle="tab"
                        data-bs-target="#tab-referral" type="button" role="tab">
                    <i class="fa-solid fa-handshake me-1"></i> Referral Fee
                </button>
            </li>
            @endif
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-notes-btn" data-bs-toggle="tab"
                        data-bs-target="#tab-notes" type="button" role="tab">
                    <i class="fa-solid fa-comment-dots me-1"></i> Additional Terms
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-client-btn" data-bs-toggle="tab"
                        data-bs-target="#tab-client" type="button" role="tab">
                    <i class="fa-solid fa-address-card me-1"></i> Your Details
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-review-btn" data-bs-toggle="tab"
                        data-bs-target="#tab-review" type="button" role="tab"
                        onclick="populateReviewTab()">
                    <i class="fa-solid fa-clipboard-check me-1"></i> Review Your Counter Terms
                </button>
            </li>
        </ul>

        <div class="tab-content" id="counterTabContent">

            {{-- Tab 1: Services --}}
            <div class="tab-pane fade show active counter-tab-pane" id="tab-services" role="tabpanel">
                <p class="text-muted small mb-3">
                    All of the agent's services are selected by default.
                    Uncheck any service you do not want included in your request.
                </p>

                @php $isFirstGroup = true; @endphp
                @foreach($groupedAgentServices as $categoryLabel => $categoryServices)
                    @if(!empty($categoryServices))
                    <div style="margin-top: {{ $isFirstGroup ? '0' : '1.25rem' }};">
                        <div class="counter-section-label">{{ $categoryLabel }}</div>
                        <ul class="service-check-list">
                            @foreach($categoryServices as $svc)
                            <li>
                                <label>
                                    <input type="checkbox" name="services[]"
                                           value="{{ $svc }}"
                                           {{ in_array($svc, $selectedServices) ? 'checked' : '' }}>
                                    {{ $svc }}
                                </label>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @php $isFirstGroup = false; @endphp
                    @endif
                @endforeach

                @if(!empty($otherServices))
                <div class="mt-4">
                    <div class="counter-section-label">Additional Services</div>
                    <ul class="service-check-list">
                        @foreach($otherServices as $svc)
                        <li>
                            <label>
                                <input type="checkbox" name="other_services[]"
                                       value="{{ $svc }}"
                                       {{ in_array($svc, $selectedOtherServices) ? 'checked' : '' }}>
                                {{ $svc }}
                            </label>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                {{-- Additional Services Requested --}}
                <div class="additional-requested-wrap">
                    <div class="counter-section-label">Additional Services Requested</div>
                    <p class="text-muted small mb-2">
                        List any services not offered above that you'd like to request from the agent.
                        Enter one service per line.
                    </p>
                    @php
                        $existingClientServices = $pending['client_requested_services'] ?? [];
                        $existingClientServicesText = is_array($existingClientServices)
                            ? implode("\n", $existingClientServices)
                            : '';
                        $isCommercialSvc = $propertyType !== 'residential';
                        $addlServicesPlaceholder = match(true) {
                            $role === 'seller' && !$isCommercialSvc => 'Enter additional services requested (e.g., Custom Neighborhood Mailer, Seller Video Script, Pre-Listing Strategy Call, Relocation Buyer Outreach)',
                            $role === 'seller' && $isCommercialSvc  => 'Enter additional services requested (e.g., Investor Prospect List, Property Offering Memorandum Outline, Broker Outreach Script, Tenant Mix Strategy Notes)',
                            $role === 'buyer'  && !$isCommercialSvc => 'Enter additional services requested (e.g., Off-Market Outreach Letter, Neighborhood Comparison Summary, School Zone Research Summary, Commute Area Shortlist)',
                            $role === 'buyer'  && $isCommercialSvc  => 'Enter additional services requested (e.g., Site Selection Matrix, Trade Area Snapshot, Parking Demand Review, Business Use Fit Summary)',
                            $role === 'landlord' && !$isCommercialSvc => 'Enter additional services requested (e.g., Rental Pricing Snapshot, Tenant Persona Summary, Local Employer Outreach List, Move-In Readiness Checklist)',
                            $role === 'landlord' && $isCommercialSvc  => 'Enter additional services requested (e.g., Tenant Prospect List, Use Compatibility Notes, Broker Outreach Script, Leasing Flyer Outline)',
                            $role === 'tenant' && !$isCommercialSvc => 'Enter additional services requested (e.g., Rental Application Strategy, Neighborhood Fit Summary, Commute Zone Shortlist, Pet-Friendly Housing Notes)',
                            default => 'Enter additional services requested (e.g., Space Requirement Summary, Trade Area Snapshot, LOI Question List, Business Use Fit Notes)',
                        };
                    @endphp
                    <textarea name="client_requested_services"
                              rows="4"
                              class="form-control @error('client_requested_services') is-invalid @enderror"
                              maxlength="3000"
                              placeholder="{{ $addlServicesPlaceholder }}">{{ old('client_requested_services', $existingClientServicesText) }}</textarea>
                    @error('client_requested_services')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="text-muted small mt-1">One service per line. Maximum 50 entries, 3,000 characters total.</div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="button" class="tab-nav-btn" onclick="switchTab('tab-comp-btn')">
                        Next: Comp Terms <i class="fa-solid fa-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>

            {{-- Tab 2: Comp Terms (editable) --}}
            <div class="tab-pane fade counter-tab-pane" id="tab-comp" role="tabpanel">
                <p class="text-muted small mb-3">
                    These are the agent's proposed compensation and agency agreement terms — edit any field you'd like to counter.
                </p>

                {{-- ── ROLE-SPECIFIC PRIMARY FEE FIELDS ──────────────────────────── --}}

                @if($role === 'buyer')
                    {{-- Buyer's Broker Commission Structure --}}
                    <div class="cc-group">
                        <label class="cc-label">Buyer's Broker Commission Structure</label>
                        <select name="cc[commission_structure]" class="form-control form-control-sm"
                                onchange="ccTrigger(this, 'cc_commission_structure')">
                            <option value="">Select</option>
                            @foreach(config('agent_preset_compensation.buyer.commission_structure', []) as $optVal => $optLabel)
                                <option value="{{ $optVal }}" {{ $ccv('commission_structure') === $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Buyer Purchase Fee --}}
                    <div class="cc-group">
                        <label class="cc-label">Buyer's Broker Purchase Fee</label>
                        <select name="cc[purchase_fee_type]" class="form-control form-control-sm"
                                onchange="ccTrigger(this, 'cc_purchase_fee_type')">
                            <option value="">Select</option>
                            @foreach(config('agent_preset_compensation.buyer.purchase_fee_type', []) as $optVal => $optLabel)
                                <option value="{{ $optVal }}" {{ $ccv('purchase_fee_type') === $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                            @endforeach
                        </select>
                        <div class="cc-sub mt-2">
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="Flat Fee">
                                <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[purchase_fee_flat]" class="form-control" value="{{ $ccv('purchase_fee_flat') }}" placeholder="Enter flat fee amount (e.g., 5000)"></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="Percentage of the Total Purchase Price">
                                <div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_percentage]" class="form-control" value="{{ $ccv('purchase_fee_percentage') }}" placeholder="Enter percentage of the total purchase price (e.g., 3)"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="Percentage of the Total Purchase Price + Flat Fee">
                                <div class="row g-2">
                                    <div class="col-md-6"><div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_percentage_combo]" class="form-control" value="{{ $ccv('purchase_fee_percentage_combo') }}" placeholder="Enter percentage of the total purchase price (e.g., 2)"><span class="input-group-text">%</span></div></div>
                                    <div class="col-md-1 text-center pt-1">+</div>
                                    <div class="col-md-5"><div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[purchase_fee_flat_combo]" class="form-control" value="{{ $ccv('purchase_fee_flat_combo') }}" placeholder="Enter flat fee amount (e.g., 3000)"></div></div>
                                </div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="other">
                                <input type="text" name="cc[purchase_fee_other]" class="form-control form-control-sm" value="{{ $ccv('purchase_fee_other') }}" placeholder="Enter purchase fee structure (e.g., 1000 upfront + 2% at Closing)">
                            </div>
                        </div>
                    </div>

                    {{-- Interested in Lease Agreement --}}
                    <div class="cc-group">
                        <label class="cc-label">Interested in a Lease Agreement</label>
                        <select name="cc[interested_lease_option]" class="form-control form-control-sm"
                                onchange="ccTrigger(this, 'cc_interested_lease_option')">
                            <option value="">Select</option>
                            <option value="Yes" {{ $ccv('interested_lease_option') === 'Yes' ? 'selected' : '' }}>Yes</option>
                            <option value="No" {{ $ccv('interested_lease_option') === 'No' ? 'selected' : '' }}>No</option>
                        </select>
                        <div class="cc-sub mt-2">
                            <div class="cc-conditional" data-cc-parent="cc_interested_lease_option" data-cc-values="Yes">
                                <label class="cc-label">Buyer's Broker Lease Fee</label>
                                @php
                                    $buyerLeaseFeeOpts = ($propertyType === 'residential')
                                        ? config('agent_preset_compensation.buyer.lease_fee_type.residential', [])
                                        : config('agent_preset_compensation.buyer.lease_fee_type.commercial', []);
                                @endphp
                                <select name="cc[lease_fee_type]" class="form-control form-control-sm mb-2"
                                        onchange="ccTrigger(this, 'cc_lease_fee_type')">
                                    <option value="">Select</option>
                                    @foreach($buyerLeaseFeeOpts as $optVal => $optLabel)
                                        <option value="{{ $optVal }}" {{ $ccv('lease_fee_type') === $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                                    @endforeach
                                </select>
                                <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="flat">
                                    <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[lease_fee_flat]" class="form-control" value="{{ $ccv('lease_fee_flat') }}" placeholder="Enter flat fee amount (e.g., 2500)"></div>
                                </div>
                                @if($propertyType === 'residential')
                                <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="Percentage of Monthly Rent">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[lease_fee_percentage_monthly_rent]" class="form-control" value="{{ $ccv('lease_fee_percentage_monthly_rent') }}" placeholder="Enter percentage of monthly rent (e.g., 100)"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="Percentage of the Gross Lease Value">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[lease_fee_percentage]" class="form-control" value="{{ $ccv('lease_fee_percentage') }}" placeholder="Enter percentage of the gross lease value (e.g., 10)"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="Flat Fee + Percentage of the Gross Lease Value">
                                    <div class="row g-2">
                                        <div class="col-md-5"><div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[lease_fee_flat_combo]" class="form-control" value="{{ $ccv('lease_fee_flat_combo') }}" placeholder="Enter flat fee amount (e.g., 1000)"></div></div>
                                        <div class="col-md-1 text-center pt-1">+</div>
                                        <div class="col-md-6"><div class="input-group input-group-sm"><input type="number" name="cc[lease_fee_percentage_combo]" class="form-control" value="{{ $ccv('lease_fee_percentage_combo') }}" placeholder="Enter percentage of the gross lease value (e.g., 7)"><span class="input-group-text">%</span></div></div>
                                    </div>
                                </div>
                                @else
                                <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="Percentage of the Net Aggregate Rent">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[lease_fee_percentage_net]" class="form-control" value="{{ $ccv('lease_fee_percentage_net') }}" placeholder="Enter percentage of the net aggregate rent (e.g., 6)"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="Flat Fee + Percentage of the Net Aggregate Rent">
                                    <div class="row g-2">
                                        <div class="col-md-5"><div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[lease_fee_flat_combo_net]" class="form-control" value="{{ $ccv('lease_fee_flat_combo_net') }}" placeholder="Enter flat fee amount (e.g., 1500)"></div></div>
                                        <div class="col-md-1 text-center pt-1">+</div>
                                        <div class="col-md-6"><div class="input-group input-group-sm"><input type="number" name="cc[lease_fee_percentage_combo_net]" class="form-control" value="{{ $ccv('lease_fee_percentage_combo_net') }}" placeholder="Enter percentage of the net aggregate rent (e.g., 6)"><span class="input-group-text">%</span></div></div>
                                    </div>
                                </div>
                                @endif
                                <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="other">
                                    <input type="text" name="cc[lease_fee_other]" class="form-control form-control-sm" value="{{ $ccv('lease_fee_other') }}" placeholder="Enter the total lease fee amount and payment structure for the Buyer's Broker (e.g., $1500 upfront, $2000 at lease execution)">
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if($role === 'seller')
                    {{-- Seller Purchase Fee --}}
                    <div class="cc-group">
                        <label class="cc-label">Seller's Broker Purchase Fee</label>
                        <select name="cc[purchase_fee_type]" class="form-control form-control-sm"
                                onchange="ccTrigger(this, 'cc_purchase_fee_type')">
                            <option value="">Select</option>
                            @foreach(config('agent_preset_compensation.seller.purchase_fee_type', []) as $optVal => $optLabel)
                                <option value="{{ $optVal }}" {{ $ccv('purchase_fee_type') === $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                            @endforeach
                        </select>
                        <div class="cc-sub mt-2">
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="flat">
                                <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[purchase_fee_flat]" class="form-control" value="{{ $ccv('purchase_fee_flat') }}" placeholder="Enter flat fee amount (e.g., 5000)"></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="percentage">
                                <div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_percentage]" class="form-control" value="{{ $ccv('purchase_fee_percentage') }}" placeholder="Enter percentage of total purchase price (e.g., 6)"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="combo">
                                <div class="row g-2">
                                    <div class="col-md-6"><div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_percentage_combo]" class="form-control" value="{{ $ccv('purchase_fee_percentage_combo') }}" placeholder="Enter percentage of purchase price (e.g., 2)"><span class="input-group-text">%</span></div></div>
                                    <div class="col-md-1 text-center pt-1">+</div>
                                    <div class="col-md-5"><div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[purchase_fee_flat_combo]" class="form-control" value="{{ $ccv('purchase_fee_flat_combo') }}" placeholder="Enter flat fee amount (e.g., 2000)"></div></div>
                                </div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="other">
                                <input type="text" name="cc[purchase_fee_other]" class="form-control form-control-sm" value="{{ $ccv('purchase_fee_other') }}" placeholder="Enter commission structure (e.g., Tiered fee: 5% on the first $500000, 3% on any amount above $500000)">
                            </div>
                        </div>
                    </div>

                    {{-- Buyer's Broker Commission Structure --}}
                    <div class="cc-group">
                        <label class="cc-label">Buyer's Broker Commission Structure</label>
                        <select name="cc[commission_structure]" class="form-control form-control-sm"
                                onchange="ccTrigger(this, 'cc_commission_structure')">
                            <option value="">Select</option>
                            @foreach(config('agent_preset_compensation.seller.commission_structure', []) as $optVal => $optLabel)
                                <option value="{{ $optVal }}" {{ $ccv('commission_structure') === $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                            @endforeach
                        </select>
                        <div class="cc-sub mt-2">
                            <div class="cc-conditional" data-cc-parent="cc_commission_structure"
                                 data-cc-values="Seller's Broker to Compensate Buyer's Broker from Seller's Broker Commission|Seller to Pay Buyer's Broker Separately">
                                <label class="cc-label">Buyer's Broker Commission Fee</label>
                                <select name="cc[commission_structure_type]" class="form-control form-control-sm mb-2"
                                        onchange="ccTrigger(this, 'cc_commission_structure_type')">
                                    <option value="">Select</option>
                                    @foreach(config('agent_preset_compensation.seller.commission_structure_type', []) as $optVal => $optLabel)
                                        <option value="{{ $optVal }}" {{ $ccv('commission_structure_type') === $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                                    @endforeach
                                </select>
                                <div class="cc-conditional" data-cc-parent="cc_commission_structure_type" data-cc-values="Flat Fee">
                                    <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[commission_structure_type_fee_flat]" class="form-control" value="{{ $ccv('commission_structure_type_fee_flat') }}" placeholder="Enter flat fee amount (e.g., 4000)"></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_commission_structure_type" data-cc-values="Percentage of the Total Purchase Price">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[commission_structure_type_fee_percentage]" class="form-control" value="{{ $ccv('commission_structure_type_fee_percentage') }}" placeholder="Enter percentage of total purchase price (e.g., 6)"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_commission_structure_type" data-cc-values="other">
                                    <input type="text" name="cc[commission_structure_type_fee_other]" class="form-control form-control-sm" value="{{ $ccv('commission_structure_type_fee_other') }}" placeholder="Enter compensation for the Buyer's Broker Commission Fee (e.g., 3% if the sale price is under $500000, 2% if over $500000)">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Seller: Interested in Offering Lease Agreement --}}
                    <div class="cc-group">
                        <label class="cc-label">Interested in Offering a Lease Agreement</label>
                        <select name="cc[interested_purchase_fee_type]" class="form-control form-control-sm"
                                onchange="ccTrigger(this, 'cc_interested_purchase_fee_type')">
                            <option value="">Select</option>
                            <option value="Yes" {{ $ccv('interested_purchase_fee_type') === 'Yes' ? 'selected' : '' }}>Yes</option>
                            <option value="No" {{ $ccv('interested_purchase_fee_type') === 'No' ? 'selected' : '' }}>No</option>
                        </select>
                        <div class="cc-sub mt-2">
                            <div class="cc-conditional" data-cc-parent="cc_interested_purchase_fee_type" data-cc-values="Yes">
                                <label class="cc-label">Seller's Broker Leasing Fee</label>
                                @php
                                    $sellerLeasingFeeOpts = in_array($propertyType, ['residential','income','vacant_land'])
                                        ? config('agent_preset_compensation.seller.seller_leasing_fee_type.residential_income_vacant_land', [])
                                        : config('agent_preset_compensation.seller.seller_leasing_fee_type.commercial_business', []);
                                    $isSellerLeasingResidential = in_array($propertyType, ['residential','income','vacant_land']);
                                @endphp
                                <select name="cc[seller_leasing_fee_type]" class="form-control form-control-sm mb-2"
                                        onchange="ccTrigger(this, 'cc_seller_leasing_fee_type')">
                                    <option value="">Select</option>
                                    @foreach($sellerLeasingFeeOpts as $optVal => $optLabel)
                                        <option value="{{ $optVal }}" {{ $ccv('seller_leasing_fee_type') === $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                                    @endforeach
                                </select>
                                {{-- Flat Fee (both property type groups) --}}
                                <div class="cc-conditional" data-cc-parent="cc_seller_leasing_fee_type" data-cc-values="Flat Fee">
                                    <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[seller_leasing_gross_purchase_fee_flat_amount]" class="form-control" value="{{ $ccv('seller_leasing_gross_purchase_fee_flat_amount') }}" placeholder="Enter flat fee amount (e.g., 5000)"></div>
                                </div>
                                @if($isSellerLeasingResidential)
                                {{-- Residential / Income / Vacant Land sub-fields --}}
                                <div class="cc-conditional" data-cc-parent="cc_seller_leasing_fee_type" data-cc-values="Percentage of the Gross Lease Value">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[seller_leasing_gross]" class="form-control" value="{{ $ccv('seller_leasing_gross') }}" placeholder="Enter percentage of the gross lease value (e.g., 10)"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_seller_leasing_fee_type" data-cc-values="Percentage of the Rent Due Each Rental Period">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[seller_leasing_gross_rental]" class="form-control" value="{{ $ccv('seller_leasing_gross_rental') }}" placeholder="Enter percentage of the rent due each rental period (e.g., 10)"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_seller_leasing_fee_type" data-cc-values="Percentage of the First Month's Rent">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[seller_leasing_gross_month_rent]" class="form-control" value="{{ $ccv('seller_leasing_gross_month_rent') }}" placeholder="Enter percentage of month's rent (e.g., 100)"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_seller_leasing_fee_type" data-cc-values="other">
                                    <input type="text" name="cc[seller_leasing_gross_purchase_fee_other]" class="form-control form-control-sm" value="{{ $ccv('seller_leasing_gross_purchase_fee_other') }}" placeholder="Enter lease fee structure (e.g., 100% of First Month's Rent, or a Tiered Schedule for Multi-Year Leases)">
                                </div>
                                @else
                                {{-- Commercial / Business sub-fields --}}
                                <div class="cc-conditional" data-cc-parent="cc_seller_leasing_fee_type" data-cc-values="Percentage of Net Aggregate Rent">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[seller_leasing_gross_other]" class="form-control" value="{{ $ccv('seller_leasing_gross_other') }}" placeholder="Enter percentage of net aggregate rent (e.g., 6)"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_seller_leasing_fee_type" data-cc-values="Percentage of Gross Rent">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[seller_leasing_gross_percentage]" class="form-control" value="{{ $ccv('seller_leasing_gross_percentage') }}" placeholder="Enter the percentage of the gross rent (e.g., 6)"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_seller_leasing_fee_type" data-cc-values="Percentage of Month's Rent">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[seller_leasing_gross_month_rent]" class="form-control" value="{{ $ccv('seller_leasing_gross_month_rent') }}" placeholder="Enter percentage of month's rent (e.g., 100)"><span class="input-group-text">%</span></div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                @if($role === 'landlord')
                    {{-- Landlord's Broker Lease Fee --}}
                    @php
                        $llPurchFeeOpts = ($propertyType === 'residential')
                            ? config('agent_preset_compensation.landlord.purchase_fee_type.residential', [])
                            : config('agent_preset_compensation.landlord.purchase_fee_type.commercial', []);
                    @endphp
                    <div class="cc-group">
                        <label class="cc-label">Landlord's Broker Lease Fee</label>
                        <select name="cc[purchase_fee_type]" class="form-control form-control-sm"
                                onchange="ccTrigger(this, 'cc_purchase_fee_type')">
                            <option value="">Select</option>
                            @foreach($llPurchFeeOpts as $optVal => $optLabel)
                                <option value="{{ $optVal }}" {{ $ccv('purchase_fee_type') === $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                            @endforeach
                        </select>
                        <div class="cc-sub mt-2">
                            @if($propertyType === 'residential')
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="Percentage of the Rent Due Each Rental Period">
                                <div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_rental_period]" class="form-control" value="{{ $ccv('purchase_fee_rental_period') }}" placeholder="Enter percentage of the rent due each rental period (e.g., 10)"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="Percentage of the Gross Lease Value">
                                <div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_percentage_combo]" class="form-control" value="{{ $ccv('purchase_fee_percentage_combo') }}" placeholder="Enter percentage of the gross lease value (e.g., 10)"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="Percentage of the First Month's Rent">
                                <div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_flat_combo]" class="form-control" value="{{ $ccv('purchase_fee_flat_combo') }}" placeholder="Enter percentage of the first month's rent (e.g., 100)"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="Flat Fee">
                                <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[purchase_fee_flat]" class="form-control" value="{{ $ccv('purchase_fee_flat') }}" placeholder="Enter flat fee amount (e.g., 5000)"></div>
                            </div>
                            @else
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="Percentage of the Net Aggregate Rent">
                                <div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_net_aggregate]" class="form-control" value="{{ $ccv('purchase_fee_net_aggregate') }}" placeholder="Enter percentage of the net aggregate rent (e.g., 5)"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="Percentage of the Gross Rent">
                                <div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_gross_rent]" class="form-control" value="{{ $ccv('purchase_fee_gross_rent') }}" placeholder="Enter percentage of the gross rent (e.g., 5)"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="Percentage of Month's Rent">
                                <div class="row g-1">
                                    <div class="col-8"><div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_monthly_percentage]" class="form-control" value="{{ $ccv('purchase_fee_monthly_percentage') }}" placeholder="Enter percentage of month's rent (e.g., 100)"><span class="input-group-text">%</span></div></div>
                                    <div class="col-4"><div class="input-group input-group-sm"><span class="input-group-text">#</span><input type="number" name="cc[purchase_fee_months]" class="form-control" value="{{ $ccv('purchase_fee_months') }}" placeholder="months"></div></div>
                                </div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="Flat Fee">
                                <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[purchase_fee_flat_commercial]" class="form-control" value="{{ $ccv('purchase_fee_flat_commercial') }}" placeholder="Enter flat fee amount (e.g., 3000)"></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="other">
                                <input type="text" name="cc[purchase_fee_other_commercial]" class="form-control form-control-sm" value="{{ $ccv('purchase_fee_other_commercial') }}" placeholder="Enter lease fee structure (e.g., 100% of First Month's Rent, or a Tiered Schedule for Multi-Year Leases)">
                            </div>
                            @endif
                            @if($propertyType === 'residential')
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="other">
                                <input type="text" name="cc[purchase_fee_other]" class="form-control form-control-sm" value="{{ $ccv('purchase_fee_other') }}" placeholder="Enter lease fee structure (e.g., 100% of First Month's Rent, or a Tiered Schedule for Multi-Year Leases)">
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Broker Fee Timing --}}
                    @php
                        $llBftOpts = ($propertyType === 'residential')
                            ? config('agent_preset_compensation.landlord.broker_fee_timing.residential', [])
                            : config('agent_preset_compensation.landlord.broker_fee_timing.commercial', []);
                    @endphp
                    <div class="cc-group">
                        <label class="cc-label">Payment Timing for Broker Fees</label>
                        <select name="cc[broker_fee_timing]" class="form-control form-control-sm"
                                onchange="ccTrigger(this, 'cc_broker_fee_timing')">
                            <option value="">Select</option>
                            @foreach($llBftOpts as $optVal => $optLabel)
                                <option value="{{ $optVal }}" {{ $ccv('broker_fee_timing') === $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                            @endforeach
                        </select>
                        <div class="cc-sub mt-2">
                            <div class="cc-conditional" data-cc-parent="cc_broker_fee_timing" data-cc-values="Deducted from Rent Collected">
                                <div class="input-group input-group-sm"><span class="input-group-text">#</span><input type="number" name="cc[broker_fee_days_from_rent]" class="form-control" value="{{ $ccv('broker_fee_days_from_rent') }}" placeholder="Calendar days"></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_broker_fee_timing" data-cc-values="Paid Within Calendar Days After Executed Lease">
                                <div class="input-group input-group-sm"><span class="input-group-text">#</span><input type="number" name="cc[broker_fee_days_after_lease]" class="form-control" value="{{ $ccv('broker_fee_days_after_lease') }}" placeholder="Calendar days"></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_broker_fee_timing" data-cc-values="other|Other">
                                <input type="text" name="cc[broker_fee_timing_other]" class="form-control form-control-sm" value="{{ $ccv('broker_fee_timing_other') }}" placeholder="Describe timing arrangement">
                            </div>
                        </div>
                    </div>

                    {{-- Lease Renewal Fee --}}
                    @php
                        $llRenewalOpts = ($propertyType === 'residential')
                            ? config('agent_preset_compensation.landlord.renewal_fee_type.residential', [])
                            : config('agent_preset_compensation.landlord.renewal_fee_type.commercial', []);
                    @endphp
                    <div class="cc-group">
                        <label class="cc-label">Lease Renewal/Extension Fee</label>
                        <select name="cc[renewal_fee_type]" class="form-control form-control-sm"
                                onchange="ccTrigger(this, 'cc_renewal_fee_type')">
                            <option value="">Select</option>
                            @foreach($llRenewalOpts as $optVal => $optLabel)
                                <option value="{{ $optVal }}" {{ $ccv('renewal_fee_type') === $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                            @endforeach
                        </select>
                        <div class="cc-sub mt-2">
                            @if($propertyType === 'residential')
                            {{-- Residential: Percentage of the Rent Due Each Rental Period --}}
                            <div class="cc-conditional" data-cc-parent="cc_renewal_fee_type" data-cc-values="Percentage of the Rent Due Each Rental Period">
                                <div class="input-group input-group-sm"><input type="number" name="cc[renewal_fee_percentage]" class="form-control" value="{{ $ccv('renewal_fee_percentage') }}" placeholder="Enter percentage of the rent due each rental period (e.g., 10)"><span class="input-group-text">%</span></div>
                            </div>
                            {{-- Residential: Percentage of the Gross Lease Value --}}
                            <div class="cc-conditional" data-cc-parent="cc_renewal_fee_type" data-cc-values="Percentage of the Gross Lease Value">
                                <div class="input-group input-group-sm"><input type="number" name="cc[renewal_fee_lease_value]" class="form-control" value="{{ $ccv('renewal_fee_lease_value') }}" placeholder="Enter percentage of the gross lease value (e.g., 10)"><span class="input-group-text">%</span></div>
                            </div>
                            {{-- Residential: Percentage of the First Month's Rent --}}
                            <div class="cc-conditional" data-cc-parent="cc_renewal_fee_type" data-cc-values="Percentage of the First Month's Rent">
                                <div class="input-group input-group-sm"><input type="number" name="cc[renewal_fee_first_month]" class="form-control" value="{{ $ccv('renewal_fee_first_month') }}" placeholder="Enter percentage of first month's rent (e.g., 100)"><span class="input-group-text">%</span></div>
                            </div>
                            @else
                            {{-- Commercial: Percentage of the Net Aggregate Rent --}}
                            <div class="cc-conditional" data-cc-parent="cc_renewal_fee_type" data-cc-values="Percentage of the Net Aggregate Rent">
                                <div class="input-group input-group-sm"><input type="number" name="cc[renewal_fee_percentage]" class="form-control" value="{{ $ccv('renewal_fee_percentage') }}" placeholder="Enter percentage of the net aggregate rent (e.g., 5)"><span class="input-group-text">%</span></div>
                            </div>
                            {{-- Commercial: Percentage of the Gross Rent --}}
                            <div class="cc-conditional" data-cc-parent="cc_renewal_fee_type" data-cc-values="Percentage of the Gross Rent">
                                <div class="input-group input-group-sm"><input type="number" name="cc[renewal_fee_lease_value]" class="form-control" value="{{ $ccv('renewal_fee_lease_value') }}" placeholder="Enter percentage of the gross rent (e.g., 5)"><span class="input-group-text">%</span></div>
                            </div>
                            {{-- Commercial: Percentage of Month's Rent --}}
                            <div class="cc-conditional" data-cc-parent="cc_renewal_fee_type" data-cc-values="Percentage of Month's Rent">
                                <div class="input-group input-group-sm"><input type="number" name="cc[renewal_fee_first_month]" class="form-control" value="{{ $ccv('renewal_fee_first_month') }}" placeholder="Enter percentage of month's rent (e.g., 100)"><span class="input-group-text">%</span></div>
                            </div>
                            @endif
                            {{-- Flat Fee (both property type groups) --}}
                            <div class="cc-conditional" data-cc-parent="cc_renewal_fee_type" data-cc-values="Flat Fee">
                                <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[renewal_fee_flat_fee]" class="form-control" value="{{ $ccv('renewal_fee_flat_fee') }}" placeholder="Enter flat fee amount (e.g., 2000)"></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_renewal_fee_type" data-cc-values="other">
                                <input type="text" name="cc[renewal_fee_custom]" class="form-control form-control-sm" value="{{ $ccv('renewal_fee_custom') }}" placeholder="Enter commission structure (e.g., $500 flat fee plus 5% of the gross lease value)">
                            </div>
                        </div>
                    </div>

                    {{-- Tenant Broker Commission Structure --}}
                    @if($propertyType === 'residential')
                    <div class="cc-group">
                        <label class="cc-label">Tenant's Broker Commission Structure</label>
                        <select name="cc[tenant_broker_commission_structure]" class="form-control form-control-sm"
                                onchange="ccTrigger(this, 'cc_tenant_broker_commission_structure')">
                            <option value="">Select</option>
                            @foreach(config('agent_preset_compensation.landlord.tenant_broker_commission_structure', []) as $optVal => $optLabel)
                                <option value="{{ $optVal }}" {{ $ccv('tenant_broker_commission_structure') === $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                            @endforeach
                        </select>
                        <div class="cc-sub mt-2">
                            <div class="cc-conditional" data-cc-parent="cc_tenant_broker_commission_structure"
                                 data-cc-values="Landlord's Broker to Compensate Tenant's Broker from Landlord's Broker Commission|Landlord to Pay Tenant's Broker Separately">
                                <label class="cc-label">Tenant's Broker Commission Fee</label>
                                <select name="cc[tenant_broker_fee_structure]" class="form-control form-control-sm mb-2"
                                        onchange="ccTrigger(this, 'cc_tenant_broker_fee_structure')">
                                    <option value="">Select</option>
                                    @foreach(config('agent_preset_compensation.landlord.tenant_broker_fee_structure', []) as $optVal => $optLabel)
                                        <option value="{{ $optVal }}" {{ $ccv('tenant_broker_fee_structure') === $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                                    @endforeach
                                </select>
                                <div class="cc-conditional" data-cc-parent="cc_tenant_broker_fee_structure" data-cc-values="Percentage of the Rent Due Each Rental Period">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[tenant_broker_percentage]" class="form-control" value="{{ $ccv('tenant_broker_percentage') }}" placeholder="Enter percentage of the rent due each rental period (e.g., 5)"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_tenant_broker_fee_structure" data-cc-values="Percentage of the Gross Lease Value">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[tenant_broker_gross_lease]" class="form-control" value="{{ $ccv('tenant_broker_gross_lease') }}" placeholder="Enter percentage of the gross lease value (e.g., 5)"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_tenant_broker_fee_structure" data-cc-values="Percentage of the First Month's Rent">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[tenant_broker_first_month_rent]" class="form-control" value="{{ $ccv('tenant_broker_first_month_rent') }}" placeholder="Enter percentage of the first month's rent (e.g., 50)"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_tenant_broker_fee_structure" data-cc-values="Flat fee|Flat Fee">
                                    <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[tenant_broker_flat_fee]" class="form-control" value="{{ $ccv('tenant_broker_flat_fee') }}" placeholder="Enter flat fee amount (e.g., 1000)"></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_tenant_broker_fee_structure" data-cc-values="Other">
                                    <input type="text" name="cc[tenant_broker_other]" class="form-control form-control-sm" value="{{ $ccv('tenant_broker_other') }}" placeholder="Enter Tenant's Broker commission arrangement (e.g., $500 bonus plus 2% of gross lease value)">
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Interested in Selling --}}
                    <div class="cc-group">
                        <label class="cc-label">Interested in Selling the Property</label>
                        <select name="cc[interested_in_selling]" class="form-control form-control-sm"
                                onchange="ccTrigger(this, 'cc_interested_in_selling')">
                            <option value="">Select</option>
                            <option value="Yes" {{ $ccv('interested_in_selling') === 'Yes' ? 'selected' : '' }}>Yes</option>
                            <option value="No" {{ $ccv('interested_in_selling') === 'No' ? 'selected' : '' }}>No</option>
                        </select>
                        <div class="cc-sub mt-2">
                            <div class="cc-conditional" data-cc-parent="cc_interested_in_selling" data-cc-values="Yes">
                                <label class="cc-label">Landlord's Broker Purchase Fee</label>
                                <select name="cc[interested_in_selling_type]" class="form-control form-control-sm mb-2"
                                        onchange="ccTrigger(this, 'cc_interested_in_selling_type')">
                                    <option value="">Select</option>
                                    @foreach(config('agent_preset_compensation.landlord.selling_fee_type', []) as $optVal => $optLabel)
                                        <option value="{{ $optVal }}" {{ $ccv('interested_in_selling_type') === $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                                    @endforeach
                                </select>
                                <div class="cc-conditional" data-cc-parent="cc_interested_in_selling_type" data-cc-values="Percentage of the Total Purchase Price">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[landlord_broker_purchase_price]" class="form-control" value="{{ $ccv('landlord_broker_purchase_price') }}" placeholder="Enter percentage of total purchase price (e.g., 6)"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_interested_in_selling_type" data-cc-values="Percentage of the Total Purchase Price + Flat Fee">
                                    <div class="row g-2">
                                        <div class="col-md-6"><div class="input-group input-group-sm"><input type="number" name="cc[landlord_broker_percentage_price]" class="form-control" value="{{ $ccv('landlord_broker_percentage_price') }}" placeholder="Enter percentage of purchase price (e.g., 2)"><span class="input-group-text">%</span></div></div>
                                        <div class="col-md-1 text-center pt-1">+</div>
                                        <div class="col-md-5"><div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[landlord_broker_dollar_price]" class="form-control" value="{{ $ccv('landlord_broker_dollar_price') }}" placeholder="Enter flat fee amount (e.g., 2000)"></div></div>
                                    </div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_interested_in_selling_type" data-cc-values="Flat Fee">
                                    <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[landlord_broker_flate_fee]" class="form-control" value="{{ $ccv('landlord_broker_flate_fee') }}" placeholder="Enter flat fee amount (e.g., 5000)"></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_interested_in_selling_type" data-cc-values="Other">
                                    <input type="text" name="cc[landlord_broker_other]" class="form-control form-control-sm" value="{{ $ccv('landlord_broker_other') }}" placeholder="Enter purchase fee structure (e.g., Tiered: 5% on the first $500000, 3% on any amount above $500000)">
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if($role === 'tenant')
                    {{-- Tenant's Broker Commission Structure --}}
                    <div class="cc-group">
                        <label class="cc-label">Tenant's Broker Commission Structure</label>
                        <select name="cc[commission_structure]" class="form-control form-control-sm">
                            <option value="">Select</option>
                            @foreach(config('agent_preset_compensation.tenant.commission_structure', []) as $optVal => $optLabel)
                                <option value="{{ $optVal }}" {{ $ccv('commission_structure') === $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Tenant's Broker Lease Fee --}}
                    @php
                        $tenantLeaseFeeOpts = ($propertyType === 'residential')
                            ? config('agent_preset_compensation.tenant.lease_fee_type.residential', [])
                            : (($propertyType === 'commercial')
                                ? config('agent_preset_compensation.tenant.lease_fee_type.commercial', [])
                                : ['Flat Fee' => 'Flat Fee', 'other' => 'Other']);
                    @endphp
                    <div class="cc-group">
                        <label class="cc-label">Tenant's Broker Lease Fee</label>
                        <select name="cc[lease_fee_type]" class="form-control form-control-sm"
                                onchange="ccTrigger(this, 'cc_lease_fee_type')">
                            <option value="">Select</option>
                            @foreach($tenantLeaseFeeOpts as $optVal => $optLabel)
                                <option value="{{ $optVal }}" {{ $ccv('lease_fee_type') === $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                            @endforeach
                        </select>
                        <div class="cc-sub mt-2">
                            <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="Flat Fee">
                                <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[lease_fee_flat]" class="form-control" value="{{ $ccv('lease_fee_flat') }}" placeholder="Enter flat fee amount (e.g., 5000)"></div>
                            </div>
                            @if($propertyType === 'residential')
                            <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="Percentage of Monthly Rent">
                                <div class="input-group input-group-sm"><input type="number" name="cc[lease_fee_percentage_monthly_rent]" class="form-control" value="{{ $ccv('lease_fee_percentage_monthly_rent') }}" placeholder="Enter percentage of monthly rent (e.g., 100)"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="Percentage of the Gross Lease Value">
                                <div class="input-group input-group-sm"><input type="number" name="cc[lease_fee_percentage]" class="form-control" value="{{ $ccv('lease_fee_percentage') }}" placeholder="Enter percentage of the gross lease value (e.g., 10)"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="Flat Fee + Percentage of the Gross Lease Value">
                                <div class="row g-2">
                                    <div class="col-md-5"><div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[lease_fee_flat_combo]" class="form-control" value="{{ $ccv('lease_fee_flat_combo') }}" placeholder="Enter flat fee amount (e.g., 1000)"></div></div>
                                    <div class="col-md-1 text-center pt-1">+</div>
                                    <div class="col-md-6"><div class="input-group input-group-sm"><input type="number" name="cc[lease_fee_percentage_combo]" class="form-control" value="{{ $ccv('lease_fee_percentage_combo') }}" placeholder="Enter percentage of the gross lease value (e.g., 7)"><span class="input-group-text">%</span></div></div>
                                </div>
                            </div>
                            @elseif($propertyType === 'commercial')
                            <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="Percentage of the Net Aggregate Rent">
                                <div class="input-group input-group-sm"><input type="number" name="cc[lease_fee_percentage_net]" class="form-control" value="{{ $ccv('lease_fee_percentage_net') }}" placeholder="Enter percentage of the net aggregate rent (e.g., 6)"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="Flat Fee + Percentage of the Net Aggregate Rent">
                                <div class="row g-2">
                                    <div class="col-md-5"><div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[lease_fee_flat_combo_net]" class="form-control" value="{{ $ccv('lease_fee_flat_combo_net') }}" placeholder="Enter flat fee amount (e.g., 1500)"></div></div>
                                    <div class="col-md-1 text-center pt-1">+</div>
                                    <div class="col-md-6"><div class="input-group input-group-sm"><input type="number" name="cc[lease_fee_percentage_combo_net]" class="form-control" value="{{ $ccv('lease_fee_percentage_combo_net') }}" placeholder="Enter percentage of the net aggregate rent (e.g., 6)"><span class="input-group-text">%</span></div></div>
                                </div>
                            </div>
                            @endif
                            <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="other">
                                <input type="text" name="cc[lease_fee_other]" class="form-control form-control-sm" value="{{ $ccv('lease_fee_other') }}" placeholder="Enter the total lease fee amount and payment structure for the Tenant's Broker (e.g., $1500 upfront, $2000 at lease execution)">
                            </div>
                        </div>
                    </div>

                    {{-- Broker Fee Timing (Tenant) --}}
                    @if(in_array($propertyType, ['residential', 'commercial']))
                    @php
                        $tenantBftOpts = ($propertyType === 'residential')
                            ? config('agent_preset_compensation.tenant.broker_fee_timing.residential', [])
                            : config('agent_preset_compensation.tenant.broker_fee_timing.commercial', []);
                    @endphp
                    <div class="cc-group">
                        <label class="cc-label">Payment Timing for Broker Fees</label>
                        <select name="cc[broker_fee_timing]" class="form-control form-control-sm"
                                onchange="ccTrigger(this, 'cc_broker_fee_timing')">
                            <option value="">Select</option>
                            @foreach($tenantBftOpts as $optVal => $optLabel)
                                <option value="{{ $optVal }}" {{ $ccv('broker_fee_timing') === $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                            @endforeach
                        </select>
                        <div class="cc-sub mt-2">
                            <div class="cc-conditional" data-cc-parent="cc_broker_fee_timing" data-cc-values="Deducted from Rent Collected">
                                <div class="input-group input-group-sm"><span class="input-group-text">#</span><input type="number" name="cc[broker_fee_days_from_rent]" class="form-control" value="{{ $ccv('broker_fee_days_from_rent') }}" placeholder="Calendar days"></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_broker_fee_timing" data-cc-values="Paid Within Calendar Days After Executed Lease">
                                <div class="input-group input-group-sm"><span class="input-group-text">#</span><input type="number" name="cc[broker_fee_days_after_lease]" class="form-control" value="{{ $ccv('broker_fee_days_after_lease') }}" placeholder="Calendar days"></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_broker_fee_timing" data-cc-values="Paid Within Calendar Days of Tenant Rent Payment">
                                <div class="input-group input-group-sm"><span class="input-group-text">#</span><input type="number" name="cc[broker_fee_days_after_rent]" class="form-control" value="{{ $ccv('broker_fee_days_after_rent') }}" placeholder="Calendar days"></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_broker_fee_timing" data-cc-values="other|Other">
                                <input type="text" name="cc[broker_fee_timing_other]" class="form-control form-control-sm" value="{{ $ccv('broker_fee_timing_other') }}" placeholder="Describe timing arrangement">
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Interested in Purchase --}}
                    <div class="cc-group">
                        <label class="cc-label">Interested in Purchasing a Property</label>
                        <select name="cc[interested_purchase_fee_type]" class="form-control form-control-sm"
                                onchange="ccTrigger(this, 'cc_interested_purchase_fee_type')">
                            <option value="">Select</option>
                            <option value="Yes" {{ $ccv('interested_purchase_fee_type') === 'Yes' ? 'selected' : '' }}>Yes</option>
                            <option value="No" {{ $ccv('interested_purchase_fee_type') === 'No' ? 'selected' : '' }}>No</option>
                        </select>
                        <div class="cc-sub mt-2">
                            <div class="cc-conditional" data-cc-parent="cc_interested_purchase_fee_type" data-cc-values="Yes">
                                <label class="cc-label">Tenant's Broker Purchase Fee</label>
                                <select name="cc[purchase_fee_type]" class="form-control form-control-sm mb-2"
                                        onchange="ccTrigger(this, 'cc_purchase_fee_type_tenant')">
                                    <option value="">Select</option>
                                    @foreach(config('agent_preset_compensation.tenant.purchase_fee_type', []) as $optVal => $optLabel)
                                        <option value="{{ $optVal }}" {{ $ccv('purchase_fee_type') === $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                                    @endforeach
                                </select>
                                <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type_tenant" data-cc-values="Flat Fee">
                                    <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[purchase_fee_flat]" class="form-control" value="{{ $ccv('purchase_fee_flat') }}" placeholder="Enter flat fee amount (e.g., 5000)"></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type_tenant" data-cc-values="Percentage of the Total Purchase Price">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_percentage]" class="form-control" value="{{ $ccv('purchase_fee_percentage') }}" placeholder="Enter percentage of the total purchase price (e.g., 3)"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type_tenant" data-cc-values="Percentage of the Total Purchase Price + Flat Fee">
                                    <div class="row g-2">
                                        <div class="col-md-6"><div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_percentage_combo]" class="form-control" value="{{ $ccv('purchase_fee_percentage_combo') }}" placeholder="Enter percentage of the total purchase price (e.g., 2)"><span class="input-group-text">%</span></div></div>
                                        <div class="col-md-1 text-center pt-1">+</div>
                                        <div class="col-md-5"><div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[purchase_fee_flat_combo]" class="form-control" value="{{ $ccv('purchase_fee_flat_combo') }}" placeholder="Enter flat fee amount (e.g., 3000)"></div></div>
                                    </div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type_tenant" data-cc-values="other">
                                    <input type="text" name="cc[purchase_fee_other]" class="form-control form-control-sm" value="{{ $ccv('purchase_fee_other') }}" placeholder="Enter purchase fee amount (e.g., $1000 upfront + 2% at closing)">
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ── SHARED LEGAL & AGREEMENT TERMS ──────────────────────────────── --}}
                <div class="cc-section-divider"></div>
                <div class="cc-section-heading">Agreement &amp; Legal Terms</div>

                {{-- Brokerage Relationship --}}
                <div class="cc-group">
                    <label class="cc-label">Brokerage Relationship</label>
                    <select name="cc[brokerage_relationship]" class="form-control form-control-sm">
                        <option value="">Select</option>
                        @foreach(config('agent_preset_compensation.common.brokerage_relationship', []) as $optVal => $optLabel)
                            <option value="{{ $optVal }}" {{ $ccv('brokerage_relationship') === $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Agreement Timeframe --}}
                @php
                    // buyer stores 'custom'; all other roles store 'Other' (per preset editor convention)
                    $aatCustomVal = ($role === 'buyer') ? 'custom' : 'Other';
                @endphp
                <div class="cc-group">
                    @php
                        $aaLabels = [
                            'buyer'    => 'Buyer Agency Agreement Timeframe:',
                            'seller'   => 'Seller Agency Agreement Timeframe:',
                            'tenant'   => 'Tenant Agency Agreement Timeframe:',
                            'landlord' => 'Landlord Agency Agreement Timeframe:',
                        ];
                    @endphp
                    <label class="cc-label">{{ $aaLabels[$role] ?? 'Agency Agreement Timeframe:' }}</label>
                    <select name="cc[agency_agreement_timeframe]" class="form-control form-control-sm"
                            onchange="ccTrigger(this, 'cc_agency_agreement_timeframe')">
                        <option value="">Select</option>
                        @foreach(config('agent_preset_compensation.common.agency_agreement_timeframe', []) as $optVal => $optLabel)
                            <option value="{{ $optVal }}" {{ $ccv('agency_agreement_timeframe') === $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                        @endforeach
                        {{-- Custom/Other option excluded from config (stored value differs by role) --}}
                        <option value="{{ $aatCustomVal }}" {{ $ccv('agency_agreement_timeframe') === $aatCustomVal ? 'selected' : '' }}>Other</option>
                    </select>
                    <div class="cc-sub mt-2">
                        <div class="cc-conditional" data-cc-parent="cc_agency_agreement_timeframe" data-cc-values="custom|Other">
                            <input type="text" name="cc[agency_agreement_custom]" class="form-control form-control-sm" value="{{ $ccv('agency_agreement_custom') }}" placeholder="Enter agreement timeframe (e.g., 45 Days)">
                        </div>
                    </div>
                </div>

                {{-- Protection Period --}}
                <div class="cc-group">
                    <label class="cc-label">Protection Period (Days)</label>
                    <input type="number" name="cc[protection_period]" class="form-control form-control-sm"
                           value="{{ $ccv('protection_period') }}" placeholder="Enter protection period (e.g., 90 Days)">
                </div>

                {{-- Early Termination Fee --}}
                @php
                    $etfYesVal = ($role === 'tenant') ? 'Yes' : 'yes';
                    $etfNoVal  = ($role === 'tenant') ? 'No'  : 'no';
                @endphp
                <div class="cc-group">
                    <label class="cc-label">Early Termination Fee</label>
                    <select name="cc[early_termination_fee_option]" class="form-control form-control-sm"
                            onchange="ccTrigger(this, 'cc_early_termination_fee_option')">
                        <option value="">Select</option>
                        <option value="{{ $etfYesVal }}" {{ $ccv('early_termination_fee_option') === $etfYesVal ? 'selected' : '' }}>Yes</option>
                        <option value="{{ $etfNoVal }}" {{ $ccv('early_termination_fee_option') === $etfNoVal ? 'selected' : '' }}>No</option>
                    </select>
                    <div class="cc-sub mt-2">
                        <div class="cc-conditional" data-cc-parent="cc_early_termination_fee_option" data-cc-values="{{ $etfYesVal }}">
                            <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[early_termination_fee_amount]" class="form-control" value="{{ $ccv('early_termination_fee_amount') }}" placeholder="Enter early termination fee amount (e.g., 2000)"></div>
                        </div>
                    </div>
                </div>

                {{-- Retainer Fee (buyer, seller, tenant only) --}}
                @if(in_array($role, ['buyer', 'seller', 'tenant']))
                @php
                    $rtfYesVal = ($role === 'tenant') ? 'Yes' : 'yes';
                    $rtfNoVal  = ($role === 'tenant') ? 'No'  : 'no';
                @endphp
                <div class="cc-group">
                    <label class="cc-label">Retainer Fee</label>
                    <select name="cc[retainer_fee_option]" class="form-control form-control-sm"
                            onchange="ccTrigger(this, 'cc_retainer_fee_option')">
                        <option value="">Select</option>
                        <option value="{{ $rtfYesVal }}" {{ $ccv('retainer_fee_option') === $rtfYesVal ? 'selected' : '' }}>Yes</option>
                        <option value="{{ $rtfNoVal }}" {{ $ccv('retainer_fee_option') === $rtfNoVal ? 'selected' : '' }}>No</option>
                    </select>
                    <div class="cc-sub mt-2">
                        <div class="cc-conditional" data-cc-parent="cc_retainer_fee_option" data-cc-values="{{ $rtfYesVal }}">
                            <div class="input-group input-group-sm mb-2"><span class="input-group-text">$</span><input type="text" name="cc[retainer_fee_amount]" class="form-control" value="{{ $ccv('retainer_fee_amount') }}" placeholder="Enter retainer fee amount (e.g., 500)"></div>
                            <select name="cc[retainer_fee_application]" class="form-control form-control-sm">
                                <option value="">Select</option>
                                @if($role === 'tenant')
                                    <option value="applied" {{ $ccv('retainer_fee_application') === 'applied' ? 'selected' : '' }}>Applied toward final compensation</option>
                                    <option value="additional" {{ $ccv('retainer_fee_application') === 'additional' ? 'selected' : '' }}>Charged in addition to final compensation</option>
                                @else
                                    <option value="Applied toward final compensation" {{ $ccv('retainer_fee_application') === 'Applied toward final compensation' ? 'selected' : '' }}>Applied toward final compensation</option>
                                    <option value="Charged in addition to final compensation" {{ $ccv('retainer_fee_application') === 'Charged in addition to final compensation' ? 'selected' : '' }}>Charged in addition to final compensation</option>
                                @endif
                            </select>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Additional Broker Notes --}}
                <div class="cc-group">
                    <label class="cc-label">Additional Terms / Broker Notes</label>
                    <textarea name="cc[additional_details_broker]" rows="3"
                              class="form-control form-control-sm"
                              placeholder="Enter any additional notes, clarifications, requested changes, or conditions you want the agent to review before accepting.">{{ $ccv('additional_details_broker') }}</textarea>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="tab-nav-btn" onclick="switchTab('tab-services-btn')">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back: Services
                    </button>
                    @if($viewerIsAgent)
                    <button type="button" class="tab-nav-btn" onclick="switchTab('tab-referral-btn')">
                        Next: Referral Fee <i class="fa-solid fa-arrow-right ms-1"></i>
                    </button>
                    @else
                    <button type="button" class="tab-nav-btn" onclick="switchTab('tab-notes-btn')">
                        Next: Additional Terms <i class="fa-solid fa-arrow-right ms-1"></i>
                    </button>
                    @endif
                </div>
            </div>

            {{-- Referral Fee tab (agents only) --}}
            @if($viewerIsAgent)
            <div class="tab-pane fade counter-tab-pane" id="tab-referral" role="tabpanel">
                <p class="text-muted small mb-3">
                    Specify the referral fee percentage for this agent-to-agent referral arrangement. This tab is only visible to agents.
                </p>
                <div class="cc-group">
                    <label class="cc-label">Referral Fee (%)</label>
                    <div class="input-group input-group-sm" style="max-width:220px;">
                        <input type="number" name="cc[referral_fee_percent]" class="form-control"
                               value="{{ $ccv('referral_fee_percent') }}"
                               min="0" max="100" step="0.01"
                               placeholder="e.g. 25">
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="text-muted small mt-2">
                        Enter the referral fee percentage offered for this agent-to-agent referral arrangement.
                    </div>
                </div>
                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="tab-nav-btn" onclick="switchTab('tab-comp-btn')">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back: Comp Terms
                    </button>
                    <button type="button" class="tab-nav-btn" onclick="switchTab('tab-notes-btn')">
                        Next: Additional Terms <i class="fa-solid fa-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>
            @endif

            {{-- Additional Terms tab --}}
            <div class="tab-pane fade counter-tab-pane" id="tab-notes" role="tabpanel">
                <p class="text-muted small mb-3">
                    Use this space to add any supplemental notes, clarifications, or conditions not covered above.
                </p>
                <div class="mb-3">
                    <label class="fw-bold form-label">Your Additional Terms / Notes <span class="text-muted fw-normal">(optional)</span></label>
                    @php
                        $addlTermsPlaceholder = "Enter any additional notes, clarifications, requested changes, or conditions you want the agent to review before accepting.";
                    @endphp
                    <textarea name="additional_terms" rows="7"
                              class="form-control @error('additional_terms') is-invalid @enderror"
                              maxlength="3000"
                              placeholder="{{ $addlTermsPlaceholder }}"
                              >{{ old('additional_terms', $pending['additional_terms'] ?? '') }}</textarea>
                    @error('additional_terms')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="text-muted small mt-1">Maximum 3,000 characters.</div>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    @if($viewerIsAgent)
                    <button type="button" class="tab-nav-btn" onclick="switchTab('tab-referral-btn')">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back: Referral Fee
                    </button>
                    @else
                    <button type="button" class="tab-nav-btn" onclick="switchTab('tab-comp-btn')">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back: Comp Terms
                    </button>
                    @endif
                    <button type="button" class="tab-nav-btn" onclick="switchTab('tab-client-btn')">
                        Next: Your Details <i class="fa-solid fa-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>

            {{-- Your Details tab --}}
            <div class="tab-pane fade counter-tab-pane" id="tab-client" role="tabpanel">
                @if($role === 'seller')
                    @include('hire-agent-direct.client-details.seller')
                @elseif($role === 'buyer')
                    @include('hire-agent-direct.client-details.buyer')
                @elseif($role === 'landlord')
                    @include('hire-agent-direct.client-details.landlord')
                @else
                    @include('hire-agent-direct.client-details.tenant')
                @endif

                <div class="d-flex justify-content-between mt-2 mb-2">
                    <button type="button" class="tab-nav-btn" onclick="switchTab('tab-notes-btn')">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back: Additional Terms
                    </button>
                    <button type="button" class="tab-nav-btn" onclick="switchTab('tab-review-btn')">
                        Next: Review <i class="fa-solid fa-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>

            {{-- Review Your Counter Terms tab --}}
            <div class="tab-pane fade counter-tab-pane" id="tab-review" role="tabpanel">
                <p class="text-muted small mb-3">
                    Review your counter terms below before submitting. Use the tabs above to make any changes.
                </p>

                {{-- Services summary — pre-rendered grouped structure; JS filters by checkbox state --}}
                <div class="mb-3" style="border:1px solid #dee2e6;border-radius:8px;overflow:hidden;">
                    <div class="ack-section-header"><i class="fa-solid fa-square-check"></i> Requested Services</div>
                    <div class="ack-section-body" id="review-services-body">
                        @php $isFirstReviewGroup = true; @endphp
                        @foreach($groupedAgentServices as $categoryLabel => $categoryServices)
                            @if(!empty($categoryServices))
                            <div data-review-category style="margin-top: {{ $isFirstReviewGroup ? '0' : '1rem' }}; display:none;">
                                <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6c757d;margin-bottom:.5rem;" data-review-category-label>{{ $categoryLabel }}</div>
                                <ul class="service-bullet-list" data-review-service-list>
                                    @foreach($categoryServices as $svc)
                                    <li data-review-service-item="{{ $svc }}" style="display:none;">{{ $svc }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            @php $isFirstReviewGroup = false; @endphp
                            @endif
                        @endforeach
                        @if(!empty($otherServices))
                        <div data-review-category style="margin-top: 1rem; display:none;">
                            <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6c757d;margin-bottom:.5rem;" data-review-category-label>Additional Services</div>
                            <ul class="service-bullet-list" data-review-service-list>
                                @foreach($otherServices as $svc)
                                <li data-review-service-item="{{ $svc }}" style="display:none;">{{ $svc }}</li>
                                @endforeach
                            </ul>
                        </div>
                        @endif
                        <p id="review-services-empty" class="text-muted small mb-0"><em>Complete the Services tab, then return here to review your selection.</em></p>
                    </div>
                </div>

                {{-- Comp terms — pre-rendered from $reviewCompRows (referral fee always excluded; see dedicated section below).
                     populateReviewTab() re-renders from the same server-computed JSON to avoid raw cc[] concatenation. --}}
                @if(count($reviewCompRows) > 0)
                <div class="mb-3" style="border:1px solid #dee2e6;border-radius:8px;overflow:hidden;">
                    <div class="ack-section-header">
                        <i class="fa-solid fa-file-lines"></i> Your Proposed Broker Compensation and Agency Agreement Terms
                    </div>
                    <div class="ack-section-body" id="review-comp-body">
                        <table class="comp-table">
                            @foreach($reviewCompRows as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td>{{ $row['value'] }}</td>
                            </tr>
                            @endforeach
                        </table>
                    </div>
                </div>
                @endif

                {{-- Referral Fee — dedicated section for agent viewers only (never inside the comp table) --}}
                @if($viewerIsAgent && !empty($referralFeeRows))
                <div class="mb-3" style="border:1px solid #dee2e6;border-radius:8px;overflow:hidden;" id="review-referral-section">
                    <div class="ack-section-header">
                        <i class="fa-solid fa-handshake"></i> Referral Fee
                    </div>
                    <div class="ack-section-body" id="review-referral-body">
                        <table class="comp-table">
                            @foreach($referralFeeRows as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td>{{ $row['value'] }}</td>
                            </tr>
                            @endforeach
                        </table>
                    </div>
                </div>
                @endif

                {{-- Additional Services Requested (JS-populated) --}}
                <div class="mb-3" style="border:1px solid #dee2e6;border-radius:8px;overflow:hidden;">
                    <div class="ack-section-header"><i class="fa-solid fa-circle-plus"></i> Additional Services Requested</div>
                    <div class="ack-section-body" id="review-addl-services-body">
                        <p class="text-muted small mb-0"><em>None entered.</em></p>
                    </div>
                </div>

                {{-- Additional Terms (JS-populated) --}}
                <div class="mb-3" style="border:1px solid #dee2e6;border-radius:8px;overflow:hidden;">
                    <div class="ack-section-header"><i class="fa-solid fa-comment-dots"></i> Your Additional Terms / Notes</div>
                    <div class="ack-section-body" id="review-notes-body">
                        <p class="text-muted small mb-0"><em>None entered.</em></p>
                    </div>
                </div>

                {{-- Your Details (JS-populated) --}}
                <div class="mb-3" style="border:1px solid #dee2e6;border-radius:8px;overflow:hidden;">
                    <div class="ack-section-header"><i class="fa-solid fa-address-card"></i> Your Details</div>
                    <div class="ack-section-body" id="review-contact-body">
                        <p class="text-muted small mb-0"><em>Complete the Your Details tab, then return here to review.</em></p>
                    </div>
                </div>

                <div class="counter-notice mt-3">
                    <i class="fa-solid fa-circle-info me-2"></i>
                    <strong>This is a counter request, not a final agreement.</strong>
                    Review your proposed counter terms below before submitting. If you need to make changes, use the tabs above to return to the appropriate section. Submitting sends your proposed counter terms to the agent for review. The agent may accept, counter, or decline before anything is finalized.
                </div>

                <div class="d-flex align-items-center gap-3 flex-wrap mt-3 mb-2">
                    <button type="button" class="tab-nav-btn" onclick="switchTab('tab-client-btn')">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back: Your Details
                    </button>
                    <button type="submit" id="counter-submit-btn" class="submit-btn btn">
                        <i class="fa-solid fa-arrow-right-arrow-left me-2"></i>Submit Counter Request
                    </button>
                </div>
            </div>

        </div>

    </form>

</div>
</div>

<script>
function switchTab(tabBtnId) {
    var btn = document.getElementById(tabBtnId);
    if (btn) btn.click();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function counterFormSubmit(form) {
    var btn = document.getElementById('counter-submit-btn');
    // Prevent double-submit only — a missing button must never block the POST.
    if (btn && btn.disabled) return false;
    // Strip commas from all $-prefixed currency text inputs before submission
    document.querySelectorAll('#counter-form .input-group input[type="text"]').forEach(function(inp) {
        var grp = inp.closest('.input-group');
        if (!grp) return;
        var hasDollar = Array.from(grp.querySelectorAll('.input-group-text')).some(function(span) {
            return span.textContent.trim() === '$';
        });
        if (hasDollar) {
            inp.value = inp.value.replace(/,/g, '');
        }
    });
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Sending\u2026';
    }
    return true;
}

// Server-side agent flag exposed to JS — single source of truth matching $viewerIsAgent in Blade.
var viewerIsAgent = {{ $viewerIsAgent ? 'true' : 'false' }};

// Pre-computed, server-formatted comp rows for the Review tab.
// Referral Fee is always excluded here; it appears in a separate dedicated section for agents.
// Using these avoids raw cc[] concatenation which cannot reproduce the canonical formatting.
var reviewCompRowsData = {!! json_encode($reviewCompRows) !!};

// Pre-computed referral fee row(s) for the agent-only dedicated section in the Review tab.
var reviewReferralRowsData = {!! json_encode($referralFeeRows) !!};

function populateReviewTab() {
    var escape = function(s) {
        return String(s).replace(/[<>&"]/g, function(c) {
            return {'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c];
        });
    };

    // ── Services (grouped display; show/hide pre-rendered items) ──────────────
    var servicesBody = document.getElementById('review-services-body');
    if (servicesBody) {
        var checkedValues = new Set();
        document.querySelectorAll('input[name="services[]"]:checked, input[name="other_services[]"]:checked').forEach(function(cb) {
            checkedValues.add(cb.value);
        });

        var hasAnyVisible = false;
        servicesBody.querySelectorAll('[data-review-service-item]').forEach(function(li) {
            var svcVal = li.getAttribute('data-review-service-item');
            var show = checkedValues.has(svcVal);
            li.style.display = show ? '' : 'none';
            if (show) hasAnyVisible = true;
        });

        // Show/hide entire category block based on whether any item within is visible
        servicesBody.querySelectorAll('[data-review-category]').forEach(function(cat) {
            var visCount = 0;
            cat.querySelectorAll('[data-review-service-item]').forEach(function(li) {
                if (li.style.display !== 'none') visCount++;
            });
            cat.style.display = visCount > 0 ? '' : 'none';
        });

        var emptyMsg = document.getElementById('review-services-empty');
        if (emptyMsg) emptyMsg.style.display = hasAnyVisible ? 'none' : '';
    }

    // ── Comp Terms — render from pre-computed server-formatted JSON rows ──────
    // Never rebuild from raw cc[] inputs (that produces unformatted concatenations like
    // "Percentage of the Total Purchase Price + 6"). The server rows use CompensationFormatter
    // for canonical formatting. Since counter submission triggers a full page reload to the
    // submitted view, the initial server state is always correct for review purposes.
    var compBody = document.getElementById('review-comp-body');
    if (compBody && reviewCompRowsData.length > 0) {
        var compHtml = '<table class="comp-table">';
        reviewCompRowsData.forEach(function(row) {
            compHtml += '<tr><td>' + escape(row.label) + '</td><td>' + escape(row.value) + '</td></tr>';
        });
        compHtml += '</table>';
        compBody.innerHTML = compHtml;
    }

    // ── Referral Fee dedicated section (agent viewers only) ───────────────────
    var referralBody = document.getElementById('review-referral-body');
    if (referralBody && viewerIsAgent && reviewReferralRowsData.length > 0) {
        var refHtml = '<table class="comp-table">';
        reviewReferralRowsData.forEach(function(row) {
            refHtml += '<tr><td>' + escape(row.label) + '</td><td>' + escape(row.value) + '</td></tr>';
        });
        refHtml += '</table>';
        referralBody.innerHTML = refHtml;
    }

    // Additional Services Requested
    var addlBody = document.getElementById('review-addl-services-body');
    if (addlBody) {
        var addlTa = document.querySelector('textarea[name="client_requested_services"]');
        var addlVal = addlTa ? addlTa.value.trim() : '';
        if (addlVal) {
            var lines = addlVal.split(/\r?\n/).map(function(l) { return l.trim(); }).filter(function(l) { return l; });
            if (lines.length > 0) {
                var html2 = '<ul class="service-bullet-list">';
                lines.forEach(function(s) { html2 += '<li>' + escape(s) + '</li>'; });
                html2 += '</ul>';
                addlBody.innerHTML = html2;
            } else {
                addlBody.innerHTML = '<p class="text-muted small mb-0"><em>None entered.</em></p>';
            }
        } else {
            addlBody.innerHTML = '<p class="text-muted small mb-0"><em>None entered.</em></p>';
        }
    }

    // Additional Terms / Notes
    var notesBody = document.getElementById('review-notes-body');
    if (notesBody) {
        var notesTa = document.querySelector('textarea[name="additional_terms"]');
        var notesVal = notesTa ? notesTa.value.trim() : '';
        if (notesVal) {
            notesBody.innerHTML = '<div style="font-size:.9rem;color:#1a1a1a;line-height:1.65;white-space:pre-line;">' + escape(notesVal) + '</div>';
        } else {
            notesBody.innerHTML = '<p class="text-muted small mb-0"><em>None entered.</em></p>';
        }
    }

    // Contact Details
    var contactBody = document.getElementById('review-contact-body');
    if (contactBody) {
        var rows = '';

        // Name: prefer separate first/last, fall back to legacy client_name
        var fnEl = document.querySelector('[name="client_first_name"]');
        var lnEl = document.querySelector('[name="client_last_name"]');
        var cnEl = document.querySelector('[name="client_name"]');
        var fnVal = fnEl ? fnEl.value.trim() : '';
        var lnVal = lnEl ? lnEl.value.trim() : '';
        var cnVal = cnEl ? cnEl.value.trim() : '';
        if (fnVal || lnVal) {
            if (fnVal) rows += '<div><dt>' + escape('First Name') + '</dt><dd>' + escape(fnVal) + '</dd></div>';
            if (lnVal) rows += '<div><dt>' + escape('Last Name') + '</dt><dd>' + escape(lnVal) + '</dd></div>';
        } else if (cnVal) {
            rows += '<div><dt>' + escape('Name') + '</dt><dd>' + escape(cnVal) + '</dd></div>';
        }

        var fields = [
            { label: 'Phone', name: 'client_phone' },
            { label: 'Email', name: 'client_email' },
            { label: 'Property Address', name: 'client_property_address' },
            { label: 'City', name: 'client_property_city' },
            { label: 'State', name: 'client_property_state' },
            { label: 'ZIP', name: 'client_property_zip' },
            { label: 'Areas of Interest', name: 'areas_of_interest' },
            { label: 'Desired Sale Price', name: 'desired_sale_price' },
            { label: 'Timeline to Sell', name: 'timeline_to_sell' },
            { label: 'Motivation Level', name: 'motivation_level' },
            { label: 'Target Purchase Price', name: 'target_purchase_price' },
            { label: 'Timeline to Purchase', name: 'timeline_to_purchase' },
            { label: 'Financing Status', name: 'financing_status' },
            { label: 'Estimated Down Payment', name: 'estimated_down_payment' },
            { label: 'Desired Monthly Rent', name: 'desired_monthly_rent' },
            { label: 'Availability Date', name: 'availability_date' },
            { label: 'Occupancy Status', name: 'occupancy_status' },
            { label: 'Desired Lease Term', name: 'desired_lease_term' },
            { label: 'Max Monthly Lease Price', name: 'max_monthly_lease_price' },
            { label: 'Desired Lease Length', name: 'desired_lease_length' },
            { label: 'Move-In Date', name: 'move_in_date' },
            { label: 'Number of Occupants', name: 'number_of_occupants' },
            { label: 'Household Monthly Income', name: 'household_monthly_income' },
            { label: 'Preferred Communication Method', name: 'preferred_comm_method' },
            { label: 'Top Priority', name: 'top_priority' },
        ];
        fields.forEach(function(f) {
            var el = document.querySelector('[name="' + f.name + '"]');
            var val = el ? el.value.trim() : '';
            if (val) {
                var displayVal = val;
                if (f.name === 'desired_sale_price') {
                    var dspRaw = val.replace(/,/g, '').replace(/[^\d]/g, '');
                    displayVal = dspRaw ? '$' + parseInt(dspRaw, 10).toLocaleString('en-US') : val;
                } else if (f.name === 'desired_monthly_rent') {
                    var dmrRaw = val.replace(/,/g, '').replace(/[^\d]/g, '');
                    displayVal = dmrRaw ? '$' + parseInt(dmrRaw, 10).toLocaleString('en-US') : val;
                } else if (f.name === 'target_purchase_price') {
                    displayVal = '$' + val;
                } else if (f.name === 'estimated_down_payment') {
                    var dpTypeEl = document.querySelector('[name="down_payment_type"]');
                    var dpMode = dpTypeEl ? dpTypeEl.value : 'percent';
                    displayVal = (dpMode === 'dollar') ? '$' + val : val + '%';
                } else if (f.name === 'max_monthly_lease_price' || f.name === 'household_monthly_income') {
                    displayVal = '$' + String(val).replace(/^\$/, '');
                } else if (f.name === 'move_in_date' || f.name === 'availability_date') {
                    var parts = val.split('-');
                    if (parts.length === 3) {
                        var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                        displayVal = d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                    }
                } else if (f.name === 'desired_lease_term' && val === 'Other') {
                    var dltOtherEl = document.querySelector('[name="desired_lease_term_other"]');
                    var dltOtherVal = dltOtherEl ? dltOtherEl.value.trim() : '';
                    if (dltOtherVal) {
                        displayVal = dltOtherVal;
                    }
                } else if (f.name === 'desired_lease_length' && val === 'Other') {
                    var dllOtherEl = document.querySelector('[name="desired_lease_length_other"]');
                    var dllOtherVal = dllOtherEl ? dllOtherEl.value.trim() : '';
                    if (dllOtherVal) {
                        displayVal = dllOtherVal;
                    }
                } else if (f.name === 'financing_status' && val === 'Other') {
                    var fsOtherEl = document.querySelector('[name="financing_status_other"]');
                    var fsOtherVal = fsOtherEl ? fsOtherEl.value.trim() : '';
                    if (fsOtherVal) {
                        displayVal = fsOtherVal;
                    }
                } else if (f.name === 'preferred_comm_method' && val === 'Other') {
                    var pcmOtherEl = document.querySelector('[name="preferred_comm_method_other"]');
                    var pcmOtherVal = pcmOtherEl ? pcmOtherEl.value.trim() : '';
                    if (pcmOtherVal) {
                        displayVal = pcmOtherVal;
                    }
                } else if (f.name === 'top_priority' && val === 'Other') {
                    var tpOtherEl = document.querySelector('[name="top_priority_other"]');
                    var tpOtherVal = tpOtherEl ? tpOtherEl.value.trim() : '';
                    if (tpOtherVal) {
                        displayVal = tpOtherVal;
                    }
                }
                rows += '<div><dt>' + escape(f.label) + '</dt><dd>' + escape(displayVal) + '</dd></div>';
            }
        });
        if (rows) {
            contactBody.innerHTML = '<dl class="detail-grid">' + rows + '</dl>';
        } else {
            contactBody.innerHTML = '<p class="text-muted small mb-0"><em>No details entered yet.</em></p>';
        }
    }
}

/**
 * Show/hide cc-conditional sub-fields based on select value.
 * @param {HTMLSelectElement} selectEl  The triggering select
 * @param {string}            parentId  The data-cc-parent value to match against
 */
function ccTrigger(selectEl, parentId) {
    // Normalize curly/smart apostrophes (U+2018, U+2019) to straight (U+0027) so that
    // data-cc-values can use plain straight apostrophes even when the browser option
    // value came from a config string that uses the curly Unicode variant.
    var normalize = function(s) { return s.replace(/[\u2018\u2019]/g, "'"); };
    var val = normalize(selectEl.value);
    var conditionals = document.querySelectorAll('[data-cc-parent="' + parentId + '"]');
    conditionals.forEach(function(el) {
        var allowed = (el.getAttribute('data-cc-values') || '').split('|').map(normalize);
        if (allowed.indexOf(val) !== -1) {
            el.style.display = 'block';
        } else {
            el.style.display = 'none';
        }
    });
}

// Initialise all cc-conditional visibility on page load.
// ccTrigger() normalises curly/smart apostrophes (U+2018/U+2019 → U+0027) on
// both the select value and data-cc-values before comparing, so option strings
// sourced from PHP config (which may contain curly apostrophes) always match.
document.addEventListener('DOMContentLoaded', function() {
    // Find every element with data-cc-parent and trigger visibility based on current select value
    var triggers = {};
    document.querySelectorAll('[data-cc-parent]').forEach(function(el) {
        var parentId = el.getAttribute('data-cc-parent');
        triggers[parentId] = true;
    });

    Object.keys(triggers).forEach(function(parentId) {
        // Find the select that drives this parentId — it's the one with onchange calling ccTrigger(this,'parentId')
        // We find all selects whose onchange attribute references this parentId
        var selects = document.querySelectorAll('select[onchange*="' + parentId + '"]');
        selects.forEach(function(sel) {
            ccTrigger(sel, parentId);
        });
    });

    // ── Landlord: explicit post-init pass for nested Interested-in-Selling chain ──
    // The generic init above processes triggers in DOM insertion order, which means
    // cc_interested_in_selling (outer) is always initialized before
    // cc_interested_in_selling_type (inner). This explicit re-pass guarantees that
    // even if Object.keys ordering were non-deterministic, the parent is resolved
    // first so the inner sub-fields render correctly when a saved value is present.
    var iisOuter = document.querySelector('select[onchange*="cc_interested_in_selling"]:not([onchange*="cc_interested_in_selling_type"])');
    if (iisOuter) {
        ccTrigger(iisOuter, 'cc_interested_in_selling');
    }
    var iisInner = document.querySelector('select[onchange*="cc_interested_in_selling_type"]');
    if (iisInner) {
        ccTrigger(iisInner, 'cc_interested_in_selling_type');
    }
});

// ── Comp Terms: comma formatting for all $-prefixed flat-fee text inputs ──────
// Selector rule: input[type="text"] inside .input-group where a sibling
// span.input-group-text has textContent === "$" exactly.
// Percentage (%), calendar-day (#), and protection period inputs are naturally
// excluded because their sibling spans contain different symbols.
document.addEventListener('DOMContentLoaded', function() {
    function ccFormatWithCommas(val) {
        var raw = val.replace(/[^0-9.]/g, '');
        var firstDot = raw.indexOf('.');
        if (firstDot !== -1) {
            raw = raw.substring(0, firstDot + 1) + raw.substring(firstDot + 1).replace(/\./g, '');
        }
        var parts = raw.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return parts.length > 1 ? parts[0] + '.' + parts[1] : parts[0];
    }

    function isCcCurrencyInput(inp) {
        if (inp.type !== 'text') return false;
        var grp = inp.closest('.input-group');
        if (!grp) return false;
        return Array.from(grp.querySelectorAll(':scope > .input-group-text')).some(function(span) {
            return span.textContent.trim() === '$';
        });
    }

    document.querySelectorAll('#tab-comp input[type="text"]').forEach(function(inp) {
        if (!isCcCurrencyInput(inp)) return;
        // Format any pre-populated value on page load
        if (inp.value !== '') {
            inp.value = ccFormatWithCommas(inp.value);
        }
        // Format while typing, preserving cursor position
        inp.addEventListener('input', function() {
            var pos = this.selectionStart;
            var oldLen = this.value.length;
            this.value = ccFormatWithCommas(this.value);
            var newLen = this.value.length;
            this.selectionStart = this.selectionEnd = Math.max(0, pos + (newLen - oldLen));
        });
    });
});
</script>
@endsection
