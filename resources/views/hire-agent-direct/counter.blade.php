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
</style>
@endpush

@section('content')
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
        Adjust the services and comp terms below, add any notes in the Additional Terms tab, then submit.
        No listing or bid will be created until both parties agree on terms.
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

                {{-- Additional Requested Services --}}
                <div class="additional-requested-wrap">
                    <div class="counter-section-label">Additionally Requested Services</div>
                    <p class="text-muted small mb-2">
                        List any services not offered above that you'd like to request from the agent.
                        Enter one service per line.
                    </p>
                    @php
                        $existingClientServices = $pending['client_requested_services'] ?? [];
                        $existingClientServicesText = is_array($existingClientServices)
                            ? implode("\n", $existingClientServices)
                            : '';
                    @endphp
                    <textarea name="client_requested_services"
                              rows="4"
                              class="form-control @error('client_requested_services') is-invalid @enderror"
                              maxlength="3000"
                              placeholder="e.g. Virtual staging photography&#10;Professional floor plan&#10;Weekly market updates">{{ old('client_requested_services', $existingClientServicesText) }}</textarea>
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
                                <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[purchase_fee_flat]" class="form-control" value="{{ $ccv('purchase_fee_flat') }}" placeholder="Flat fee amount"></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="Percentage of the Total Purchase Price">
                                <div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_percentage]" class="form-control" value="{{ $ccv('purchase_fee_percentage') }}" placeholder="Percentage (e.g. 3)"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="Percentage of the Total Purchase Price + Flat Fee">
                                <div class="row g-2">
                                    <div class="col-md-6"><div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_percentage_combo]" class="form-control" value="{{ $ccv('purchase_fee_percentage_combo') }}" placeholder="% (e.g. 2)"><span class="input-group-text">%</span></div></div>
                                    <div class="col-md-1 text-center pt-1">+</div>
                                    <div class="col-md-5"><div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[purchase_fee_flat_combo]" class="form-control" value="{{ $ccv('purchase_fee_flat_combo') }}" placeholder="Flat fee"></div></div>
                                </div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="other">
                                <input type="text" name="cc[purchase_fee_other]" class="form-control form-control-sm" value="{{ $ccv('purchase_fee_other') }}" placeholder="Describe fee structure">
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
                                    <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[lease_fee_flat]" class="form-control" value="{{ $ccv('lease_fee_flat') }}" placeholder="Flat fee"></div>
                                </div>
                                @if($propertyType === 'residential')
                                <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="Percentage of Monthly Rent">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[lease_fee_percentage_monthly_rent]" class="form-control" value="{{ $ccv('lease_fee_percentage_monthly_rent') }}" placeholder="% monthly rent"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="Percentage of the Gross Lease Value">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[lease_fee_percentage]" class="form-control" value="{{ $ccv('lease_fee_percentage') }}" placeholder="% gross lease value"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="Flat Fee + Percentage of the Gross Lease Value">
                                    <div class="row g-2">
                                        <div class="col-md-5"><div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[lease_fee_flat_combo]" class="form-control" value="{{ $ccv('lease_fee_flat_combo') }}" placeholder="Flat fee"></div></div>
                                        <div class="col-md-1 text-center pt-1">+</div>
                                        <div class="col-md-6"><div class="input-group input-group-sm"><input type="number" name="cc[lease_fee_percentage_combo]" class="form-control" value="{{ $ccv('lease_fee_percentage_combo') }}" placeholder="% gross lease"><span class="input-group-text">%</span></div></div>
                                    </div>
                                </div>
                                @else
                                <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="Percentage of the Net Aggregate Rent">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[lease_fee_percentage_net]" class="form-control" value="{{ $ccv('lease_fee_percentage_net') }}" placeholder="% net aggregate rent"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="Flat Fee + Percentage of the Net Aggregate Rent">
                                    <div class="row g-2">
                                        <div class="col-md-5"><div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[lease_fee_flat_combo_net]" class="form-control" value="{{ $ccv('lease_fee_flat_combo_net') }}" placeholder="Flat fee"></div></div>
                                        <div class="col-md-1 text-center pt-1">+</div>
                                        <div class="col-md-6"><div class="input-group input-group-sm"><input type="number" name="cc[lease_fee_percentage_combo_net]" class="form-control" value="{{ $ccv('lease_fee_percentage_combo_net') }}" placeholder="% net aggregate"><span class="input-group-text">%</span></div></div>
                                    </div>
                                </div>
                                @endif
                                <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="other">
                                    <input type="text" name="cc[lease_fee_other]" class="form-control form-control-sm" value="{{ $ccv('lease_fee_other') }}" placeholder="Describe lease fee">
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
                                <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[purchase_fee_flat]" class="form-control" value="{{ $ccv('purchase_fee_flat') }}" placeholder="Flat fee amount"></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="percentage">
                                <div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_percentage]" class="form-control" value="{{ $ccv('purchase_fee_percentage') }}" placeholder="Percentage (e.g. 6)"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="combo">
                                <div class="row g-2">
                                    <div class="col-md-6"><div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_percentage_combo]" class="form-control" value="{{ $ccv('purchase_fee_percentage_combo') }}" placeholder="% (e.g. 2)"><span class="input-group-text">%</span></div></div>
                                    <div class="col-md-1 text-center pt-1">+</div>
                                    <div class="col-md-5"><div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[purchase_fee_flat_combo]" class="form-control" value="{{ $ccv('purchase_fee_flat_combo') }}" placeholder="Flat fee"></div></div>
                                </div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="other">
                                <input type="text" name="cc[purchase_fee_other]" class="form-control form-control-sm" value="{{ $ccv('purchase_fee_other') }}" placeholder="Describe fee structure">
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
                                    <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[commission_structure_type_fee_flat]" class="form-control" value="{{ $ccv('commission_structure_type_fee_flat') }}" placeholder="Flat fee amount"></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_commission_structure_type" data-cc-values="Percentage of the Total Purchase Price">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[commission_structure_type_fee_percentage]" class="form-control" value="{{ $ccv('commission_structure_type_fee_percentage') }}" placeholder="% of purchase price"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_commission_structure_type" data-cc-values="other">
                                    <input type="text" name="cc[commission_structure_type_fee_other]" class="form-control form-control-sm" value="{{ $ccv('commission_structure_type_fee_other') }}" placeholder="Describe fee structure">
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
                                    <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[seller_leasing_gross_purchase_fee_flat_amount]" class="form-control" value="{{ $ccv('seller_leasing_gross_purchase_fee_flat_amount') }}" placeholder="Flat fee amount"></div>
                                </div>
                                @if($isSellerLeasingResidential)
                                {{-- Residential / Income / Vacant Land sub-fields --}}
                                <div class="cc-conditional" data-cc-parent="cc_seller_leasing_fee_type" data-cc-values="Percentage of the Gross Lease Value">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[seller_leasing_gross]" class="form-control" value="{{ $ccv('seller_leasing_gross') }}" placeholder="% gross lease value"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_seller_leasing_fee_type" data-cc-values="Percentage of the Rent Due Each Rental Period">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[seller_leasing_gross_rental]" class="form-control" value="{{ $ccv('seller_leasing_gross_rental') }}" placeholder="% rent per period"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_seller_leasing_fee_type" data-cc-values="Percentage of the First Month's Rent">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[seller_leasing_gross_month_rent]" class="form-control" value="{{ $ccv('seller_leasing_gross_month_rent') }}" placeholder="% first month's rent"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_seller_leasing_fee_type" data-cc-values="other">
                                    <input type="text" name="cc[seller_leasing_gross_purchase_fee_other]" class="form-control form-control-sm" value="{{ $ccv('seller_leasing_gross_purchase_fee_other') }}" placeholder="Describe leasing fee">
                                </div>
                                @else
                                {{-- Commercial / Business sub-fields --}}
                                <div class="cc-conditional" data-cc-parent="cc_seller_leasing_fee_type" data-cc-values="Percentage of Net Aggregate Rent">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[seller_leasing_gross_other]" class="form-control" value="{{ $ccv('seller_leasing_gross_other') }}" placeholder="% net aggregate rent"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_seller_leasing_fee_type" data-cc-values="Percentage of Gross Rent">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[seller_leasing_gross_percentage]" class="form-control" value="{{ $ccv('seller_leasing_gross_percentage') }}" placeholder="% gross rent"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_seller_leasing_fee_type" data-cc-values="Percentage of Month's Rent">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[seller_leasing_gross_month_rent]" class="form-control" value="{{ $ccv('seller_leasing_gross_month_rent') }}" placeholder="% month's rent"><span class="input-group-text">%</span></div>
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
                                <div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_rental_period]" class="form-control" value="{{ $ccv('purchase_fee_rental_period') }}" placeholder="% rent per period"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="Percentage of the Gross Lease Value">
                                <div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_percentage_combo]" class="form-control" value="{{ $ccv('purchase_fee_percentage_combo') }}" placeholder="% gross lease value"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="Percentage of the First Month's Rent">
                                <div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_flat_combo]" class="form-control" value="{{ $ccv('purchase_fee_flat_combo') }}" placeholder="% first month's rent"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="Flat Fee">
                                <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[purchase_fee_flat]" class="form-control" value="{{ $ccv('purchase_fee_flat') }}" placeholder="Flat fee amount"></div>
                            </div>
                            @else
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="Percentage of the Net Aggregate Rent">
                                <div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_net_aggregate]" class="form-control" value="{{ $ccv('purchase_fee_net_aggregate') }}" placeholder="% net aggregate rent"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="Percentage of the Gross Rent">
                                <div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_gross_rent]" class="form-control" value="{{ $ccv('purchase_fee_gross_rent') }}" placeholder="% gross rent"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="Percentage of Month's Rent">
                                <div class="row g-1">
                                    <div class="col-8"><div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_monthly_percentage]" class="form-control" value="{{ $ccv('purchase_fee_monthly_percentage') }}" placeholder="% month's rent"><span class="input-group-text">%</span></div></div>
                                    <div class="col-4"><div class="input-group input-group-sm"><span class="input-group-text">#</span><input type="number" name="cc[purchase_fee_months]" class="form-control" value="{{ $ccv('purchase_fee_months') }}" placeholder="months"></div></div>
                                </div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="Flat Fee">
                                <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[purchase_fee_flat_commercial]" class="form-control" value="{{ $ccv('purchase_fee_flat_commercial') }}" placeholder="Flat fee amount"></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="other">
                                <input type="text" name="cc[purchase_fee_other_commercial]" class="form-control form-control-sm" value="{{ $ccv('purchase_fee_other_commercial') }}" placeholder="Describe fee structure">
                            </div>
                            @endif
                            @if($propertyType === 'residential')
                            <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type" data-cc-values="other">
                                <input type="text" name="cc[purchase_fee_other]" class="form-control form-control-sm" value="{{ $ccv('purchase_fee_other') }}" placeholder="Describe fee structure">
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
                                <div class="input-group input-group-sm"><input type="number" name="cc[renewal_fee_percentage]" class="form-control" value="{{ $ccv('renewal_fee_percentage') }}" placeholder="% rent per period"><span class="input-group-text">%</span></div>
                            </div>
                            {{-- Residential: Percentage of the Gross Lease Value --}}
                            <div class="cc-conditional" data-cc-parent="cc_renewal_fee_type" data-cc-values="Percentage of the Gross Lease Value">
                                <div class="input-group input-group-sm"><input type="number" name="cc[renewal_fee_lease_value]" class="form-control" value="{{ $ccv('renewal_fee_lease_value') }}" placeholder="% gross lease value"><span class="input-group-text">%</span></div>
                            </div>
                            {{-- Residential: Percentage of the First Month's Rent --}}
                            <div class="cc-conditional" data-cc-parent="cc_renewal_fee_type" data-cc-values="Percentage of the First Month's Rent">
                                <div class="input-group input-group-sm"><input type="number" name="cc[renewal_fee_first_month]" class="form-control" value="{{ $ccv('renewal_fee_first_month') }}" placeholder="% first month's rent"><span class="input-group-text">%</span></div>
                            </div>
                            @else
                            {{-- Commercial: Percentage of the Net Aggregate Rent --}}
                            <div class="cc-conditional" data-cc-parent="cc_renewal_fee_type" data-cc-values="Percentage of the Net Aggregate Rent">
                                <div class="input-group input-group-sm"><input type="number" name="cc[renewal_fee_percentage]" class="form-control" value="{{ $ccv('renewal_fee_percentage') }}" placeholder="% net aggregate rent"><span class="input-group-text">%</span></div>
                            </div>
                            {{-- Commercial: Percentage of the Gross Rent --}}
                            <div class="cc-conditional" data-cc-parent="cc_renewal_fee_type" data-cc-values="Percentage of the Gross Rent">
                                <div class="input-group input-group-sm"><input type="number" name="cc[renewal_fee_lease_value]" class="form-control" value="{{ $ccv('renewal_fee_lease_value') }}" placeholder="% gross rent"><span class="input-group-text">%</span></div>
                            </div>
                            {{-- Commercial: Percentage of Month's Rent --}}
                            <div class="cc-conditional" data-cc-parent="cc_renewal_fee_type" data-cc-values="Percentage of Month's Rent">
                                <div class="input-group input-group-sm"><input type="number" name="cc[renewal_fee_first_month]" class="form-control" value="{{ $ccv('renewal_fee_first_month') }}" placeholder="% month's rent"><span class="input-group-text">%</span></div>
                            </div>
                            @endif
                            {{-- Flat Fee (both property type groups) --}}
                            <div class="cc-conditional" data-cc-parent="cc_renewal_fee_type" data-cc-values="Flat Fee">
                                <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[renewal_fee_flat_fee]" class="form-control" value="{{ $ccv('renewal_fee_flat_fee') }}" placeholder="Flat fee amount"></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_renewal_fee_type" data-cc-values="other">
                                <input type="text" name="cc[renewal_fee_custom]" class="form-control form-control-sm" value="{{ $ccv('renewal_fee_custom') }}" placeholder="Describe renewal fee">
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
                                    <div class="input-group input-group-sm"><input type="number" name="cc[tenant_broker_percentage]" class="form-control" value="{{ $ccv('tenant_broker_percentage') }}" placeholder="% rent per period"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_tenant_broker_fee_structure" data-cc-values="Percentage of the Gross Lease Value">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[tenant_broker_gross_lease]" class="form-control" value="{{ $ccv('tenant_broker_gross_lease') }}" placeholder="% gross lease value"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_tenant_broker_fee_structure" data-cc-values="Percentage of the First Month's Rent">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[tenant_broker_first_month_rent]" class="form-control" value="{{ $ccv('tenant_broker_first_month_rent') }}" placeholder="% first month's rent"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_tenant_broker_fee_structure" data-cc-values="Flat fee|Flat Fee">
                                    <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[tenant_broker_flat_fee]" class="form-control" value="{{ $ccv('tenant_broker_flat_fee') }}" placeholder="Flat fee amount"></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_tenant_broker_fee_structure" data-cc-values="Other">
                                    <input type="text" name="cc[tenant_broker_other]" class="form-control form-control-sm" value="{{ $ccv('tenant_broker_other') }}" placeholder="Describe fee">
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
                                    <div class="input-group input-group-sm"><input type="number" name="cc[landlord_broker_purchase_price]" class="form-control" value="{{ $ccv('landlord_broker_purchase_price') }}" placeholder="% of purchase price"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_interested_in_selling_type" data-cc-values="Percentage of the Total Purchase Price + Flat Fee">
                                    <div class="row g-2">
                                        <div class="col-md-6"><div class="input-group input-group-sm"><input type="number" name="cc[landlord_broker_percentage_price]" class="form-control" value="{{ $ccv('landlord_broker_percentage_price') }}" placeholder="% (e.g. 2)"><span class="input-group-text">%</span></div></div>
                                        <div class="col-md-1 text-center pt-1">+</div>
                                        <div class="col-md-5"><div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[landlord_broker_dollar_price]" class="form-control" value="{{ $ccv('landlord_broker_dollar_price') }}" placeholder="Flat fee"></div></div>
                                    </div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_interested_in_selling_type" data-cc-values="Flat Fee">
                                    <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[landlord_broker_flate_fee]" class="form-control" value="{{ $ccv('landlord_broker_flate_fee') }}" placeholder="Flat fee amount"></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_interested_in_selling_type" data-cc-values="Other">
                                    <input type="text" name="cc[landlord_broker_other]" class="form-control form-control-sm" value="{{ $ccv('landlord_broker_other') }}" placeholder="Describe selling fee">
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
                                <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[lease_fee_flat]" class="form-control" value="{{ $ccv('lease_fee_flat') }}" placeholder="Flat fee amount"></div>
                            </div>
                            @if($propertyType === 'residential')
                            <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="Percentage of Monthly Rent">
                                <div class="input-group input-group-sm"><input type="number" name="cc[lease_fee_percentage_monthly_rent]" class="form-control" value="{{ $ccv('lease_fee_percentage_monthly_rent') }}" placeholder="% monthly rent"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="Percentage of the Gross Lease Value">
                                <div class="input-group input-group-sm"><input type="number" name="cc[lease_fee_percentage]" class="form-control" value="{{ $ccv('lease_fee_percentage') }}" placeholder="% gross lease value"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="Flat Fee + Percentage of the Gross Lease Value">
                                <div class="row g-2">
                                    <div class="col-md-5"><div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[lease_fee_flat_combo]" class="form-control" value="{{ $ccv('lease_fee_flat_combo') }}" placeholder="Flat fee"></div></div>
                                    <div class="col-md-1 text-center pt-1">+</div>
                                    <div class="col-md-6"><div class="input-group input-group-sm"><input type="number" name="cc[lease_fee_percentage_combo]" class="form-control" value="{{ $ccv('lease_fee_percentage_combo') }}" placeholder="% gross lease"><span class="input-group-text">%</span></div></div>
                                </div>
                            </div>
                            @elseif($propertyType === 'commercial')
                            <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="Percentage of the Net Aggregate Rent">
                                <div class="input-group input-group-sm"><input type="number" name="cc[lease_fee_percentage_net]" class="form-control" value="{{ $ccv('lease_fee_percentage_net') }}" placeholder="% net aggregate rent"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="Flat Fee + Percentage of the Net Aggregate Rent">
                                <div class="row g-2">
                                    <div class="col-md-5"><div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[lease_fee_flat_combo_net]" class="form-control" value="{{ $ccv('lease_fee_flat_combo_net') }}" placeholder="Flat fee"></div></div>
                                    <div class="col-md-1 text-center pt-1">+</div>
                                    <div class="col-md-6"><div class="input-group input-group-sm"><input type="number" name="cc[lease_fee_percentage_combo_net]" class="form-control" value="{{ $ccv('lease_fee_percentage_combo_net') }}" placeholder="% net aggregate"><span class="input-group-text">%</span></div></div>
                                </div>
                            </div>
                            @endif
                            <div class="cc-conditional" data-cc-parent="cc_lease_fee_type" data-cc-values="other">
                                <input type="text" name="cc[lease_fee_other]" class="form-control form-control-sm" value="{{ $ccv('lease_fee_other') }}" placeholder="Describe lease fee">
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
                                    <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[purchase_fee_flat]" class="form-control" value="{{ $ccv('purchase_fee_flat') }}" placeholder="Flat fee amount"></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type_tenant" data-cc-values="Percentage of the Total Purchase Price">
                                    <div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_percentage]" class="form-control" value="{{ $ccv('purchase_fee_percentage') }}" placeholder="% of purchase price"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type_tenant" data-cc-values="Percentage of the Total Purchase Price + Flat Fee">
                                    <div class="row g-2">
                                        <div class="col-md-6"><div class="input-group input-group-sm"><input type="number" name="cc[purchase_fee_percentage_combo]" class="form-control" value="{{ $ccv('purchase_fee_percentage_combo') }}" placeholder="% (e.g. 3)"><span class="input-group-text">%</span></div></div>
                                        <div class="col-md-1 text-center pt-1">+</div>
                                        <div class="col-md-5"><div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[purchase_fee_flat_combo]" class="form-control" value="{{ $ccv('purchase_fee_flat_combo') }}" placeholder="Flat fee"></div></div>
                                    </div>
                                </div>
                                <div class="cc-conditional" data-cc-parent="cc_purchase_fee_type_tenant" data-cc-values="other">
                                    <input type="text" name="cc[purchase_fee_other]" class="form-control form-control-sm" value="{{ $ccv('purchase_fee_other') }}" placeholder="Describe purchase fee">
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
                    <label class="cc-label">Agreement Timeframe</label>
                    <select name="cc[agency_agreement_timeframe]" class="form-control form-control-sm"
                            onchange="ccTrigger(this, 'cc_agency_agreement_timeframe')">
                        <option value="">Select</option>
                        @foreach(config('agent_preset_compensation.common.agency_agreement_timeframe', []) as $optVal => $optLabel)
                            <option value="{{ $optVal }}" {{ $ccv('agency_agreement_timeframe') === $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                        @endforeach
                        {{-- Custom/Other option excluded from config (stored value differs by role) --}}
                        <option value="{{ $aatCustomVal }}" {{ $ccv('agency_agreement_timeframe') === $aatCustomVal ? 'selected' : '' }}>{{ $role === 'buyer' ? 'Other (Custom)' : 'Other' }}</option>
                    </select>
                    <div class="cc-sub mt-2">
                        <div class="cc-conditional" data-cc-parent="cc_agency_agreement_timeframe" data-cc-values="custom|Other">
                            <input type="text" name="cc[agency_agreement_custom]" class="form-control form-control-sm" value="{{ $ccv('agency_agreement_custom') }}" placeholder="Specify timeframe (e.g. 45 days)">
                        </div>
                    </div>
                </div>

                {{-- Protection Period --}}
                <div class="cc-group">
                    <label class="cc-label">Protection Period (Days)</label>
                    <input type="number" name="cc[protection_period]" class="form-control form-control-sm"
                           value="{{ $ccv('protection_period') }}" placeholder="e.g. 90">
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
                            <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="text" name="cc[early_termination_fee_amount]" class="form-control" value="{{ $ccv('early_termination_fee_amount') }}" placeholder="Amount (e.g. 2,000)"></div>
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
                            <div class="input-group input-group-sm mb-2"><span class="input-group-text">$</span><input type="text" name="cc[retainer_fee_amount]" class="form-control" value="{{ $ccv('retainer_fee_amount') }}" placeholder="Amount (e.g. 500)"></div>
                            <select name="cc[retainer_fee_application]" class="form-control form-control-sm">
                                <option value="">Select application</option>
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
                              placeholder="Any additional compensation terms or notes...">{{ $ccv('additional_details_broker') }}</textarea>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="tab-nav-btn" onclick="switchTab('tab-services-btn')">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back: Services
                    </button>
                    <button type="button" class="tab-nav-btn" onclick="switchTab('tab-notes-btn')">
                        Next: Additional Terms <i class="fa-solid fa-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>

            {{-- Tab 3: Additional Terms --}}
            <div class="tab-pane fade counter-tab-pane" id="tab-notes" role="tabpanel">
                <p class="text-muted small mb-3">
                    Use this space to add any supplemental notes, clarifications, or conditions not covered above.
                </p>
                <div class="mb-3">
                    <label class="fw-bold form-label">Your Additional Terms / Notes <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea name="additional_terms" rows="7"
                              class="form-control @error('additional_terms') is-invalid @enderror"
                              maxlength="3000"
                              placeholder="e.g. I'd prefer to start with a 30-day trial period, or I have a few questions about the referral clause..."
                              >{{ old('additional_terms', $pending['additional_terms'] ?? '') }}</textarea>
                    @error('additional_terms')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="text-muted small mt-1">Maximum 3,000 characters.</div>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="tab-nav-btn" onclick="switchTab('tab-comp-btn')">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back: Comp Terms
                    </button>
                    <button type="button" class="tab-nav-btn" onclick="switchTab('tab-client-btn')">
                        Next: Your Details <i class="fa-solid fa-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>

            {{-- Tab 4: Client Details --}}
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
                </div>
            </div>

        </div>

        {{-- Submit footer --}}
        <div class="d-flex align-items-center gap-3 flex-wrap mb-4 mt-2">
            <button type="submit" id="counter-submit-btn" class="submit-btn btn">
                <i class="fa-solid fa-arrow-right-arrow-left me-2"></i>Submit Counter Request
            </button>
            <a href="{{ route('hire.agent.direct.preview', ['agentId' => $agent->id, 'role' => $role, 'propertyType' => $propertyType]) }}"
               class="btn btn-outline-secondary">
                ← Back to Review
            </a>
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
    if (!btn || btn.disabled) return false;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Sending\u2026';
    return true;
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
});
</script>
@endsection
