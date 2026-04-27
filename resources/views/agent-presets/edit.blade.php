@extends('layouts.main')

@push('styles')
<style>
    .preset-edit-wrap {
        max-width: 860px;
        margin: 0 auto;
    }
    .preset-header {
        background: linear-gradient(135deg, #049399 0%, #036b70 100%);
        color: #fff;
        border-radius: 12px;
        padding: 1.4rem 1.8rem;
        margin-bottom: 1.75rem;
    }
    .preset-header h1 {
        font-size: 1.35rem;
        font-weight: 700;
        margin: 0 0 .25rem;
    }
    .preset-header p {
        margin: 0;
        opacity: .85;
        font-size: .88rem;
    }
    .preset-section {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 10px;
        margin-bottom: 1.5rem;
        overflow: hidden;
    }
    .preset-section-header {
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        padding: .85rem 1.25rem;
        font-weight: 700;
        font-size: .95rem;
        display: flex;
        align-items: center;
        gap: .5rem;
        cursor: pointer;
        user-select: none;
    }
    .preset-section-header i.section-icon {
        font-size: 1rem;
        color: #049399;
    }
    .preset-section-header .toggle-icon {
        margin-left: auto;
        transition: transform .2s;
        color: #6c757d;
    }
    .preset-section-header[aria-expanded="false"] .toggle-icon {
        transform: rotate(-90deg);
    }
    /* Custom collapse — sections closed by default hide themselves.
       Sections open by default have no hiding class at all, so they
       are always visible regardless of Bootstrap or Alpine JS. */
    .preset-section-body {
        padding: 1.25rem 1.4rem;
    }
    .preset-section-body.preset-closed {
        display: none;
    }
    .form-label-sm {
        font-size: .83rem;
        font-weight: 600;
        color: #444;
        margin-bottom: .3rem;
    }
    .form-hint {
        font-size: .77rem;
        color: #6c757d;
        margin-top: .2rem;
    }
    /* Services checklist */
    .services-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: .4rem;
    }
    @media (min-width: 640px) {
        .services-grid { grid-template-columns: 1fr 1fr; }
    }
    .service-item {
        background: #f8fafc;
        border: 1px solid #a8bfcf;
        border-radius: 6px;
        padding: .55rem .75rem;
        display: flex;
        align-items: flex-start;
        gap: .55rem;
        font-size: .875rem;
        color: #253041;
        line-height: 1.45;
        cursor: pointer;
        transition: background .12s, border-color .12s;
    }
    .service-item:hover {
        background: #e8f7f7;
        border-color: #049399;
        color: #036b70;
    }
    .service-item input[type="checkbox"] {
        margin-top: .18rem;
        flex-shrink: 0;
        width: 15px;
        height: 15px;
        accent-color: #049399;
        cursor: pointer;
    }
    .service-item.checked {
        background: #e4f5f5;
        border-color: #049399;
        color: #023e40;
        font-weight: 600;
    }
    /* Section error badge */
    .section-error-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.25rem;
        height: 1.25rem;
        background: #dc3545;
        color: #fff;
        border-radius: 50%;
        font-size: .7rem;
        font-weight: 800;
        line-height: 1;
        flex-shrink: 0;
    }
    .preset-section-header.has-errors {
        background: #fff5f5;
        border-bottom-color: #f5c2c7;
    }
    /* Section requirement badges */
    .section-req-badge {
        font-size: .68rem;
        font-weight: 700;
        padding: .1rem .42rem;
        border-radius: 4px;
        text-transform: uppercase;
        letter-spacing: .05em;
        vertical-align: middle;
    }
    .section-req-badge.req {
        background: #fff3cd;
        color: #7d5a00;
        border: 1px solid #ffc107;
    }
    .section-req-badge.rec {
        background: #e8f4fd;
        color: #0c5a9c;
        border: 1px solid #b8d9f5;
    }
    .services-toolbar {
        display: flex;
        gap: .5rem;
        margin-bottom: .75rem;
        flex-wrap: wrap;
    }
    .btn-select-all, .btn-clear-all {
        font-size: .78rem;
        padding: .25rem .65rem;
        border-radius: 5px;
    }
    .selected-count-badge {
        font-size: .78rem;
        padding: .25rem .65rem;
        background: #e8f7f7;
        color: #049399;
        border-radius: 5px;
        border: 1px solid #c0e8e9;
    }
    .sticky-save-bar {
        position: sticky;
        bottom: 0;
        background: #fff;
        border-top: 1px solid #dee2e6;
        padding: 1rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        z-index: 50;
        box-shadow: 0 -2px 8px rgba(0,0,0,.06);
    }
    .btn-save-preset {
        font-size: .95rem;
        padding: .55rem 1.8rem;
        background: #049399;
        border: none;
        color: #fff;
        border-radius: 7px;
        font-weight: 600;
    }
    .btn-save-preset:hover {
        background: #036b70;
        color: #fff;
    }
    .btn-copy-hire-edit {
        font-size: .83rem;
        padding: .25rem .65rem;
        border-radius: 6px;
        color: #049399;
        border-color: #049399;
    }
    .btn-copy-hire-edit:hover {
        background: #e8f7f7;
        color: #036b70;
        border-color: #036b70;
    }
    .btn-copy-hire-edit.copied {
        color: #198754;
        border-color: #198754;
        background: #f0faf4;
    }
</style>
@endpush

@section('content')
<div class="preset-edit-wrap py-4 px-3" x-ignore>

    <div class="preset-header">
        <h1><i class="fa fa-sliders me-2"></i>Edit Preset: {{ $roleLabel }}</h1>
        <p>Property type: <strong>{{ $propertyLabel }}</strong> &nbsp;&middot;&nbsp; Changes save to your default profile and auto-fill future bids.</p>
    </div>

    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <a href="{{ route('agent.presets.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fa fa-arrow-left me-1"></i>Back to All Presets
        </a>
        @if ($profileExists)
            <a href="{{ $hireMeUrl }}" target="_blank" class="btn btn-sm btn-outline-info">
                <i class="fa fa-eye me-1"></i>Open Hire Me Page
            </a>
            <button type="button"
                    class="btn btn-sm btn-outline btn-copy-hire-edit"
                    data-hire-url="{{ $hireMeUrl }}"
                    title="Copy your Hire Me link to share with clients">
                <i class="fa fa-copy me-1"></i>Copy Hire Me Link
            </button>
        @else
            <button type="button" class="btn btn-sm btn-outline-info" disabled title="Save this preset first to preview your Hire Me page">
                <i class="fa fa-eye me-1"></i>Open Hire Me Page
            </button>
        @endif
    </div>

    @if (request()->query('saved'))
        <div class="alert alert-success d-flex align-items-center justify-content-between gap-3 mb-4" role="alert">
            <span><i class="fa fa-check-circle me-1"></i>{{ $roleLabel }} &mdash; {{ $propertyLabel }} preset saved.</span>
            <a href="{{ $hireMeUrl }}" target="_blank" class="btn btn-sm btn-success text-nowrap">
                <i class="fa fa-eye me-1"></i>Preview Hire Me Page
            </a>
        </div>
    @endif

    <div id="preset-error-summary" class="d-none alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert" aria-live="polite">
        <i class="fa fa-exclamation-circle flex-shrink-0"></i>
        <span id="preset-error-summary-text"></span>
    </div>

    <form method="POST" action="{{ route('agent.presets.save', [$role, $propertyType]) }}" id="preset-form">
        @csrf

        {{-- ── SERVICES ──────────────────────────────────────────────────────── --}}
        <div class="preset-section">
            <div class="preset-section-header"
                 data-preset-toggle="section-services"
                 aria-expanded="true"
                 aria-controls="section-services">
                <i class="fa fa-list-ul section-icon"></i>
                Services
                <span class="section-req-badge req">Required</span>
                <i class="fa fa-chevron-down toggle-icon"></i>
            </div>
            {{-- No Bootstrap collapse class — section is visible by default via normal block display --}}
            <div class="preset-section-body" id="section-services">
                <p class="form-hint mb-3">Select the services you include by default in your offer.</p>

                <div class="services-toolbar">
                    <button type="button" class="btn btn-outline-secondary btn-select-all" data-target="services-grid">Select All</button>
                    <button type="button" class="btn btn-outline-secondary btn-clear-all" data-target="services-grid">Clear All</button>
                    <span class="selected-count-badge">
                        <span class="selected-count">{{ count($selectedServices) }}</span> selected
                    </span>
                </div>

                <div class="services-grid" id="services-grid">
                    @forelse ($services as $service)
                        @php $checked = in_array($service, $selectedServices); @endphp
                        <label class="service-item {{ $checked ? 'checked' : '' }}">
                            <input type="checkbox"
                                   name="services[]"
                                   value="{{ $service }}"
                                   {{ $checked ? 'checked' : '' }}>
                            <span>{{ $service }}</span>
                        </label>
                    @empty
                        <p class="text-muted fst-italic">No services available for this combination.</p>
                    @endforelse
                </div>

                {{-- ── ADDITIONAL / CUSTOM SERVICES ────────────────────────────── --}}
                <div class="additional-services-wrap mt-4 pt-3" style="border-top: 1px solid #dee2e6;">
                    <div class="d-flex align-items-center justify-content-between mb-1 flex-wrap gap-2">
                        <span class="form-label-sm mb-0">Additional Services</span>
                        <button type="button" id="add-custom-service-btn"
                                class="btn btn-sm btn-outline-secondary">
                            <i class="fa fa-plus me-1"></i>Add Custom Service
                        </button>
                    </div>
                    <div class="form-hint mb-2">Enter any custom services not listed above — these are included alongside your checked services when pre-filling bids.</div>
                    <div id="custom-services-list">
                        @foreach ($otherServices as $osVal)
                            <div class="custom-service-row d-flex gap-2 mb-2">
                                <input type="text"
                                       name="other_services[]"
                                       class="form-control form-control-sm"
                                       value="{{ $osVal }}"
                                       placeholder="Enter a custom service the Agent is willing to provide"
                                       maxlength="500">
                                <button type="button"
                                        class="btn btn-sm btn-outline-danger custom-service-remove flex-shrink-0"
                                        title="Remove this service">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ── AGENT OVERVIEW ───────────────────────────────────────────────── --}}
        <div class="preset-section">
            <div class="preset-section-header"
                 data-preset-toggle="section-overview"
                 aria-expanded="true"
                 aria-controls="section-overview">
                <i class="fa fa-user section-icon"></i>
                Agent Overview
                <span class="section-req-badge rec">Recommended</span>
                <i class="fa fa-chevron-down toggle-icon"></i>
            </div>
            {{-- No Bootstrap collapse class — section is visible by default via normal block display --}}
            <div class="preset-section-body" id="section-overview">
                <div class="mb-3">
                    <label class="form-label form-label-sm" for="bio">Professional Bio</label>
                    <textarea class="form-control @error('bio') is-invalid @enderror"
                              id="bio" name="bio" rows="4"
                              placeholder="Write a brief professional bio...">{{ old('bio', $data['bio'] ?? '') }}</textarea>
                    @error('bio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-hint">Introduce yourself, your background, and your experience.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label form-label-sm" for="why_hire_you">Why Hire You</label>
                    <textarea class="form-control @error('why_hire_you') is-invalid @enderror"
                              id="why_hire_you" name="why_hire_you" rows="3"
                              placeholder="Why should a client choose you?">{{ old('why_hire_you', $data['why_hire_you'] ?? '') }}</textarea>
                    @error('why_hire_you')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label form-label-sm" for="what_sets_you_apart">What Sets You Apart</label>
                    <textarea class="form-control @error('what_sets_you_apart') is-invalid @enderror"
                              id="what_sets_you_apart" name="what_sets_you_apart" rows="3"
                              placeholder="Your unique strengths, niche, or approach...">{{ old('what_sets_you_apart', $data['what_sets_you_apart'] ?? '') }}</textarea>
                    @error('what_sets_you_apart')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label form-label-sm" for="marketing_plan">Marketing Plan</label>
                    <textarea class="form-control @error('marketing_plan') is-invalid @enderror"
                              id="marketing_plan" name="marketing_plan" rows="3"
                              placeholder="Describe your marketing approach...">{{ old('marketing_plan', $data['marketing_plan'] ?? '') }}</textarea>
                    @error('marketing_plan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label form-label-sm" for="year_licensed">Year Licensed</label>
                    <input type="text"
                           class="form-control @error('year_licensed') is-invalid @enderror"
                           id="year_licensed" name="year_licensed"
                           placeholder="e.g. 2015"
                           value="{{ old('year_licensed', $data['year_licensed'] ?? '') }}">
                    @error('year_licensed')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-0">
                    <label class="form-label form-label-sm" for="additional_details">Additional Details</label>
                    <textarea class="form-control @error('additional_details') is-invalid @enderror"
                              id="additional_details" name="additional_details" rows="3"
                              placeholder="Any other information to include...">{{ old('additional_details', $data['additional_details'] ?? '') }}</textarea>
                    @error('additional_details')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- ── AGENT CREDENTIALS ────────────────────────────────────────────── --}}
        <div class="preset-section">
            <div class="preset-section-header"
                 data-preset-toggle="section-creds"
                 aria-expanded="false"
                 aria-controls="section-creds">
                <i class="fa fa-id-card-o section-icon"></i>
                Agent Credentials
                <i class="fa fa-chevron-down toggle-icon"></i>
            </div>
            <div class="preset-section-body preset-closed" id="section-creds">
                <p class="form-hint mb-3">These fields pre-fill your contact and license information in bid forms.</p>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label form-label-sm" for="first_name">First Name</label>
                        <input type="text" class="form-control @error('first_name') is-invalid @enderror"
                               id="first_name" name="first_name"
                               value="{{ old('first_name', $data['first_name'] ?? '') }}">
                        @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label form-label-sm" for="last_name">Last Name</label>
                        <input type="text" class="form-control @error('last_name') is-invalid @enderror"
                               id="last_name" name="last_name"
                               value="{{ old('last_name', $data['last_name'] ?? '') }}">
                        @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label form-label-sm" for="phone">Phone</label>
                        <input type="text" class="form-control @error('phone') is-invalid @enderror"
                               id="phone" name="phone"
                               value="{{ old('phone', $data['phone'] ?? '') }}">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label form-label-sm" for="email">Email</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror"
                               id="email" name="email"
                               value="{{ old('email', $data['email'] ?? '') }}">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label form-label-sm" for="brokerage">Brokerage Name</label>
                        <input type="text" class="form-control @error('brokerage') is-invalid @enderror"
                               id="brokerage" name="brokerage"
                               value="{{ old('brokerage', $data['brokerage'] ?? '') }}">
                        @error('brokerage')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label form-label-sm" for="license_no">License Number</label>
                        <input type="text" class="form-control @error('license_no') is-invalid @enderror"
                               id="license_no" name="license_no"
                               value="{{ old('license_no', $data['license_no'] ?? '') }}">
                        @error('license_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label form-label-sm" for="nar_id">NAR ID</label>
                        <input type="text" class="form-control @error('nar_id') is-invalid @enderror"
                               id="nar_id" name="nar_id"
                               value="{{ old('nar_id', $data['nar_id'] ?? '') }}">
                        @error('nar_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- ── PRESENTATION & LINKS ─────────────────────────────────────────── --}}
        <div class="preset-section">
            <div class="preset-section-header"
                 data-preset-toggle="section-links"
                 aria-expanded="false"
                 aria-controls="section-links">
                <i class="fa fa-link section-icon"></i>
                Presentation &amp; Links
                <i class="fa fa-chevron-down toggle-icon"></i>
            </div>
            <div class="preset-section-body preset-closed" id="section-links">
                <div class="mb-3">
                    <label class="form-label form-label-sm" for="presentation_link">Presentation Link</label>
                    <input type="url" class="form-control @error('presentation_link') is-invalid @enderror"
                           id="presentation_link" name="presentation_link"
                           placeholder="https://"
                           value="{{ old('presentation_link', $data['presentation_link'] ?? '') }}">
                    @error('presentation_link')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-hint">Link to a short intro video (YouTube, Vimeo, etc.).</div>
                </div>
                <div class="mb-3">
                    <label class="form-label form-label-sm" for="business_card_link">Business Card / Headshot Link</label>
                    <input type="url" class="form-control @error('business_card_link') is-invalid @enderror"
                           id="business_card_link" name="business_card_link"
                           placeholder="https://"
                           value="{{ old('business_card_link', $data['business_card_link'] ?? '') }}">
                    @error('business_card_link')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label form-label-sm" for="reviews_links_raw">Review Links</label>
                    <textarea class="form-control @error('reviews_links_raw') is-invalid @enderror"
                              id="reviews_links_raw" name="reviews_links_raw" rows="3"
                              placeholder="One URL per line (Zillow, Google, Realtor.com, etc.)">{{ old('reviews_links_raw', implode("\n", $data['reviews_links'] ?? [])) }}</textarea>
                    @error('reviews_links_raw')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-hint">Enter one link per line.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label form-label-sm" for="website_link_raw">Website Links</label>
                    <textarea class="form-control @error('website_link_raw') is-invalid @enderror"
                              id="website_link_raw" name="website_link_raw" rows="2"
                              placeholder="One URL per line">{{ old('website_link_raw', implode("\n", $data['website_link'] ?? [])) }}</textarea>
                    @error('website_link_raw')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-hint">Enter one link per line.</div>
                </div>
                <div class="mb-0">
                    <label class="form-label form-label-sm" for="social_media_raw">Social Media Links</label>
                    <textarea class="form-control @error('social_media_raw') is-invalid @enderror"
                              id="social_media_raw" name="social_media_raw" rows="2"
                              placeholder="One URL per line (Instagram, LinkedIn, Facebook, etc.)">{{ old('social_media_raw', implode("\n", $data['social_media'] ?? [])) }}</textarea>
                    @error('social_media_raw')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-hint">Enter one link per line.</div>
                </div>
            </div>
        </div>

        {{-- ── BROKER COMPENSATION & AGENCY AGREEMENT TERMS ──────────────────── --}}
        <div class="preset-section">
            <div class="preset-section-header"
                 data-preset-toggle="section-compensation"
                 aria-expanded="false"
                 aria-controls="section-compensation">
                <i class="fa fa-dollar section-icon"></i>
                Broker Compensation &amp; Agency Agreement Terms
                <i class="fa fa-chevron-down toggle-icon"></i>
            </div>
            <div class="preset-section-body preset-closed" id="section-compensation">
                <p class="text-muted mb-4 small">These defaults will pre-fill the compensation fields in bid forms when submitting bids. All fields are optional.</p>

                @if ($role === 'buyer')
                    {{-- Buyer's Broker Commission Structure --}}
                    <div class="mb-4">
                        <label class="fw-bold">Buyer's Broker Commission Structure</label>
                        <div class="input-cover mt-2">
                            <select id="cp_commission_structure" name="commission_structure" class="form-control has-icon"
                                    data-icon="fa-solid fa-file-invoice-dollar" onchange="_cpTrigger(this.id)">
                                <option value="">Select</option>
                                <option value="Buyer Pays Out-of-Pocket" @selected(old('commission_structure', $data['commission_structure'] ?? '') === 'Buyer Pays Out-of-Pocket')>Buyer Pays Out-of-Pocket</option>
                                <option value="Requested From Seller in the Offer" @selected(old('commission_structure', $data['commission_structure'] ?? '') === 'Requested From Seller in the Offer')>Requested From Seller in the Offer</option>
                            </select>
                        </div>
                    </div>

                    {{-- Buyer's Broker Purchase Fee --}}
                    <div class="mb-4">
                        <label class="fw-bold">Buyer's Broker Purchase Fee</label>
                        <div class="input-cover mt-2">
                            <select id="cp_purchase_fee_type" name="purchase_fee_type" class="form-control has-icon"
                                    data-icon="fa-solid fa-file-invoice-dollar" onchange="_cpTrigger(this.id)">
                                <option value="">Select</option>
                                <option value="Flat Fee" @selected(old('purchase_fee_type', $data['purchase_fee_type'] ?? '') === 'Flat Fee')>Flat Fee</option>
                                <option value="Percentage of the Total Purchase Price" @selected(old('purchase_fee_type', $data['purchase_fee_type'] ?? '') === 'Percentage of the Total Purchase Price')>Percentage of the Total Purchase Price</option>
                                <option value="Percentage of the Total Purchase Price + Flat Fee" @selected(old('purchase_fee_type', $data['purchase_fee_type'] ?? '') === 'Percentage of the Total Purchase Price + Flat Fee')>Percentage of the Total Purchase Price + Flat Fee</option>
                                <option value="other" @selected(old('purchase_fee_type', $data['purchase_fee_type'] ?? '') === 'other')>Other</option>
                            </select>
                        </div>
                        <div class="mt-2">
                            <div data-cp-parent="cp_purchase_fee_type" data-cp-values="Flat Fee">
                                <div class="input-group"><span class="input-group-text">$</span>
                                    <input type="text" name="purchase_fee_flat" class="form-control" value="{{ old('purchase_fee_flat', $data['purchase_fee_flat'] ?? '') }}" placeholder="Enter flat fee amount (e.g., 5,000)"></div>
                            </div>
                            <div data-cp-parent="cp_purchase_fee_type" data-cp-values="Percentage of the Total Purchase Price">
                                <div class="input-group">
                                    <input type="number" name="purchase_fee_percentage" class="form-control" value="{{ old('purchase_fee_percentage', $data['purchase_fee_percentage'] ?? '') }}" placeholder="Enter percentage of the total purchase price (e.g., 3)">
                                    <span class="input-group-text">%</span></div>
                            </div>
                            <div data-cp-parent="cp_purchase_fee_type" data-cp-values="Percentage of the Total Purchase Price + Flat Fee">
                                <div class="row g-2">
                                    <div class="col-md-6"><div class="input-group"><input type="number" name="purchase_fee_percentage_combo" class="form-control" value="{{ old('purchase_fee_percentage_combo', $data['purchase_fee_percentage_combo'] ?? '') }}" placeholder="Enter percentage (e.g., 2)"><span class="input-group-text">%</span></div></div>
                                    <div class="col-md-1 text-center pt-2">+</div>
                                    <div class="col-md-5"><div class="input-group"><span class="input-group-text">$</span><input type="text" name="purchase_fee_flat_combo" class="form-control" value="{{ old('purchase_fee_flat_combo', $data['purchase_fee_flat_combo'] ?? '') }}" placeholder="Enter flat fee (e.g., 3,000)"></div></div>
                                </div>
                            </div>
                            <div data-cp-parent="cp_purchase_fee_type" data-cp-values="other">
                                <input type="text" name="purchase_fee_other" class="form-control" value="{{ old('purchase_fee_other', $data['purchase_fee_other'] ?? '') }}" placeholder="Enter purchase fee structure (e.g., 1,000 upfront + 2% at Closing)">
                            </div>
                        </div>
                    </div>

                    {{-- Interested in a Lease Agreement --}}
                    <div class="mb-2">
                        <label class="fw-bold">Interested in a Lease Agreement</label>
                        <div class="input-cover mt-2">
                            <select id="cp_interested_lease_option" name="interested_lease_option" class="form-control has-icon"
                                    data-icon="fa-solid fa-ruler" onchange="_cpTrigger(this.id)">
                                <option value="">Select</option>
                                <option value="Yes" @selected(old('interested_lease_option', $data['interested_lease_option'] ?? '') === 'Yes')>Yes</option>
                                <option value="No" @selected(old('interested_lease_option', $data['interested_lease_option'] ?? '') === 'No')>No</option>
                            </select>
                        </div>
                    </div>
                    <div data-cp-parent="cp_interested_lease_option" data-cp-values="Yes" class="mb-4 ps-3 border-start border-2 border-secondary">
                        <label class="fw-bold mt-2">Buyer's Broker Lease Fee</label>
                        <div class="input-cover mt-2">
                            <select id="cp_lease_fee_type" name="lease_fee_type" class="form-control has-icon"
                                    data-icon="fa-solid fa-file-invoice-dollar" onchange="_cpTrigger(this.id)">
                                <option value="">Select</option>
                                <option value="flat" @selected(old('lease_fee_type', $data['lease_fee_type'] ?? '') === 'flat')>Flat Fee</option>
                                @if ($propertyType === 'residential')
                                    <option value="Percentage of Monthly Rent" @selected(old('lease_fee_type', $data['lease_fee_type'] ?? '') === 'Percentage of Monthly Rent')>Percentage of Monthly Rent</option>
                                    <option value="Percentage of the Gross Lease Value" @selected(old('lease_fee_type', $data['lease_fee_type'] ?? '') === 'Percentage of the Gross Lease Value')>Percentage of the Gross Lease Value</option>
                                    <option value="Flat Fee + Percentage of the Gross Lease Value" @selected(old('lease_fee_type', $data['lease_fee_type'] ?? '') === 'Flat Fee + Percentage of the Gross Lease Value')>Flat Fee + Percentage of the Gross Lease Value</option>
                                @else
                                    <option value="Percentage of the Net Aggregate Rent" @selected(old('lease_fee_type', $data['lease_fee_type'] ?? '') === 'Percentage of the Net Aggregate Rent')>Percentage of the Net Aggregate Rent</option>
                                    <option value="Flat Fee + Percentage of the Net Aggregate Rent" @selected(old('lease_fee_type', $data['lease_fee_type'] ?? '') === 'Flat Fee + Percentage of the Net Aggregate Rent')>Flat Fee + Percentage of the Net Aggregate Rent</option>
                                @endif
                                <option value="other" @selected(old('lease_fee_type', $data['lease_fee_type'] ?? '') === 'other')>Other</option>
                            </select>
                        </div>
                        <div class="mt-2">
                            <div data-cp-parent="cp_lease_fee_type" data-cp-values="flat">
                                <div class="input-group"><span class="input-group-text">$</span><input type="text" name="lease_fee_flat" class="form-control" value="{{ old('lease_fee_flat', $data['lease_fee_flat'] ?? '') }}" placeholder="Enter flat fee amount (e.g., 2,500)"></div>
                            </div>
                            @if ($propertyType === 'residential')
                            <div data-cp-parent="cp_lease_fee_type" data-cp-values="Percentage of Monthly Rent">
                                <div class="input-group"><input type="number" name="lease_fee_percentage_monthly_rent" class="form-control" value="{{ old('lease_fee_percentage_monthly_rent', $data['lease_fee_percentage_monthly_rent'] ?? '') }}" placeholder="Enter percentage of monthly rent (e.g., 100)"><span class="input-group-text">%</span></div>
                            </div>
                            <div data-cp-parent="cp_lease_fee_type" data-cp-values="Percentage of the Gross Lease Value">
                                <div class="input-group"><input type="number" name="lease_fee_percentage" class="form-control" value="{{ old('lease_fee_percentage', $data['lease_fee_percentage'] ?? '') }}" placeholder="Enter percentage of the gross lease value (e.g., 10)"><span class="input-group-text">%</span></div>
                            </div>
                            <div data-cp-parent="cp_lease_fee_type" data-cp-values="Flat Fee + Percentage of the Gross Lease Value">
                                <div class="row g-2"><div class="col-md-5"><div class="input-group"><span class="input-group-text">$</span><input type="text" name="lease_fee_flat_combo" class="form-control" value="{{ old('lease_fee_flat_combo', $data['lease_fee_flat_combo'] ?? '') }}" placeholder="Flat fee (e.g., 1,000)"></div></div><div class="col-md-1 text-center pt-2">+</div><div class="col-md-6"><div class="input-group"><input type="number" name="lease_fee_percentage_combo" class="form-control" value="{{ old('lease_fee_percentage_combo', $data['lease_fee_percentage_combo'] ?? '') }}" placeholder="% gross lease value (e.g., 7)"><span class="input-group-text">%</span></div></div></div>
                            </div>
                            @else
                            <div data-cp-parent="cp_lease_fee_type" data-cp-values="Percentage of the Net Aggregate Rent">
                                <div class="input-group"><input type="number" name="lease_fee_percentage_net" class="form-control" value="{{ old('lease_fee_percentage_net', $data['lease_fee_percentage_net'] ?? '') }}" placeholder="Enter percentage of the net aggregate rent (e.g., 6)"><span class="input-group-text">%</span></div>
                            </div>
                            <div data-cp-parent="cp_lease_fee_type" data-cp-values="Flat Fee + Percentage of the Net Aggregate Rent">
                                <div class="row g-2"><div class="col-md-5"><div class="input-group"><span class="input-group-text">$</span><input type="text" name="lease_fee_flat_combo_net" class="form-control" value="{{ old('lease_fee_flat_combo_net', $data['lease_fee_flat_combo_net'] ?? '') }}" placeholder="Flat fee (e.g., 1,500)"></div></div><div class="col-md-1 text-center pt-2">+</div><div class="col-md-6"><div class="input-group"><input type="number" name="lease_fee_percentage_combo_net" class="form-control" value="{{ old('lease_fee_percentage_combo_net', $data['lease_fee_percentage_combo_net'] ?? '') }}" placeholder="% net aggregate rent (e.g., 6)"><span class="input-group-text">%</span></div></div></div>
                            </div>
                            @endif
                            <div data-cp-parent="cp_lease_fee_type" data-cp-values="other">
                                <input type="text" name="lease_fee_other" class="form-control" value="{{ old('lease_fee_other', $data['lease_fee_other'] ?? '') }}" placeholder="Enter custom lease fee structure">
                            </div>
                        </div>
                    </div>
                @endif

                @if ($role === 'seller')
                    {{-- Seller's Broker Purchase Fee --}}
                    <div class="mb-4">
                        <label class="fw-bold">Seller's Broker Purchase Fee</label>
                        <div class="input-cover mt-2">
                            <select id="cp_purchase_fee_type" name="purchase_fee_type" class="form-control has-icon"
                                    data-icon="fa-solid fa-file-invoice-dollar" onchange="_cpTrigger(this.id)">
                                <option value="">Select</option>
                                <option value="percentage" @selected(old('purchase_fee_type', $data['purchase_fee_type'] ?? '') === 'percentage')>Percentage of the Total Purchase Price</option>
                                <option value="flat" @selected(old('purchase_fee_type', $data['purchase_fee_type'] ?? '') === 'flat')>Flat Fee</option>
                                <option value="combo" @selected(old('purchase_fee_type', $data['purchase_fee_type'] ?? '') === 'combo')>Percentage of the Total Purchase Price + Flat Fee</option>
                                <option value="other" @selected(old('purchase_fee_type', $data['purchase_fee_type'] ?? '') === 'other')>Other</option>
                            </select>
                        </div>
                        <div class="mt-2">
                            <div data-cp-parent="cp_purchase_fee_type" data-cp-values="flat">
                                <div class="input-group"><span class="input-group-text">$</span><input type="text" name="purchase_fee_flat" class="form-control" value="{{ old('purchase_fee_flat', $data['purchase_fee_flat'] ?? '') }}" placeholder="Enter flat fee amount (e.g., 5,000)"></div>
                            </div>
                            <div data-cp-parent="cp_purchase_fee_type" data-cp-values="percentage">
                                <div class="input-group"><input type="number" name="purchase_fee_percentage" class="form-control" value="{{ old('purchase_fee_percentage', $data['purchase_fee_percentage'] ?? '') }}" placeholder="Enter percentage of total purchase price (e.g., 6)"><span class="input-group-text">%</span></div>
                            </div>
                            <div data-cp-parent="cp_purchase_fee_type" data-cp-values="combo">
                                <div class="row g-2"><div class="col-md-6"><div class="input-group"><input type="number" name="purchase_fee_percentage_combo" class="form-control" value="{{ old('purchase_fee_percentage_combo', $data['purchase_fee_percentage_combo'] ?? '') }}" placeholder="Enter percentage (e.g., 2)"><span class="input-group-text">%</span></div></div><div class="col-md-1 text-center pt-2">+</div><div class="col-md-5"><div class="input-group"><span class="input-group-text">$</span><input type="text" name="purchase_fee_flat_combo" class="form-control" value="{{ old('purchase_fee_flat_combo', $data['purchase_fee_flat_combo'] ?? '') }}" placeholder="Enter flat fee (e.g., 2,000)"></div></div></div>
                            </div>
                            <div data-cp-parent="cp_purchase_fee_type" data-cp-values="other">
                                <input type="text" name="purchase_fee_other" class="form-control" value="{{ old('purchase_fee_other', $data['purchase_fee_other'] ?? '') }}" placeholder="Enter commission structure (e.g., Tiered fee: 5% on the first $500,000, 3% above)">
                            </div>
                        </div>
                    </div>

                    @if (in_array($propertyType, ['income', 'commercial', 'business']))
                    <div class="mb-4">
                        <label class="fw-bold">Nominal Consideration Fee</label>
                        <div class="input-group mt-2"><span class="input-group-text">$</span>
                            <input type="text" name="nominal" class="form-control" value="{{ old('nominal', $data['nominal'] ?? '') }}" placeholder="Enter nominal consideration fee amount (e.g., 1,000)">
                        </div>
                    </div>
                    @endif

                    {{-- Buyer's Broker Commission Structure --}}
                    <div class="mb-2">
                        <label class="fw-bold">Buyer's Broker Commission Structure</label>
                        <div class="input-cover mt-2">
                            <select id="cp_commission_structure" name="commission_structure" class="form-control has-icon"
                                    data-icon="fa-solid fa-file-invoice-dollar" onchange="_cpTrigger(this.id)">
                                <option value="">Select</option>
                                <option value="Seller's Broker to Compensate Buyer's Broker from Seller's Broker Commission" @selected(old('commission_structure', $data['commission_structure'] ?? '') === "Seller's Broker to Compensate Buyer's Broker from Seller's Broker Commission")>Seller's Broker to Compensate Buyer's Broker from Seller's Broker Commission</option>
                                <option value="Seller to Pay Buyer's Broker Separately" @selected(old('commission_structure', $data['commission_structure'] ?? '') === "Seller to Pay Buyer's Broker Separately")>Seller to Pay Buyer's Broker Separately</option>
                                <option value="No Compensation Offered to the Buyer's Broker" @selected(old('commission_structure', $data['commission_structure'] ?? '') === "No Compensation Offered to the Buyer's Broker")>No Compensation Offered to the Buyer's Broker</option>
                            </select>
                        </div>
                    </div>
                    <div data-cp-parent="cp_commission_structure"
                         data-cp-values="Seller's Broker to Compensate Buyer's Broker from Seller's Broker Commission|Seller to Pay Buyer's Broker Separately"
                         class="mb-4 ps-3 border-start border-2 border-secondary">
                        <label class="fw-bold mt-2">Buyer's Broker Commission Fee</label>
                        <div class="input-cover mt-2">
                            <select id="cp_commission_structure_type" name="commission_structure_type" class="form-control has-icon"
                                    data-icon="fa-solid fa-file-invoice-dollar" onchange="_cpTrigger(this.id)">
                                <option value="">Select</option>
                                <option value="Percentage of the Total Purchase Price" @selected(old('commission_structure_type', $data['commission_structure_type'] ?? '') === 'Percentage of the Total Purchase Price')>Percentage of the Total Purchase Price</option>
                                <option value="Flat Fee" @selected(old('commission_structure_type', $data['commission_structure_type'] ?? '') === 'Flat Fee')>Flat Fee</option>
                                <option value="other" @selected(old('commission_structure_type', $data['commission_structure_type'] ?? '') === 'other')>Other</option>
                            </select>
                        </div>
                        <div class="mt-2">
                            <div data-cp-parent="cp_commission_structure_type" data-cp-values="Flat Fee">
                                <div class="input-group"><span class="input-group-text">$</span><input type="text" name="commission_structure_type_fee_flat" class="form-control" value="{{ old('commission_structure_type_fee_flat', $data['commission_structure_type_fee_flat'] ?? '') }}" placeholder="Enter flat fee amount (e.g., 4,000)"></div>
                            </div>
                            <div data-cp-parent="cp_commission_structure_type" data-cp-values="Percentage of the Total Purchase Price">
                                <div class="input-group"><input type="number" name="commission_structure_type_fee_percentage" class="form-control" value="{{ old('commission_structure_type_fee_percentage', $data['commission_structure_type_fee_percentage'] ?? '') }}" placeholder="Enter percentage of the total purchase price (e.g., 3)"><span class="input-group-text">%</span></div>
                            </div>
                            <div data-cp-parent="cp_commission_structure_type" data-cp-values="other">
                                <input type="text" name="commission_structure_type_fee_other" class="form-control" value="{{ old('commission_structure_type_fee_other', $data['commission_structure_type_fee_other'] ?? '') }}" placeholder="Enter custom Buyer's Broker fee structure">
                            </div>
                        </div>
                    </div>

                    {{-- Seller: Interested in Offering a Lease Agreement --}}
                    <div class="mb-2 mt-4">
                        <label class="fw-bold">Interested in Offering a Lease Agreement</label>
                        <div class="input-cover mt-2">
                            <select id="cp_interested_purchase_fee_type" name="interested_purchase_fee_type"
                                    class="form-control has-icon" data-icon="fa-solid fa-file-invoice-dollar"
                                    onchange="_cpTrigger(this.id)">
                                <option value="">Select</option>
                                <option value="Yes" @selected(old('interested_purchase_fee_type', $data['interested_purchase_fee_type'] ?? '') === 'Yes')>Yes</option>
                                <option value="No" @selected(old('interested_purchase_fee_type', $data['interested_purchase_fee_type'] ?? '') === 'No')>No</option>
                            </select>
                        </div>
                    </div>
                    <div data-cp-parent="cp_interested_purchase_fee_type" data-cp-values="Yes"
                         class="mb-4 ps-3 border-start border-2 border-secondary">
                        <label class="fw-bold mt-2">Seller's Broker Leasing Fee</label>
                        <div class="input-cover mt-2">
                            <select id="cp_seller_leasing_fee_type" name="seller_leasing_fee_type"
                                    class="form-control has-icon" data-icon="fa-solid fa-file-invoice-dollar"
                                    onchange="_cpTrigger(this.id)">
                                <option value="">Select</option>
                                @if (in_array($propertyType, ['residential', 'income', 'vacant_land']))
                                    <option value="Percentage of the Rent Due Each Rental Period" @selected(old('seller_leasing_fee_type', $data['seller_leasing_fee_type'] ?? '') === 'Percentage of the Rent Due Each Rental Period')>Percentage of the Rent Due Each Rental Period</option>
                                    <option value="Percentage of the Gross Lease Value" @selected(old('seller_leasing_fee_type', $data['seller_leasing_fee_type'] ?? '') === 'Percentage of the Gross Lease Value')>Percentage of the Gross Lease Value</option>
                                    <option value="Percentage of the First Month's Rent" @selected(old('seller_leasing_fee_type', $data['seller_leasing_fee_type'] ?? '') === "Percentage of the First Month's Rent")>Percentage of the First Month's Rent</option>
                                    <option value="Flat Fee" @selected(old('seller_leasing_fee_type', $data['seller_leasing_fee_type'] ?? '') === 'Flat Fee')>Flat Fee</option>
                                    <option value="other" @selected(old('seller_leasing_fee_type', $data['seller_leasing_fee_type'] ?? '') === 'other')>Other</option>
                                @elseif (in_array($propertyType, ['commercial', 'business']))
                                    <option value="Percentage of Net Aggregate Rent" @selected(old('seller_leasing_fee_type', $data['seller_leasing_fee_type'] ?? '') === 'Percentage of Net Aggregate Rent')>Percentage of Net Aggregate Rent</option>
                                    <option value="Percentage of Gross Rent" @selected(old('seller_leasing_fee_type', $data['seller_leasing_fee_type'] ?? '') === 'Percentage of Gross Rent')>Percentage of Gross Rent</option>
                                    <option value="Percentage of Month's Rent" @selected(old('seller_leasing_fee_type', $data['seller_leasing_fee_type'] ?? '') === "Percentage of Month's Rent")>Percentage of Month's Rent</option>
                                    <option value="Flat Fee" @selected(old('seller_leasing_fee_type', $data['seller_leasing_fee_type'] ?? '') === 'Flat Fee')>Flat Fee</option>
                                @endif
                            </select>
                        </div>
                        <div class="mt-2">
                            <div data-cp-parent="cp_seller_leasing_fee_type" data-cp-values="Percentage of the Rent Due Each Rental Period">
                                <div class="input-group"><input type="number" name="seller_leasing_gross_rental" class="form-control" value="{{ old('seller_leasing_gross_rental', $data['seller_leasing_gross_rental'] ?? '') }}" placeholder="Enter percentage of the rent due each rental period (e.g., 10)"><span class="input-group-text">%</span></div>
                            </div>
                            <div data-cp-parent="cp_seller_leasing_fee_type" data-cp-values="Percentage of the Gross Lease Value">
                                <div class="input-group"><input type="number" name="seller_leasing_gross" class="form-control" value="{{ old('seller_leasing_gross', $data['seller_leasing_gross'] ?? '') }}" placeholder="Enter percentage of the gross lease value (e.g., 10)"><span class="input-group-text">%</span></div>
                            </div>
                            <div data-cp-parent="cp_seller_leasing_fee_type" data-cp-values="Percentage of the First Month's Rent">
                                <div class="input-group"><input type="number" name="seller_leasing_gross_month_rent" class="form-control" value="{{ old('seller_leasing_gross_month_rent', $data['seller_leasing_gross_month_rent'] ?? '') }}" placeholder="Enter percentage of the first month's rent (e.g., 100)"><span class="input-group-text">%</span></div>
                            </div>
                            <div data-cp-parent="cp_seller_leasing_fee_type" data-cp-values="Percentage of Net Aggregate Rent">
                                <div class="input-group"><input type="number" name="seller_leasing_gross_other" class="form-control" value="{{ old('seller_leasing_gross_other', $data['seller_leasing_gross_other'] ?? '') }}" placeholder="Enter percentage of net aggregate rent (e.g., 6)"><span class="input-group-text">%</span></div>
                            </div>
                            <div data-cp-parent="cp_seller_leasing_fee_type" data-cp-values="Percentage of Gross Rent">
                                <div class="row gy-2">
                                    <div class="col-12"><div class="input-group"><input type="number" name="seller_leasing_gross_percentage" class="form-control" value="{{ old('seller_leasing_gross_percentage', $data['seller_leasing_gross_percentage'] ?? '') }}" placeholder="Enter percentage of the gross rent (e.g., 6)"><span class="input-group-text">%</span></div></div>
                                    <div class="col-12"><label class="fw-bold">Sales Tax</label><div class="input-cover mt-1"><select name="seller_leasing_gross_sales_tax_option_gross" class="form-control has-icon" data-icon="fa-solid fa-ruler"><option value="">Select</option><option value="including" @selected(old('seller_leasing_gross_sales_tax_option_gross', $data['seller_leasing_gross_sales_tax_option_gross'] ?? '') === 'including')>Including Sales Tax</option><option value="excluding" @selected(old('seller_leasing_gross_sales_tax_option_gross', $data['seller_leasing_gross_sales_tax_option_gross'] ?? '') === 'excluding')>Excluding Sales Tax</option></select></div></div>
                                </div>
                            </div>
                            <div data-cp-parent="cp_seller_leasing_fee_type" data-cp-values="Percentage of Month's Rent">
                                <div class="row gy-2">
                                    <div class="col-12"><div class="input-group"><input type="number" name="seller_leasing_gross_month_rent" class="form-control" value="{{ old('seller_leasing_gross_month_rent', $data['seller_leasing_gross_month_rent'] ?? '') }}" placeholder="Enter percentage of month's rent (e.g., 100)"><span class="input-group-text">%</span></div></div>
                                    <div class="col-12"><label class="fw-bold">Sales Tax</label><div class="input-cover mt-1"><select name="seller_leasing_gross_sales_tax_first_month" class="form-control has-icon" data-icon="fa-solid fa-ruler"><option value="">Select</option><option value="including" @selected(old('seller_leasing_gross_sales_tax_first_month', $data['seller_leasing_gross_sales_tax_first_month'] ?? '') === 'including')>Including Sales Tax</option><option value="excluding" @selected(old('seller_leasing_gross_sales_tax_first_month', $data['seller_leasing_gross_sales_tax_first_month'] ?? '') === 'excluding')>Excluding Sales Tax</option></select></div></div>
                                    <div class="col-12"><label class="fw-bold">Number of Months</label><div class="input-group mt-1"><span class="input-group-text">#</span><input type="number" name="seller_leasing_gross_no_of_months" class="form-control" value="{{ old('seller_leasing_gross_no_of_months', $data['seller_leasing_gross_no_of_months'] ?? '') }}" placeholder="Enter number of months (e.g., 1)"></div></div>
                                </div>
                            </div>
                            <div data-cp-parent="cp_seller_leasing_fee_type" data-cp-values="Flat Fee">
                                @if (in_array($propertyType, ['commercial', 'business']))
                                <div class="mb-2"><label class="fw-bold">Sales Tax</label><div class="input-cover mt-1"><select name="seller_leasing_gross_sales_tax_flat_free_gross" class="form-control has-icon" data-icon="fa-solid fa-ruler"><option value="">Select</option><option value="including" @selected(old('seller_leasing_gross_sales_tax_flat_free_gross', $data['seller_leasing_gross_sales_tax_flat_free_gross'] ?? '') === 'including')>Including Sales Tax</option><option value="excluding" @selected(old('seller_leasing_gross_sales_tax_flat_free_gross', $data['seller_leasing_gross_sales_tax_flat_free_gross'] ?? '') === 'excluding')>Excluding Sales Tax</option></select></div></div>
                                @endif
                                <div class="input-group"><span class="input-group-text">$</span><input type="text" name="seller_leasing_gross_purchase_fee_flat_amount" class="form-control" value="{{ old('seller_leasing_gross_purchase_fee_flat_amount', $data['seller_leasing_gross_purchase_fee_flat_amount'] ?? '') }}" placeholder="Enter flat fee amount (e.g., 5,000)"></div>
                            </div>
                            <div data-cp-parent="cp_seller_leasing_fee_type" data-cp-values="other">
                                <input type="text" name="seller_leasing_gross_purchase_fee_other" class="form-control" value="{{ old('seller_leasing_gross_purchase_fee_other', $data['seller_leasing_gross_purchase_fee_other'] ?? '') }}" placeholder="Enter lease fee structure (e.g., 100% of First Month's Rent)">
                            </div>
                        </div>
                    </div>
                @endif

                @if ($role === 'landlord')
                    @if ($propertyType === 'residential')
                        {{-- Landlord's Broker Lease Fee (Residential) --}}
                        <div class="mb-4">
                            <label class="fw-bold">Landlord's Broker Lease Fee</label>
                            <div class="input-cover mt-2">
                                <select id="cp_purchase_fee_type" name="purchase_fee_type" class="form-control has-icon"
                                        data-icon="fa-solid fa-file-invoice-dollar" onchange="_cpTrigger(this.id)">
                                    <option value="">Select</option>
                                    <option value="Percentage of the Rent Due Each Rental Period" @selected(old('purchase_fee_type', $data['purchase_fee_type'] ?? '') === 'Percentage of the Rent Due Each Rental Period')>Percentage of the Rent Due Each Rental Period</option>
                                    <option value="Percentage of the Gross Lease Value" @selected(old('purchase_fee_type', $data['purchase_fee_type'] ?? '') === 'Percentage of the Gross Lease Value')>Percentage of the Gross Lease Value</option>
                                    <option value="Percentage of the First Month’s Rent" @selected(old('purchase_fee_type', $data['purchase_fee_type'] ?? '') === "Percentage of the First Month’s Rent")>Percentage of the First Month’s Rent</option>
                                    <option value="Flat Fee" @selected(old('purchase_fee_type', $data['purchase_fee_type'] ?? '') === 'Flat Fee')>Flat Fee</option>
                                    <option value="other" @selected(old('purchase_fee_type', $data['purchase_fee_type'] ?? '') === 'other')>Other</option>
                                </select>
                            </div>
                            <div class="mt-2">
                                <div data-cp-parent="cp_purchase_fee_type" data-cp-values="Percentage of the Rent Due Each Rental Period">
                                    <div class="input-group"><input type="number" name="purchase_fee_rental_period" class="form-control" value="{{ old('purchase_fee_rental_period', $data['purchase_fee_rental_period'] ?? '') }}" placeholder="Enter percentage of the rent due each rental period (e.g., 10)"><span class="input-group-text">%</span></div>
                                </div>
                                <div data-cp-parent="cp_purchase_fee_type" data-cp-values="Percentage of the Gross Lease Value">
                                    <div class="input-group"><input type="number" name="purchase_fee_percentage_combo" class="form-control" value="{{ old('purchase_fee_percentage_combo', $data['purchase_fee_percentage_combo'] ?? '') }}" placeholder="Enter percentage of the gross lease value (e.g., 10)"><span class="input-group-text">%</span></div>
                                </div>
                                <div data-cp-parent="cp_purchase_fee_type" data-cp-values="Percentage of the First Month’s Rent">
                                    <div class="input-group"><input type="number" name="purchase_fee_flat_combo" class="form-control" value="{{ old('purchase_fee_flat_combo', $data['purchase_fee_flat_combo'] ?? '') }}" placeholder="Enter percentage of the first month's rent (e.g., 100)"><span class="input-group-text">%</span></div>
                                </div>
                                <div data-cp-parent="cp_purchase_fee_type" data-cp-values="Flat Fee">
                                    <div class="input-group"><span class="input-group-text">$</span><input type="text" name="purchase_fee_flat" class="form-control" value="{{ old('purchase_fee_flat', $data['purchase_fee_flat'] ?? '') }}" placeholder="Enter flat fee amount (e.g., 5,000)"></div>
                                </div>
                                <div data-cp-parent="cp_purchase_fee_type" data-cp-values="other">
                                    <input type="text" name="purchase_fee_other" class="form-control" value="{{ old('purchase_fee_other', $data['purchase_fee_other'] ?? '') }}" placeholder="Enter lease fee structure (e.g., 100% of First Month's Rent)">
                                </div>
                            </div>
                        </div>

                        {{-- Tenant's Broker Commission Structure --}}
                        <div class="mb-2">
                            <label class="fw-bold">Tenant's Broker Commission Structure</label>
                            <div class="input-cover mt-2">
                                <select id="cp_tenant_broker_commission_structure" name="tenant_broker_commission_structure"
                                        class="form-control has-icon" data-icon="fa-solid fa-handshake"
                                        onchange="_cpTrigger(this.id)">
                                    <option value="">Select</option>
                                    <option value="Landlord's Broker to Compensate Tenant's Broker from Landlord's Broker Commission" @selected(old('tenant_broker_commission_structure', $data['tenant_broker_commission_structure'] ?? '') === "Landlord's Broker to Compensate Tenant's Broker from Landlord's Broker Commission")>Landlord's Broker to Compensate Tenant's Broker from Landlord's Broker Commission</option>
                                    <option value="Landlord to Pay Tenant's Broker Separately" @selected(old('tenant_broker_commission_structure', $data['tenant_broker_commission_structure'] ?? '') === "Landlord to Pay Tenant's Broker Separately")>Landlord to Pay Tenant's Broker Separately</option>
                                    <option value="No Compensation Offered to the Tenant's Broker" @selected(old('tenant_broker_commission_structure', $data['tenant_broker_commission_structure'] ?? '') === "No Compensation Offered to the Tenant's Broker")>No Compensation Offered to the Tenant's Broker</option>
                                </select>
                            </div>
                        </div>
                        <div data-cp-parent="cp_tenant_broker_commission_structure"
                             data-cp-values="Landlord's Broker to Compensate Tenant's Broker from Landlord's Broker Commission|Landlord to Pay Tenant's Broker Separately"
                             class="mb-4 ps-3 border-start border-2 border-secondary">
                            <label class="fw-bold mt-2">Tenant's Broker Commission Fee</label>
                            <div class="input-cover mt-2">
                                <select id="cp_tenant_broker_fee_structure" name="tenant_broker_fee_structure"
                                        class="form-control has-icon" data-icon="fa-solid fa-ruler"
                                        onchange="_cpTrigger(this.id)">
                                    <option value="">Select</option>
                                    <option value="Percentage of the Rent Due Each Rental Period" @selected(old('tenant_broker_fee_structure', $data['tenant_broker_fee_structure'] ?? '') === 'Percentage of the Rent Due Each Rental Period')>Percentage of the Rent Due Each Rental Period</option>
                                    <option value="Percentage of the Gross Lease Value" @selected(old('tenant_broker_fee_structure', $data['tenant_broker_fee_structure'] ?? '') === 'Percentage of the Gross Lease Value')>Percentage of the Gross Lease Value</option>
                                    <option value="Percentage of the First Month’s Rent" @selected(old('tenant_broker_fee_structure', $data['tenant_broker_fee_structure'] ?? '') === "Percentage of the First Month’s Rent")>Percentage of the First Month’s Rent</option>
                                    <option value="Flat fee" @selected(old('tenant_broker_fee_structure', $data['tenant_broker_fee_structure'] ?? '') === 'Flat fee')>Flat Fee</option>
                                    <option value="Other" @selected(old('tenant_broker_fee_structure', $data['tenant_broker_fee_structure'] ?? '') === 'Other')>Other</option>
                                </select>
                            </div>
                            <div class="mt-2">
                                <div data-cp-parent="cp_tenant_broker_fee_structure" data-cp-values="Percentage of the Rent Due Each Rental Period">
                                    <div class="input-group"><input type="number" name="tenant_broker_percentage" class="form-control" value="{{ old('tenant_broker_percentage', $data['tenant_broker_percentage'] ?? '') }}" placeholder="Enter percentage of the rent due each rental period (e.g., 5)"><span class="input-group-text">%</span></div>
                                </div>
                                <div data-cp-parent="cp_tenant_broker_fee_structure" data-cp-values="Percentage of the Gross Lease Value">
                                    <div class="input-group"><input type="number" name="tenant_broker_gross_lease" class="form-control" value="{{ old('tenant_broker_gross_lease', $data['tenant_broker_gross_lease'] ?? '') }}" placeholder="Enter percentage of the gross lease value (e.g., 5)"><span class="input-group-text">%</span></div>
                                </div>
                                <div data-cp-parent="cp_tenant_broker_fee_structure" data-cp-values="Percentage of the First Month’s Rent">
                                    <div class="input-group"><input type="number" name="tenant_broker_first_month_rent" class="form-control" value="{{ old('tenant_broker_first_month_rent', $data['tenant_broker_first_month_rent'] ?? '') }}" placeholder="Enter percentage of the first month's rent (e.g., 50)"><span class="input-group-text">%</span></div>
                                </div>
                                <div data-cp-parent="cp_tenant_broker_fee_structure" data-cp-values="Flat fee">
                                    <div class="input-group"><span class="input-group-text">$</span><input type="text" name="tenant_broker_flat_fee" class="form-control" value="{{ old('tenant_broker_flat_fee', $data['tenant_broker_flat_fee'] ?? '') }}" placeholder="Enter flat fee amount (e.g., 1,000)"></div>
                                </div>
                                <div data-cp-parent="cp_tenant_broker_fee_structure" data-cp-values="Other">
                                    <input type="text" name="tenant_broker_other" class="form-control" value="{{ old('tenant_broker_other', $data['tenant_broker_other'] ?? '') }}" placeholder="Enter Tenant's Broker commission arrangement">
                                </div>
                            </div>
                        </div>

                        {{-- Payment Timing for Broker Fees (Landlord Residential) --}}
                        <div class="mb-4">
                            <label class="fw-bold">Payment Timing for Broker Fees</label>
                            <div class="input-cover mt-2">
                                <select id="cp_broker_fee_timing" name="broker_fee_timing" class="form-control has-icon"
                                        data-icon="fa-solid fa-clock" onchange="_cpTrigger(this.id)">
                                    <option value="">Select</option>
                                    <option value="Deducted from Rent Collected" @selected(old('broker_fee_timing', $data['broker_fee_timing'] ?? '') === 'Deducted from Rent Collected')>Deducted from Rent Collected</option>
                                    <option value="Paid Within Calendar Days After Executed Lease" @selected(old('broker_fee_timing', $data['broker_fee_timing'] ?? '') === 'Paid Within Calendar Days After Executed Lease')>Paid Within Calendar Days After Executed Lease</option>
                                    <option value="Paid Within Calendar Days of Tenant Rent Payment" @selected(old('broker_fee_timing', $data['broker_fee_timing'] ?? '') === 'Paid Within Calendar Days of Tenant Rent Payment')>Paid Within Calendar Days of Tenant Rent Payment</option>
                                    <option value="other" @selected(old('broker_fee_timing', $data['broker_fee_timing'] ?? '') === 'other')>Other</option>
                                </select>
                            </div>
                            <div class="mt-2">
                                <div data-cp-parent="cp_broker_fee_timing" data-cp-values="Deducted from Rent Collected">
                                    <div class="input-group"><span class="input-group-text">#</span><input type="number" name="broker_fee_days_from_rent" class="form-control" value="{{ old('broker_fee_days_from_rent', $data['broker_fee_days_from_rent'] ?? '') }}" placeholder="Enter number of calendar days (e.g., 5)"></div>
                                </div>
                                <div data-cp-parent="cp_broker_fee_timing" data-cp-values="Paid Within Calendar Days After Executed Lease">
                                    <div class="input-group"><span class="input-group-text">#</span><input type="number" name="broker_fee_days_after_lease" class="form-control" value="{{ old('broker_fee_days_after_lease', $data['broker_fee_days_after_lease'] ?? '') }}" placeholder="Enter number of calendar days (e.g., 5)"></div>
                                </div>
                                <div data-cp-parent="cp_broker_fee_timing" data-cp-values="Paid Within Calendar Days of Tenant Rent Payment">
                                    <div class="input-group"><span class="input-group-text">#</span><input type="number" name="broker_fee_days_after_rent" class="form-control" value="{{ old('broker_fee_days_after_rent', $data['broker_fee_days_after_rent'] ?? '') }}" placeholder="Enter number of calendar days (e.g., 5)"></div>
                                </div>
                                <div data-cp-parent="cp_broker_fee_timing" data-cp-values="other">
                                    <input type="text" name="broker_fee_timing_other" class="form-control" value="{{ old('broker_fee_timing_other', $data['broker_fee_timing_other'] ?? '') }}" placeholder="Describe payment arrangement">
                                </div>
                            </div>
                        </div>

                        {{-- Lease Renewal/Extension Fee (Landlord Residential) --}}
                        <div class="mb-4">
                            <label class="fw-bold">Lease Renewal/Extension Fee</label>
                            <div class="input-cover mt-2">
                                <select id="cp_renewal_fee_type_res" name="renewal_fee_type" class="form-control has-icon"
                                        data-icon="fa-solid fa-file-invoice-dollar" onchange="_cpTrigger(this.id)">
                                    <option value="">Select</option>
                                    <option value="Percentage of the Rent Due Each Rental Period" @selected(old('renewal_fee_type', $data['renewal_fee_type'] ?? '') === 'Percentage of the Rent Due Each Rental Period')>Percentage of the Rent Due Each Rental Period</option>
                                    <option value="Percentage of the Gross Lease Value" @selected(old('renewal_fee_type', $data['renewal_fee_type'] ?? '') === 'Percentage of the Gross Lease Value')>Percentage of the Gross Lease Value</option>
                                    <option value="Percentage of the First Month's Rent" @selected(old('renewal_fee_type', $data['renewal_fee_type'] ?? '') === "Percentage of the First Month’s Rent")>Percentage of the First Month's Rent</option>
                                    <option value="Flat Fee" @selected(old('renewal_fee_type', $data['renewal_fee_type'] ?? '') === 'Flat Fee')>Flat Fee</option>
                                    <option value="other" @selected(old('renewal_fee_type', $data['renewal_fee_type'] ?? '') === 'other')>Other</option>
                                </select>
                            </div>
                            <div class="mt-2">
                                <div data-cp-parent="cp_renewal_fee_type_res" data-cp-values="Percentage of the Rent Due Each Rental Period">
                                    <div class="input-group"><input type="number" name="renewal_fee_percentage" class="form-control" value="{{ old('renewal_fee_percentage', $data['renewal_fee_percentage'] ?? '') }}" placeholder="Enter percentage of the rent due each rental period (e.g., 10)"><span class="input-group-text">%</span></div>
                                </div>
                                <div data-cp-parent="cp_renewal_fee_type_res" data-cp-values="Percentage of the Gross Lease Value">
                                    <div class="input-group"><input type="number" name="renewal_fee_lease_value" class="form-control" value="{{ old('renewal_fee_lease_value', $data['renewal_fee_lease_value'] ?? '') }}" placeholder="Enter percentage of the gross lease value (e.g., 10)"><span class="input-group-text">%</span></div>
                                </div>
                                <div data-cp-parent="cp_renewal_fee_type_res" data-cp-values="Percentage of the First Month's Rent">
                                    <div class="input-group"><input type="number" name="renewal_fee_first_month" class="form-control" value="{{ old('renewal_fee_first_month', $data['renewal_fee_first_month'] ?? '') }}" placeholder="Enter percentage of first month's rent (e.g., 100)"><span class="input-group-text">%</span></div>
                                </div>
                                <div data-cp-parent="cp_renewal_fee_type_res" data-cp-values="Flat Fee">
                                    <div class="input-group"><span class="input-group-text">$</span><input type="text" name="renewal_fee_flat_free" class="form-control" value="{{ old('renewal_fee_flat_free', $data['renewal_fee_flat_free'] ?? '') }}" placeholder="Enter flat fee amount (e.g., 2,000)"></div>
                                </div>
                                <div data-cp-parent="cp_renewal_fee_type_res" data-cp-values="other">
                                    <input type="text" name="renewal_fee_custom" class="form-control" value="{{ old('renewal_fee_custom', $data['renewal_fee_custom'] ?? '') }}" placeholder="Enter commission structure (e.g., $500 flat fee plus 5% of the gross lease value)">
                                </div>
                            </div>
                        </div>
                    @elseif ($propertyType === 'commercial')
                        {{-- Landlord's Broker Lease Fee (Commercial) --}}
                        <div class="mb-4">
                            <label class="fw-bold">Landlord's Broker Lease Fee</label>
                            <div class="input-cover mt-2">
                                <select id="cp_purchase_fee_type" name="purchase_fee_type" class="form-control has-icon"
                                        data-icon="fa-solid fa-file-invoice-dollar" onchange="_cpTrigger(this.id)">
                                    <option value="">Select</option>
                                    <option value="Percentage of the Net Aggregate Rent" @selected(old('purchase_fee_type', $data['purchase_fee_type'] ?? '') === 'Percentage of the Net Aggregate Rent')>Percentage of the Net Aggregate Rent</option>
                                    <option value="Percentage of the Gross Rent" @selected(old('purchase_fee_type', $data['purchase_fee_type'] ?? '') === 'Percentage of the Gross Rent')>Percentage of the Gross Rent</option>
                                    <option value="Percentage of Month’s Rent" @selected(old('purchase_fee_type', $data['purchase_fee_type'] ?? '') === "Percentage of Month’s Rent")>Percentage of Month’s Rent</option>
                                    <option value="Flat Fee" @selected(old('purchase_fee_type', $data['purchase_fee_type'] ?? '') === 'Flat Fee')>Flat Fee</option>
                                    <option value="other" @selected(old('purchase_fee_type', $data['purchase_fee_type'] ?? '') === 'other')>Other</option>
                                </select>
                            </div>
                            <div class="mt-2">
                                <div data-cp-parent="cp_purchase_fee_type" data-cp-values="Percentage of the Net Aggregate Rent">
                                    <div class="input-group"><input type="number" name="purchase_fee_net_aggregate" class="form-control" value="{{ old('purchase_fee_net_aggregate', $data['purchase_fee_net_aggregate'] ?? '') }}" placeholder="Enter percentage of the net aggregate rent (e.g., 5)"><span class="input-group-text">%</span></div>
                                </div>
                                <div data-cp-parent="cp_purchase_fee_type" data-cp-values="Percentage of the Gross Rent">
                                    <div class="row gy-2">
                                        <div class="col-12"><div class="input-group"><input type="number" name="purchase_fee_gross_rent" class="form-control" value="{{ old('purchase_fee_gross_rent', $data['purchase_fee_gross_rent'] ?? '') }}" placeholder="Enter percentage of the gross rent (e.g., 5)"><span class="input-group-text">%</span></div></div>
                                        <div class="col-12"><label class="fw-bold">Sales Tax</label><div class="input-cover mt-1"><select name="sales_tax_option_gross" class="form-control has-icon" data-icon="fa-solid fa-ruler"><option value="">Select</option><option value="including" @selected(old('sales_tax_option_gross', $data['sales_tax_option_gross'] ?? '') === 'including')>Including Sales Tax</option><option value="excluding" @selected(old('sales_tax_option_gross', $data['sales_tax_option_gross'] ?? '') === 'excluding')>Excluding Sales Tax</option></select></div></div>
                                    </div>
                                </div>
                                <div data-cp-parent="cp_purchase_fee_type" data-cp-values="Percentage of Month’s Rent">
                                    <div class="row gy-2">
                                        <div class="col-12"><div class="input-group"><input type="number" name="purchase_fee_monthly_percentage" class="form-control" value="{{ old('purchase_fee_monthly_percentage', $data['purchase_fee_monthly_percentage'] ?? '') }}" placeholder="Enter percentage of month's rent (e.g., 100)"><span class="input-group-text">%</span></div></div>
                                        <div class="col-12"><label class="fw-bold">Number of Months</label><div class="input-group mt-1"><span class="input-group-text">#</span><input type="number" name="purchase_fee_months" class="form-control" value="{{ old('purchase_fee_months', $data['purchase_fee_months'] ?? '') }}" placeholder="Enter number of months (e.g., 1)"></div></div>
                                        <div class="col-12"><label class="fw-bold">Sales Tax</label><div class="input-cover mt-1"><select name="sales_tax_option_monthly" class="form-control has-icon" data-icon="fa-solid fa-ruler"><option value="">Select</option><option value="including" @selected(old('sales_tax_option_monthly', $data['sales_tax_option_monthly'] ?? '') === 'including')>Including Sales Tax</option><option value="excluding" @selected(old('sales_tax_option_monthly', $data['sales_tax_option_monthly'] ?? '') === 'excluding')>Excluding Sales Tax</option></select></div></div>
                                    </div>
                                </div>
                                <div data-cp-parent="cp_purchase_fee_type" data-cp-values="Flat Fee">
                                    <div class="row gy-2">
                                        <div class="col-12"><div class="input-group"><span class="input-group-text">$</span><input type="text" name="purchase_fee_flat_commercial" class="form-control" value="{{ old('purchase_fee_flat_commercial', $data['purchase_fee_flat_commercial'] ?? '') }}" placeholder="Enter flat fee amount (e.g., 3,000)"></div></div>
                                        <div class="col-12"><label class="fw-bold">Sales Tax</label><div class="input-cover mt-1"><select name="sales_tax_option_flat" class="form-control has-icon" data-icon="fa-solid fa-ruler"><option value="">Select</option><option value="including" @selected(old('sales_tax_option_flat', $data['sales_tax_option_flat'] ?? '') === 'including')>Including Sales Tax</option><option value="excluding" @selected(old('sales_tax_option_flat', $data['sales_tax_option_flat'] ?? '') === 'excluding')>Excluding Sales Tax</option></select></div></div>
                                    </div>
                                </div>
                                <div data-cp-parent="cp_purchase_fee_type" data-cp-values="other">
                                    <input type="text" name="purchase_fee_other_commercial" class="form-control" value="{{ old('purchase_fee_other_commercial', $data['purchase_fee_other_commercial'] ?? '') }}" placeholder="Enter lease fee structure">
                                </div>
                            </div>
                        </div>

                        {{-- Payment Timing for Broker Fees (Landlord Commercial) --}}
                        <div class="mb-4">
                            <label class="fw-bold">Payment Timing for Broker Fees</label>
                            <div class="input-cover mt-2">
                                <select id="cp_broker_fee_timing" name="broker_fee_timing" class="form-control has-icon"
                                        data-icon="fa-solid fa-clock" onchange="_cpTrigger(this.id)">
                                    <option value="">Select</option>
                                    <option value="full_execution" @selected(old('broker_fee_timing', $data['broker_fee_timing'] ?? '') === 'full_execution')>Full amount upon execution of lease, sales contract, or other transfer agreement</option>
                                    <option value="50% due upon execution, 50% due upon commencement of agreement" @selected(old('broker_fee_timing', $data['broker_fee_timing'] ?? '') === '50% due upon execution, 50% due upon commencement of agreement')>50% due upon execution, 50% due upon commencement of agreement</option>
                                    <option value="50% due upon execution, 50% due upon occupancy of premises" @selected(old('broker_fee_timing', $data['broker_fee_timing'] ?? '') === '50% due upon execution, 50% due upon occupancy of premises')>50% due upon execution, 50% due upon occupancy of premises</option>
                                    <option value="Other" @selected(old('broker_fee_timing', $data['broker_fee_timing'] ?? '') === 'Other')>Other</option>
                                </select>
                            </div>
                            <div data-cp-parent="cp_broker_fee_timing" data-cp-values="Other" class="mt-2">
                                <input type="text" name="broker_fee_timing_other" class="form-control" value="{{ old('broker_fee_timing_other', $data['broker_fee_timing_other'] ?? '') }}" placeholder="Describe payment arrangement">
                            </div>
                        </div>

                        {{-- Lease Renewal/Extension Fee (Landlord Commercial) --}}
                        <div class="mb-4">
                            <label class="fw-bold">Lease Renewal/Extension Fee</label>
                            <div class="input-cover mt-2">
                                <select id="cp_renewal_fee_type_com" name="renewal_fee_type" class="form-control has-icon"
                                        data-icon="fa-solid fa-file-invoice-dollar" onchange="_cpTrigger(this.id)">
                                    <option value="">Select</option>
                                    <option value="Percentage of the Net Aggregate Rent" @selected(old('renewal_fee_type', $data['renewal_fee_type'] ?? '') === 'Percentage of the Net Aggregate Rent')>Percentage of the Net Aggregate Rent</option>
                                    <option value="Percentage of the Gross Rent" @selected(old('renewal_fee_type', $data['renewal_fee_type'] ?? '') === 'Percentage of the Gross Rent')>Percentage of the Gross Rent</option>
                                    <option value="Percentage of Month's Rent" @selected(old('renewal_fee_type', $data['renewal_fee_type'] ?? '') === "Percentage of Month’s Rent")>Percentage of Month's Rent</option>
                                    <option value="Flat Fee" @selected(old('renewal_fee_type', $data['renewal_fee_type'] ?? '') === 'Flat Fee')>Flat Fee</option>
                                    <option value="other" @selected(old('renewal_fee_type', $data['renewal_fee_type'] ?? '') === 'other')>Other</option>
                                </select>
                            </div>
                            <div class="mt-2">
                                <div data-cp-parent="cp_renewal_fee_type_com" data-cp-values="Percentage of the Net Aggregate Rent">
                                    <div class="input-group"><input type="number" name="renewal_fee_percentage" class="form-control" value="{{ old('renewal_fee_percentage', $data['renewal_fee_percentage'] ?? '') }}" placeholder="Enter percentage of the net aggregate rent (e.g., 5)"><span class="input-group-text">%</span></div>
                                </div>
                                <div data-cp-parent="cp_renewal_fee_type_com" data-cp-values="Percentage of the Gross Rent">
                                    <div class="row gy-2">
                                        <div class="col-12"><div class="input-group"><input type="number" name="renewal_fee_lease_value" class="form-control" value="{{ old('renewal_fee_lease_value', $data['renewal_fee_lease_value'] ?? '') }}" placeholder="Enter percentage of the gross rent (e.g., 5)"><span class="input-group-text">%</span></div></div>
                                        <div class="col-12"><label class="fw-bold">Sales Tax</label><div class="input-cover mt-1"><select name="renewal_fee_sales_tax_lease_value" class="form-control has-icon" data-icon="fa-solid fa-ruler"><option value="">Select</option><option value="including" @selected(old('renewal_fee_sales_tax_lease_value', $data['renewal_fee_sales_tax_lease_value'] ?? '') === 'including')>Including Sales Tax</option><option value="excluding" @selected(old('renewal_fee_sales_tax_lease_value', $data['renewal_fee_sales_tax_lease_value'] ?? '') === 'excluding')>Excluding Sales Tax</option></select></div></div>
                                    </div>
                                </div>
                                <div data-cp-parent="cp_renewal_fee_type_com" data-cp-values="Percentage of Month's Rent">
                                    <div class="row gy-2">
                                        <div class="col-12"><div class="input-group"><input type="number" name="renewal_fee_first_month" class="form-control" value="{{ old('renewal_fee_first_month', $data['renewal_fee_first_month'] ?? '') }}" placeholder="Enter percentage of month's rent (e.g., 100)"><span class="input-group-text">%</span></div></div>
                                        <div class="col-12"><label class="fw-bold">Number of Months</label><div class="input-group mt-1"><span class="input-group-text">#</span><input type="number" name="renewal_fee_no_of_months" class="form-control" value="{{ old('renewal_fee_no_of_months', $data['renewal_fee_no_of_months'] ?? '') }}" placeholder="Enter number of months (e.g., 1)"></div></div>
                                        <div class="col-12"><label class="fw-bold">Sales Tax</label><div class="input-cover mt-1"><select name="renewal_fee_sales_tax_first_month" class="form-control has-icon" data-icon="fa-solid fa-ruler"><option value="">Select</option><option value="including" @selected(old('renewal_fee_sales_tax_first_month', $data['renewal_fee_sales_tax_first_month'] ?? '') === 'including')>Including Sales Tax</option><option value="excluding" @selected(old('renewal_fee_sales_tax_first_month', $data['renewal_fee_sales_tax_first_month'] ?? '') === 'excluding')>Excluding Sales Tax</option></select></div></div>
                                    </div>
                                </div>
                                <div data-cp-parent="cp_renewal_fee_type_com" data-cp-values="Flat Fee">
                                    <div class="row gy-2">
                                        <div class="col-12"><div class="input-group"><span class="input-group-text">$</span><input type="text" name="renewal_fee_flat_free" class="form-control" value="{{ old('renewal_fee_flat_free', $data['renewal_fee_flat_free'] ?? '') }}" placeholder="Enter flat fee amount (e.g., 5,000)"></div></div>
                                        <div class="col-12"><label class="fw-bold">Sales Tax</label><div class="input-cover mt-1"><select name="renewal_fee_sales_tax_flat_fee" class="form-control has-icon" data-icon="fa-solid fa-ruler"><option value="">Select</option><option value="including" @selected(old('renewal_fee_sales_tax_flat_fee', $data['renewal_fee_sales_tax_flat_fee'] ?? '') === 'including')>Including Sales Tax</option><option value="excluding" @selected(old('renewal_fee_sales_tax_flat_fee', $data['renewal_fee_sales_tax_flat_fee'] ?? '') === 'excluding')>Excluding Sales Tax</option></select></div></div>
                                    </div>
                                </div>
                                <div data-cp-parent="cp_renewal_fee_type_com" data-cp-values="other">
                                    <input type="text" name="renewal_fee_custom" class="form-control" value="{{ old('renewal_fee_custom', $data['renewal_fee_custom'] ?? '') }}" placeholder="Describe commission fee (e.g., 50% of first month's rent plus 3% of the net aggregate rent)">
                                </div>
                            </div>
                        </div>

                        {{-- Expansion Commission for Lease Amendment (Landlord Commercial) --}}
                        <div class="mb-4">
                            <label class="fw-bold">Expansion Commission for Lease Amendment (%)</label>
                            <div class="input-group mt-2">
                                <input type="number" name="expansion_commission_percentage" class="form-control"
                                       value="{{ old('expansion_commission_percentage', $data['expansion_commission_percentage'] ?? '') }}"
                                       placeholder="Enter percentage of original commission for expansion (e.g., 50)">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    @endif

                    {{-- Interested in Property Management (Landlord, all property types) --}}
                    <div class="mb-2 mt-2">
                        <label class="fw-bold">Interested in Property Management</label>
                        <div class="input-cover mt-2">
                            <select id="cp_interested_in_property_management" name="interested_in_property_management"
                                    class="form-control has-icon" data-icon="fa-solid fa-ruler"
                                    onchange="_cpTrigger(this.id)">
                                <option value="">Select</option>
                                <option value="yes" @selected(old('interested_in_property_management', $data['interested_in_property_management'] ?? '') === 'yes')>Yes</option>
                                <option value="no" @selected(old('interested_in_property_management', $data['interested_in_property_management'] ?? '') === 'no')>No</option>
                            </select>
                        </div>
                    </div>
                    <div data-cp-parent="cp_interested_in_property_management" data-cp-values="yes"
                         class="mb-4 ps-3 border-start border-2 border-secondary">
                        <label class="fw-bold mt-2">Property Management Fee</label>
                        <div class="input-cover mt-2">
                            <select id="cp_interested_in_property_management_fee" name="interested_in_property_management_fee"
                                    class="form-control has-icon" data-icon="fa-solid fa-file-invoice-dollar"
                                    onchange="_cpTrigger(this.id)">
                                <option value="">Select</option>
                                <option value="Percentage of the Gross Lease Value" @selected(old('interested_in_property_management_fee', $data['interested_in_property_management_fee'] ?? '') === 'Percentage of the Gross Lease Value')>Percentage of the Gross Lease Value</option>
                                <option value="Percentage of the Rent Due Each Rental Period" @selected(old('interested_in_property_management_fee', $data['interested_in_property_management_fee'] ?? '') === 'Percentage of the Rent Due Each Rental Period')>Percentage of the Rent Due Each Rental Period</option>
                                <option value="Flat Fee" @selected(old('interested_in_property_management_fee', $data['interested_in_property_management_fee'] ?? '') === 'Flat Fee')>Flat Fee</option>
                                <option value="Other" @selected(old('interested_in_property_management_fee', $data['interested_in_property_management_fee'] ?? '') === 'Other')>Other</option>
                            </select>
                        </div>
                        <div class="mt-2">
                            <div data-cp-parent="cp_interested_in_property_management_fee" data-cp-values="Percentage of the Gross Lease Value">
                                <div class="input-group"><input type="number" name="interested_in_property_management_fee_gross_lease" class="form-control" value="{{ old('interested_in_property_management_fee_gross_lease', $data['interested_in_property_management_fee_gross_lease'] ?? '') }}" placeholder="Enter percentage of the gross lease value (e.g., 10)"><span class="input-group-text">%</span></div>
                            </div>
                            <div data-cp-parent="cp_interested_in_property_management_fee" data-cp-values="Percentage of the Rent Due Each Rental Period">
                                <div class="input-group"><input type="number" name="interested_in_property_management_fee_rental_periord" class="form-control" value="{{ old('interested_in_property_management_fee_rental_periord', $data['interested_in_property_management_fee_rental_periord'] ?? '') }}" placeholder="Enter percentage of the rent due each rental period (e.g., 10)"><span class="input-group-text">%</span></div>
                            </div>
                            <div data-cp-parent="cp_interested_in_property_management_fee" data-cp-values="Flat Fee">
                                <div class="input-group"><span class="input-group-text">$</span><input type="text" name="interested_in_property_management_fee_flate_free" class="form-control" value="{{ old('interested_in_property_management_fee_flate_free', $data['interested_in_property_management_fee_flate_free'] ?? '') }}" placeholder="Enter flat fee amount (e.g., 1,000)"></div>
                            </div>
                            <div data-cp-parent="cp_interested_in_property_management_fee" data-cp-values="Other">
                                <input type="text" name="interested_in_property_management_fee_other" class="form-control" value="{{ old('interested_in_property_management_fee_other', $data['interested_in_property_management_fee_other'] ?? '') }}" placeholder="Enter property management fee (e.g., 4% of the gross lease value + $500)">
                            </div>
                        </div>
                    </div>

                    {{-- Interested in Selling (Landlord) --}}
                    <div class="mb-2 mt-2">
                        <label class="fw-bold">Interested in Selling</label>
                        <div class="input-cover mt-2">
                            <select id="cp_interested_in_selling" name="interested_in_selling"
                                    class="form-control has-icon" data-icon="fa-solid fa-ruler"
                                    onchange="_cpTrigger(this.id)">
                                <option value="">Select</option>
                                <option value="Yes" @selected(old('interested_in_selling', $data['interested_in_selling'] ?? '') === 'Yes')>Yes</option>
                                <option value="No" @selected(old('interested_in_selling', $data['interested_in_selling'] ?? '') === 'No')>No</option>
                            </select>
                        </div>
                    </div>
                    <div data-cp-parent="cp_interested_in_selling" data-cp-values="Yes"
                         class="mb-4 ps-3 border-start border-2 border-secondary">
                        <label class="fw-bold mt-2">Landlord's Broker Purchase Fee</label>
                        <div class="input-cover mt-2">
                            <select id="cp_interested_in_selling_type" name="interested_in_selling_type"
                                    class="form-control has-icon" data-icon="fa-solid fa-file-invoice-dollar"
                                    onchange="_cpTrigger(this.id)">
                                <option value="">Select</option>
                                <option value="Percentage of the Total Purchase Price" @selected(old('interested_in_selling_type', $data['interested_in_selling_type'] ?? '') === 'Percentage of the Total Purchase Price')>Percentage of the Total Purchase Price</option>
                                <option value="Percentage of the Total Purchase Price + Flat Fee" @selected(old('interested_in_selling_type', $data['interested_in_selling_type'] ?? '') === 'Percentage of the Total Purchase Price + Flat Fee')>Percentage of the Total Purchase Price + Flat Fee</option>
                                <option value="Flat Fee" @selected(old('interested_in_selling_type', $data['interested_in_selling_type'] ?? '') === 'Flat Fee')>Flat Fee</option>
                                <option value="Other" @selected(old('interested_in_selling_type', $data['interested_in_selling_type'] ?? '') === 'Other')>Other</option>
                            </select>
                        </div>
                        <div class="mt-2">
                            <div data-cp-parent="cp_interested_in_selling_type" data-cp-values="Percentage of the Total Purchase Price">
                                <div class="input-group"><input type="number" name="landlord_broker_purchase_price" class="form-control" value="{{ old('landlord_broker_purchase_price', $data['landlord_broker_purchase_price'] ?? '') }}" placeholder="Enter percentage of total purchase price (e.g., 6)"><span class="input-group-text">%</span></div>
                            </div>
                            <div data-cp-parent="cp_interested_in_selling_type" data-cp-values="Percentage of the Total Purchase Price + Flat Fee">
                                <div class="row g-2"><div class="col-md-6"><div class="input-group"><input type="number" name="landlord_broker_percentage_price" class="form-control" value="{{ old('landlord_broker_percentage_price', $data['landlord_broker_percentage_price'] ?? '') }}" placeholder="Enter percentage of purchase price (e.g., 2)"><span class="input-group-text">%</span></div></div><div class="col-md-1 text-center pt-2">+</div><div class="col-md-5"><div class="input-group"><span class="input-group-text">$</span><input type="text" name="landlord_broker_dollar_price" class="form-control" value="{{ old('landlord_broker_dollar_price', $data['landlord_broker_dollar_price'] ?? '') }}" placeholder="Flat fee (e.g., 2,000)"></div></div></div>
                            </div>
                            <div data-cp-parent="cp_interested_in_selling_type" data-cp-values="Flat Fee">
                                <div class="input-group"><span class="input-group-text">$</span><input type="text" name="landlord_broker_flate_fee" class="form-control" value="{{ old('landlord_broker_flate_fee', $data['landlord_broker_flate_fee'] ?? '') }}" placeholder="Enter flat fee amount (e.g., 5,000)"></div>
                            </div>
                            <div data-cp-parent="cp_interested_in_selling_type" data-cp-values="Other">
                                <input type="text" name="landlord_broker_other" class="form-control" value="{{ old('landlord_broker_other', $data['landlord_broker_other'] ?? '') }}" placeholder="Enter purchase fee structure (e.g., Tiered: 5% on the first $500,000, 3% on any amount above $500,000)">
                            </div>
                        </div>
                    </div>

                    {{-- Referral Fee (Landlord, agent-to-agent) --}}
                    <div class="mb-4">
                        <label class="form-label fw-bold">Referral Fee (%)</label>
                        <div class="input-group mt-2">
                            <input type="number"
                                   step="0.01"
                                   min="0"
                                   max="100"
                                   name="referral_fee_percent"
                                   class="form-control"
                                   value="{{ old('referral_fee_percent', $data['referral_fee_percent'] ?? '') }}"
                                   placeholder="Enter referral fee percentage (e.g., 25)">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>

                    {{-- Split Payment Due (Landlord) --}}
                    <div class="mb-4">
                        <label class="form-label fw-bold">Split Payment Due</label>
                        <div class="input-cover mt-2">
                            <select name="split_payment_due" class="form-control has-icon"
                                    data-icon="fa-solid fa-clock">
                                <option value="">Select</option>
                                <option value="Full amount upon execution of lease, sales contract, or other transfer agreement"
                                    @selected(old('split_payment_due', $data['split_payment_due'] ?? '') === 'Full amount upon execution of lease, sales contract, or other transfer agreement')>
                                    Full amount upon execution of lease, sales contract, or other transfer agreement
                                </option>
                                <option value="50% due upon execution, 50% due upon commencement of agreement"
                                    @selected(old('split_payment_due', $data['split_payment_due'] ?? '') === '50% due upon execution, 50% due upon commencement of agreement')>
                                    50% due upon execution, 50% due upon commencement of agreement
                                </option>
                                <option value="50% due upon execution, 50% due uponoccupancy of premises"
                                    @selected(old('split_payment_due', $data['split_payment_due'] ?? '') === '50% due upon execution, 50% due uponoccupancy of premises')>
                                    50% due upon execution, 50% due upon occupancy of premises
                                </option>
                                <option value="Other"
                                    @selected(old('split_payment_due', $data['split_payment_due'] ?? '') === 'Other')>
                                    Other
                                </option>
                            </select>
                        </div>
                    </div>

                    {{-- Split Payment Due — Other (Landlord) --}}
                    <div class="mb-4">
                        <label class="form-label fw-bold">If Other, Specify</label>
                        <input type="text"
                               name="split_payment_due_other"
                               class="form-control"
                               value="{{ old('split_payment_due_other', $data['split_payment_due_other'] ?? '') }}"
                               placeholder="Describe custom payment timing">
                    </div>
                @endif

                @if ($role === 'tenant')
                    {{-- Tenant's Broker Commission Structure --}}
                    <div class="mb-4">
                        <label class="fw-bold">Tenant's Broker Commission Structure</label>
                        <div class="input-cover mt-2">
                            <select id="cp_commission_structure" name="commission_structure" class="form-control has-icon"
                                    data-icon="fa-solid fa-file-invoice-dollar" onchange="_cpTrigger(this.id)">
                                <option value="">Select</option>
                                <option value="Out-of-Pocket Payment" @selected(old('commission_structure', $data['commission_structure'] ?? '') === 'Out-of-Pocket Payment')>Tenant Pays Out-of-Pocket</option>
                                <option value="Included in Offer" @selected(old('commission_structure', $data['commission_structure'] ?? '') === 'Included in Offer')>Requested From Landlord in the Offer</option>
                            </select>
                        </div>
                    </div>

                    {{-- Tenant's Broker Lease Fee --}}
                    <div class="mb-4">
                        <label class="fw-bold">Tenant's Broker Lease Fee</label>
                        <div class="input-cover mt-2">
                            <select id="cp_lease_fee_type" name="lease_fee_type" class="form-control has-icon"
                                    data-icon="fa-solid fa-file-invoice-dollar" onchange="_cpTrigger(this.id)">
                                <option value="">Select</option>
                                <option value="Flat Fee" @selected(old('lease_fee_type', $data['lease_fee_type'] ?? '') === 'Flat Fee')>Flat Fee</option>
                                @if ($propertyType === 'residential')
                                    <option value="Percentage of Monthly Rent" @selected(old('lease_fee_type', $data['lease_fee_type'] ?? '') === 'Percentage of Monthly Rent')>Percentage of Monthly Rent</option>
                                    <option value="Percentage of the Gross Lease Value" @selected(old('lease_fee_type', $data['lease_fee_type'] ?? '') === 'Percentage of the Gross Lease Value')>Percentage of the Gross Lease Value</option>
                                    <option value="Flat Fee + Percentage of the Gross Lease Value" @selected(old('lease_fee_type', $data['lease_fee_type'] ?? '') === 'Flat Fee + Percentage of the Gross Lease Value')>Flat Fee + Percentage of the Gross Lease Value</option>
                                @elseif ($propertyType === 'commercial')
                                    <option value="Percentage of the Net Aggregate Rent" @selected(old('lease_fee_type', $data['lease_fee_type'] ?? '') === 'Percentage of the Net Aggregate Rent')>Percentage of the Net Aggregate Rent</option>
                                    <option value="Flat Fee + Percentage of the Net Aggregate Rent" @selected(old('lease_fee_type', $data['lease_fee_type'] ?? '') === 'Flat Fee + Percentage of the Net Aggregate Rent')>Flat Fee + Percentage of the Net Aggregate Rent</option>
                                @endif
                                <option value="other" @selected(old('lease_fee_type', $data['lease_fee_type'] ?? '') === 'other')>Other</option>
                            </select>
                        </div>
                        <div class="mt-2">
                            <div data-cp-parent="cp_lease_fee_type" data-cp-values="Flat Fee">
                                <div class="input-group"><span class="input-group-text">$</span><input type="text" name="lease_fee_flat" class="form-control" value="{{ old('lease_fee_flat', $data['lease_fee_flat'] ?? '') }}" placeholder="Enter flat fee amount (e.g., 5,000)"></div>
                            </div>
                            @if ($propertyType === 'residential')
                            <div data-cp-parent="cp_lease_fee_type" data-cp-values="Percentage of Monthly Rent">
                                <div class="input-group"><input type="number" name="lease_fee_percentage_monthly_rent" class="form-control" value="{{ old('lease_fee_percentage_monthly_rent', $data['lease_fee_percentage_monthly_rent'] ?? '') }}" placeholder="Enter percentage of monthly rent (e.g., 100)"><span class="input-group-text">%</span></div>
                            </div>
                            <div data-cp-parent="cp_lease_fee_type" data-cp-values="Percentage of the Gross Lease Value">
                                <div class="input-group"><input type="number" name="lease_fee_percentage" class="form-control" value="{{ old('lease_fee_percentage', $data['lease_fee_percentage'] ?? '') }}" placeholder="Enter percentage of the gross lease value (e.g., 10)"><span class="input-group-text">%</span></div>
                            </div>
                            <div data-cp-parent="cp_lease_fee_type" data-cp-values="Flat Fee + Percentage of the Gross Lease Value">
                                <div class="row g-2"><div class="col-md-5"><div class="input-group"><span class="input-group-text">$</span><input type="text" name="lease_fee_flat_combo" class="form-control" value="{{ old('lease_fee_flat_combo', $data['lease_fee_flat_combo'] ?? '') }}" placeholder="Flat fee (e.g., 1,000)"></div></div><div class="col-md-1 text-center pt-2">+</div><div class="col-md-6"><div class="input-group"><input type="number" name="lease_fee_percentage_combo" class="form-control" value="{{ old('lease_fee_percentage_combo', $data['lease_fee_percentage_combo'] ?? '') }}" placeholder="% gross lease value (e.g., 7)"><span class="input-group-text">%</span></div></div></div>
                            </div>
                            @elseif ($propertyType === 'commercial')
                            <div data-cp-parent="cp_lease_fee_type" data-cp-values="Percentage of the Net Aggregate Rent">
                                <div class="input-group"><input type="number" name="lease_fee_percentage_net" class="form-control" value="{{ old('lease_fee_percentage_net', $data['lease_fee_percentage_net'] ?? '') }}" placeholder="Enter percentage of the net aggregate rent (e.g., 6)"><span class="input-group-text">%</span></div>
                            </div>
                            <div data-cp-parent="cp_lease_fee_type" data-cp-values="Flat Fee + Percentage of the Net Aggregate Rent">
                                <div class="row g-2"><div class="col-md-5"><div class="input-group"><span class="input-group-text">$</span><input type="text" name="lease_fee_flat_combo_net" class="form-control" value="{{ old('lease_fee_flat_combo_net', $data['lease_fee_flat_combo_net'] ?? '') }}" placeholder="Flat fee (e.g., 1,500)"></div></div><div class="col-md-1 text-center pt-2">+</div><div class="col-md-6"><div class="input-group"><input type="number" name="lease_fee_percentage_combo_net" class="form-control" value="{{ old('lease_fee_percentage_combo_net', $data['lease_fee_percentage_combo_net'] ?? '') }}" placeholder="% net aggregate rent (e.g., 6)"><span class="input-group-text">%</span></div></div></div>
                            </div>
                            @endif
                            <div data-cp-parent="cp_lease_fee_type" data-cp-values="other">
                                <input type="text" name="lease_fee_other" class="form-control" value="{{ old('lease_fee_other', $data['lease_fee_other'] ?? '') }}" placeholder="Enter the total lease fee amount and payment structure">
                            </div>
                        </div>
                    </div>

                    {{-- Payment Timing for Broker Fees (Tenant, residential only) --}}
                    @if ($propertyType === 'residential')
                    <div class="mb-4">
                        <label class="fw-bold">Payment Timing for Broker Fees</label>
                        <div class="input-cover mt-2">
                            <select id="cp_broker_fee_timing" name="broker_fee_timing" class="form-control has-icon"
                                    data-icon="fa-solid fa-clock" onchange="_cpTrigger(this.id)">
                                <option value="">Select</option>
                                <option value="Deducted from Rent Collected" @selected(old('broker_fee_timing', $data['broker_fee_timing'] ?? '') === 'Deducted from Rent Collected')>Deducted from Rent Collected</option>
                                <option value="Paid Within Calendar Days After Executed Lease" @selected(old('broker_fee_timing', $data['broker_fee_timing'] ?? '') === 'Paid Within Calendar Days After Executed Lease')>Paid Within Calendar Days After Executed Lease</option>
                                <option value="Paid Within Calendar Days of Tenant Rent Payment" @selected(old('broker_fee_timing', $data['broker_fee_timing'] ?? '') === 'Paid Within Calendar Days of Tenant Rent Payment')>Paid Within Calendar Days of Tenant Rent Payment</option>
                                <option value="other" @selected(old('broker_fee_timing', $data['broker_fee_timing'] ?? '') === 'other')>Other</option>
                            </select>
                        </div>
                        <div class="mt-2">
                            <div data-cp-parent="cp_broker_fee_timing" data-cp-values="Deducted from Rent Collected">
                                <div class="input-group"><span class="input-group-text">#</span><input type="number" name="broker_fee_days_from_rent" class="form-control" value="{{ old('broker_fee_days_from_rent', $data['broker_fee_days_from_rent'] ?? '') }}" placeholder="Enter number of calendar days (e.g., 5)"></div>
                            </div>
                            <div data-cp-parent="cp_broker_fee_timing" data-cp-values="Paid Within Calendar Days After Executed Lease">
                                <div class="input-group"><span class="input-group-text">#</span><input type="number" name="broker_fee_days_after_lease" class="form-control" value="{{ old('broker_fee_days_after_lease', $data['broker_fee_days_after_lease'] ?? '') }}" placeholder="Enter number of calendar days (e.g., 5)"></div>
                            </div>
                            <div data-cp-parent="cp_broker_fee_timing" data-cp-values="Paid Within Calendar Days of Tenant Rent Payment">
                                <div class="input-group"><span class="input-group-text">#</span><input type="number" name="broker_fee_days_after_rent" class="form-control" value="{{ old('broker_fee_days_after_rent', $data['broker_fee_days_after_rent'] ?? '') }}" placeholder="Enter number of calendar days (e.g., 5)"></div>
                            </div>
                            <div data-cp-parent="cp_broker_fee_timing" data-cp-values="other">
                                <input type="text" name="broker_fee_timing_other" class="form-control" value="{{ old('broker_fee_timing_other', $data['broker_fee_timing_other'] ?? '') }}" placeholder="Describe payment arrangement">
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Payment Timing for Broker Fees (Tenant, commercial) --}}
                    @if ($propertyType === 'commercial')
                    <div class="mb-4">
                        <label class="fw-bold">Payment Timing for Broker Fees</label>
                        <div class="input-cover mt-2">
                            <select id="cp_broker_fee_timing" name="broker_fee_timing" class="form-control has-icon"
                                    data-icon="fa-solid fa-clock" onchange="_cpTrigger(this.id)">
                                <option value="">Select</option>
                                <option value="full_execution" @selected(old('broker_fee_timing', $data['broker_fee_timing'] ?? '') === 'full_execution')>Full amount upon execution of lease, sales contract, or other transfer agreement</option>
                                <option value="half_execution_half_commencement" @selected(old('broker_fee_timing', $data['broker_fee_timing'] ?? '') === 'half_execution_half_commencement')>50% due upon execution, 50% due upon commencement of agreement</option>
                                <option value="half_execution_half_occupancy" @selected(old('broker_fee_timing', $data['broker_fee_timing'] ?? '') === 'half_execution_half_occupancy')>50% due upon execution, 50% due upon occupancy of premises</option>
                                <option value="other" @selected(old('broker_fee_timing', $data['broker_fee_timing'] ?? '') === 'other')>Other</option>
                            </select>
                        </div>
                        <div data-cp-parent="cp_broker_fee_timing" data-cp-values="other" class="mt-2">
                            <input type="text" name="broker_fee_timing_other" class="form-control" value="{{ old('broker_fee_timing_other', $data['broker_fee_timing_other'] ?? '') }}" placeholder="Describe payment arrangement">
                        </div>
                    </div>
                    @endif

                    {{-- Tenant: Interested in Purchasing a Property --}}
                    <div class="mb-2 mt-4">
                        <label class="fw-bold">Interested in Purchasing a Property</label>
                        <div class="input-cover mt-2">
                            <select id="cp_interested_purchase_fee_type" name="interested_purchase_fee_type"
                                    class="form-control has-icon" data-icon="fa-solid fa-file-invoice-dollar"
                                    onchange="_cpTrigger(this.id)">
                                <option value="">Select</option>
                                <option value="Yes" @selected(old('interested_purchase_fee_type', $data['interested_purchase_fee_type'] ?? '') === 'Yes')>Yes</option>
                                <option value="No" @selected(old('interested_purchase_fee_type', $data['interested_purchase_fee_type'] ?? '') === 'No')>No</option>
                            </select>
                        </div>
                    </div>
                    <div data-cp-parent="cp_interested_purchase_fee_type" data-cp-values="Yes"
                         class="mb-4 ps-3 border-start border-2 border-secondary">
                        <label class="fw-bold mt-2">Tenant's Broker Purchase Fee</label>
                        <div class="input-cover mt-2">
                            <select id="cp_purchase_fee_type_tenant" name="purchase_fee_type"
                                    class="form-control has-icon" data-icon="fa-solid fa-file-invoice-dollar"
                                    onchange="_cpTrigger(this.id)">
                                <option value="">Select</option>
                                <option value="Flat Fee" @selected(old('purchase_fee_type', $data['purchase_fee_type'] ?? '') === 'Flat Fee')>Flat Fee</option>
                                <option value="Percentage of the Total Purchase Price" @selected(old('purchase_fee_type', $data['purchase_fee_type'] ?? '') === 'Percentage of the Total Purchase Price')>Percentage of the Total Purchase Price</option>
                                <option value="Percentage of the Total Purchase Price + Flat Fee" @selected(old('purchase_fee_type', $data['purchase_fee_type'] ?? '') === 'Percentage of the Total Purchase Price + Flat Fee')>Percentage of the Total Purchase Price + Flat Fee</option>
                                <option value="other" @selected(old('purchase_fee_type', $data['purchase_fee_type'] ?? '') === 'other')>Other</option>
                            </select>
                        </div>
                        <div class="mt-2">
                            <div data-cp-parent="cp_purchase_fee_type_tenant" data-cp-values="Flat Fee">
                                <div class="input-group"><span class="input-group-text">$</span><input type="text" name="purchase_fee_flat" class="form-control" value="{{ old('purchase_fee_flat', $data['purchase_fee_flat'] ?? '') }}" placeholder="Enter flat fee amount (e.g., 5,000)"></div>
                            </div>
                            <div data-cp-parent="cp_purchase_fee_type_tenant" data-cp-values="Percentage of the Total Purchase Price">
                                <div class="input-group"><input type="number" name="purchase_fee_percentage" class="form-control" value="{{ old('purchase_fee_percentage', $data['purchase_fee_percentage'] ?? '') }}" placeholder="Enter percentage of total purchase price (e.g., 3)"><span class="input-group-text">%</span></div>
                            </div>
                            <div data-cp-parent="cp_purchase_fee_type_tenant" data-cp-values="Percentage of the Total Purchase Price + Flat Fee">
                                <div class="row g-2"><div class="col-md-6"><div class="input-group"><input type="number" name="purchase_fee_percentage_combo" class="form-control" value="{{ old('purchase_fee_percentage_combo', $data['purchase_fee_percentage_combo'] ?? '') }}" placeholder="Enter % of total purchase price (e.g., 3)"><span class="input-group-text">%</span></div></div><div class="col-md-1 text-center pt-2">+</div><div class="col-md-5"><div class="input-group"><span class="input-group-text">$</span><input type="text" name="purchase_fee_flat_combo" class="form-control" value="{{ old('purchase_fee_flat_combo', $data['purchase_fee_flat_combo'] ?? '') }}" placeholder="Flat fee (e.g., 2,000)"></div></div></div>
                            </div>
                            <div data-cp-parent="cp_purchase_fee_type_tenant" data-cp-values="other">
                                <input type="text" name="purchase_fee_other" class="form-control" value="{{ old('purchase_fee_other', $data['purchase_fee_other'] ?? '') }}" placeholder="Enter purchase fee structure (e.g., 3% if sale price is under $500,000, 2% above)">
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Interested in a Lease-Option Agreement (all roles) --}}
                <div class="mb-2">
                    <label class="fw-bold">Interested in a Lease-Option Agreement</label>
                    <div class="input-cover mt-2">
                        <select id="cp_interested_lease_option_agreement" name="interested_lease_option_agreement"
                                class="form-control has-icon" data-icon="fa-solid fa-file-invoice-dollar"
                                onchange="_cpTrigger(this.id)">
                            <option value="">Select</option>
                            <option value="Yes" @selected(old('interested_lease_option_agreement', $data['interested_lease_option_agreement'] ?? '') === 'Yes')>Yes</option>
                            <option value="No" @selected(old('interested_lease_option_agreement', $data['interested_lease_option_agreement'] ?? '') === 'No')>No</option>
                        </select>
                    </div>
                </div>
                <div data-cp-parent="cp_interested_lease_option_agreement" data-cp-values="Yes"
                     class="mb-4 ps-3 border-start border-2 border-secondary">
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Compensation for Creating the Lease-Option Agreement</label>
                            <div class="input-group">
                                <select name="lease_type" class="form-select" style="max-width:80px">
                                    <option value="percent" @selected(old('lease_type', $data['lease_type'] ?? 'percent') === 'percent')>%</option>
                                    <option value="flat" @selected(old('lease_type', $data['lease_type'] ?? '') === 'flat')>$</option>
                                </select>
                                <input type="text" name="lease_value" class="form-control" value="{{ old('lease_value', $data['lease_value'] ?? '') }}" placeholder="Enter amount (e.g., 5 or 1,500)">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Compensation if Purchase Option is Exercised</label>
                            <div class="input-group">
                                <select name="purchase_type" class="form-select" style="max-width:80px">
                                    <option value="percent" @selected(old('purchase_type', $data['purchase_type'] ?? 'percent') === 'percent')>%</option>
                                    <option value="flat" @selected(old('purchase_type', $data['purchase_type'] ?? '') === 'flat')>$</option>
                                </select>
                                <input type="text" name="purchase_value" class="form-control" value="{{ old('purchase_value', $data['purchase_value'] ?? '') }}" placeholder="Enter amount (e.g., 6 or 5,000)">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Protection Period --}}
                <div class="mb-4">
                    <label class="fw-bold">Protection Period Timeframe (Days)</label>
                    <div class="input-cover mt-2">
                        <input type="number" name="protection_period" class="form-control has-icon"
                               data-icon="fa-solid fa-shield-alt"
                               value="{{ old('protection_period', $data['protection_period'] ?? '') }}"
                               placeholder="Enter protection period in days (e.g., 90)">
                    </div>
                </div>

                {{-- Early Termination Fee --}}
                @php
                    $etfYesVal = ($role === 'tenant') ? 'Yes' : 'yes';
                    $etfNoVal  = ($role === 'tenant') ? 'No'  : 'no';
                    $etfCur    = old('early_termination_fee_option', $data['early_termination_fee_option'] ?? '');
                @endphp
                <div class="mb-2">
                    <label class="fw-bold">Early Termination Fee</label>
                    <div class="input-cover mt-2">
                        <select id="cp_early_termination_fee_option" name="early_termination_fee_option"
                                class="form-control has-icon" data-icon="fa-solid fa-ban"
                                onchange="_cpTrigger(this.id)">
                            <option value="">Select</option>
                            <option value="{{ $etfYesVal }}" @selected($etfCur === $etfYesVal)>Yes</option>
                            <option value="{{ $etfNoVal }}" @selected($etfCur === $etfNoVal)>No</option>
                        </select>
                    </div>
                </div>
                <div data-cp-parent="cp_early_termination_fee_option" data-cp-values="{{ $etfYesVal }}" class="mb-4">
                    <div class="input-group mt-2"><span class="input-group-text">$</span>
                        <input type="text" name="early_termination_fee_amount" class="form-control"
                               value="{{ old('early_termination_fee_amount', $data['early_termination_fee_amount'] ?? '') }}"
                               placeholder="Enter early termination fee amount (e.g., 2,000)">
                    </div>
                </div>

                {{-- Seller: Retained Deposits --}}
                @if ($role === 'seller')
                <div class="mb-4">
                    <label class="fw-bold">Seller's Broker's Share of Retained Deposits (%)</label>
                    <div class="input-group mt-2">
                        <input type="number" name="retained_deposits" class="form-control"
                               value="{{ old('retained_deposits', $data['retained_deposits'] ?? '') }}"
                               placeholder="Enter percentage of retained deposit if Buyer defaults (e.g., 50)">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                @endif

                {{-- Retainer Fee (buyer, seller, tenant only) --}}
                @if (in_array($role, ['buyer', 'seller', 'tenant']))
                @php
                    $rtfYesVal = ($role === 'tenant') ? 'Yes' : 'yes';
                    $rtfNoVal  = ($role === 'tenant') ? 'No'  : 'no';
                    $rtfCur    = old('retainer_fee_option', $data['retainer_fee_option'] ?? '');
                @endphp
                <div class="mb-2">
                    <label class="fw-bold">Retainer Fee</label>
                    <div class="input-cover mt-2">
                        <select id="cp_retainer_fee_option" name="retainer_fee_option"
                                class="form-control has-icon" data-icon="fa-solid fa-receipt"
                                onchange="_cpTrigger(this.id)">
                            <option value="">Select</option>
                            <option value="{{ $rtfYesVal }}" @selected($rtfCur === $rtfYesVal)>Yes</option>
                            <option value="{{ $rtfNoVal }}" @selected($rtfCur === $rtfNoVal)>No</option>
                        </select>
                    </div>
                </div>
                <div data-cp-parent="cp_retainer_fee_option" data-cp-values="{{ $rtfYesVal }}" class="mb-4 ps-3 border-start border-2 border-secondary">
                    <div class="input-group mt-2"><span class="input-group-text">$</span>
                        <input type="text" name="retainer_fee_amount" class="form-control"
                               value="{{ old('retainer_fee_amount', $data['retainer_fee_amount'] ?? '') }}"
                               placeholder="Enter retainer fee amount (e.g., 500)">
                    </div>
                    <div class="input-cover mt-2">
                        <select name="retainer_fee_application" class="form-control has-icon" data-icon="fa-solid fa-check-circle">
                            <option value="">Select application</option>
                            @if ($role === 'tenant')
                                <option value="applied" {{ old('retainer_fee_application', $data['retainer_fee_application'] ?? '') == 'applied' ? 'selected' : '' }}>
                                    Applied toward final compensation
                                </option>
                                <option value="additional" {{ old('retainer_fee_application', $data['retainer_fee_application'] ?? '') == 'additional' ? 'selected' : '' }}>
                                    Charged in addition to final compensation
                                </option>
                            @else
                                <option value="Applied toward final compensation" {{ old('retainer_fee_application', $data['retainer_fee_application'] ?? '') == 'Applied toward final compensation' ? 'selected' : '' }}>
                                    Applied toward final compensation
                                </option>
                                <option value="Charged in addition to final compensation" {{ old('retainer_fee_application', $data['retainer_fee_application'] ?? '') == 'Charged in addition to final compensation' ? 'selected' : '' }}>
                                    Charged in addition to final compensation
                                </option>
                            @endif
                        </select>
                    </div>
                </div>
                @endif

                {{-- Brokerage Relationship (all roles) --}}
                <div class="mb-4">
                    <label class="fw-bold">Acceptable Brokerage Relationship</label>
                    <div class="input-cover mt-2">
                        <select name="brokerage_relationship" class="form-control has-icon" data-icon="fa-solid fa-handshake">
                            <option value="">Select</option>
                            <option value="Transaction Broker Representation" @selected(old('brokerage_relationship', $data['brokerage_relationship'] ?? '') === 'Transaction Broker Representation')>Transaction Broker Representation</option>
                            <option value="Single Agent Representation" @selected(old('brokerage_relationship', $data['brokerage_relationship'] ?? '') === 'Single Agent Representation')>Single Agent Representation</option>
                            <option value="Dual Agency Representation" @selected(old('brokerage_relationship', $data['brokerage_relationship'] ?? '') === 'Dual Agency Representation')>Dual Agency Representation</option>
                            <option value="No Brokerage Relationship" @selected(old('brokerage_relationship', $data['brokerage_relationship'] ?? '') === 'No Brokerage Relationship')>No Brokerage Relationship</option>
                        </select>
                    </div>
                </div>

                {{-- Agency Agreement Timeframe --}}
                @php
                    $aatCustomVal = ($role === 'buyer') ? 'custom' : 'Other';
                    $aatCur       = old('agency_agreement_timeframe', $data['agency_agreement_timeframe'] ?? '');
                @endphp
                <div class="mb-2">
                    <label class="fw-bold">Agency Agreement Timeframe</label>
                    <div class="input-cover mt-2">
                        <select id="cp_agency_agreement_timeframe" name="agency_agreement_timeframe"
                                class="form-control has-icon" data-icon="fa-solid fa-calendar"
                                onchange="_cpTrigger(this.id)">
                            <option value="">Select</option>
                            <option value="3 Months" @selected($aatCur === '3 Months')>3 Months</option>
                            <option value="6 Months" @selected($aatCur === '6 Months')>6 Months</option>
                            <option value="9 Months" @selected($aatCur === '9 Months')>9 Months</option>
                            <option value="12 Months" @selected($aatCur === '12 Months')>12 Months</option>
                            <option value="{{ $aatCustomVal }}" @selected($aatCur === $aatCustomVal)>Other (Custom)</option>
                        </select>
                    </div>
                </div>
                <div data-cp-parent="cp_agency_agreement_timeframe" data-cp-values="{{ $aatCustomVal }}" class="mb-4">
                    <input type="text" name="agency_agreement_custom" class="form-control mt-2"
                           value="{{ old('agency_agreement_custom', $data['agency_agreement_custom'] ?? '') }}"
                           placeholder="Enter custom timeframe (e.g., 18 Months)">
                </div>

                {{-- Additional Terms (all roles) --}}
                <div class="mb-4">
                    <label class="fw-bold">Additional Terms</label>
                    <textarea name="additional_details_broker" class="form-control mt-2" rows="3"
                              placeholder="Enter any additional compensation terms, conditions, or agreements not covered above">{{ old('additional_details_broker', $data['additional_details_broker'] ?? '') }}</textarea>
                </div>

            </div>
        </div>

        {{-- ── STICKY SAVE BAR ──────────────────────────────────────────────── --}}
        <div class="sticky-save-bar">
            <a href="{{ route('agent.presets.index') }}" class="btn btn-outline-secondary">
                <i class="fa fa-times me-1"></i>Cancel
            </a>
            <button type="submit" class="btn btn-save-preset">
                <i class="fa fa-save me-1"></i>Save Preset
            </button>
        </div>

    </form>
</div>
@endsection

@push('scripts')
<script>
    // ── Compensation sub-field show/hide ──────────────────────────────────────
    function _cpTrigger(selectId) {
        var sel = document.getElementById(selectId);
        if (!sel) return;
        var val = sel.value;
        document.querySelectorAll('[data-cp-parent="' + selectId + '"]').forEach(function (div) {
            var allowed = div.getAttribute('data-cp-values').split('|');
            div.style.display = (allowed.indexOf(val) !== -1) ? '' : 'none';
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Initialise all compensation dependent divs on page load
        document.querySelectorAll('[data-cp-parent]').forEach(function (div) {
            var parentId = div.getAttribute('data-cp-parent');
            var parentEl = document.getElementById(parentId);
            if (!parentEl) { div.style.display = 'none'; return; }
            var allowed = div.getAttribute('data-cp-values').split('|');
            div.style.display = (allowed.indexOf(parentEl.value) !== -1) ? '' : 'none';
        });
    });
</script>
<script>
(function () {

    // ── Custom preset-section accordion (no Bootstrap collapse dependency) ──
    // Uses data-preset-toggle="<sectionId>" on headers and
    // .preset-section-body[id] + .preset-closed on bodies.
    // This is completely isolated from Bootstrap's collapse system and Alpine.js.

    function presetToggle(headEl) {
        var targetId = headEl.getAttribute('data-preset-toggle');
        if (!targetId) return;
        var body = document.getElementById(targetId);
        if (!body) return;
        var isOpen = headEl.getAttribute('aria-expanded') === 'true';
        if (isOpen) {
            body.classList.add('preset-closed');
            headEl.setAttribute('aria-expanded', 'false');
        } else {
            body.classList.remove('preset-closed');
            headEl.setAttribute('aria-expanded', 'true');
        }
    }

    document.querySelectorAll('[data-preset-toggle]').forEach(function (hdr) {
        hdr.addEventListener('click', function () { presetToggle(hdr); });
    });

    // ── Service checkbox: visual checked state ────────────────────────────
    var updateServiceItem = function (checkbox) {
        var label = checkbox.closest('.service-item');
        if (label) label.classList.toggle('checked', checkbox.checked);
    };

    var updateCount = function () {
        var total = document.querySelectorAll('#services-grid input[type="checkbox"]:checked').length;
        document.querySelectorAll('.selected-count').forEach(function (el) { el.textContent = total; });
    };

    document.querySelectorAll('#services-grid input[type="checkbox"]').forEach(function (cb) {
        cb.addEventListener('change', function () { updateServiceItem(cb); updateCount(); });
    });

    // ── Select All / Clear All buttons ───────────────────────────────────
    document.querySelectorAll('.btn-select-all').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#services-grid input[type="checkbox"]').forEach(function (cb) {
                cb.checked = true;
                updateServiceItem(cb);
            });
            updateCount();
        });
    });

    document.querySelectorAll('.btn-clear-all').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#services-grid input[type="checkbox"]').forEach(function (cb) {
                cb.checked = false;
                updateServiceItem(cb);
            });
            updateCount();
        });
    });

    // ── Auto-open sections that contain validation errors ─────────────────
    @if ($errors->any())
    (function () {
        var totalInvalid = document.querySelectorAll('#preset-form .is-invalid').length;
        if (totalInvalid > 0) {
            var banner = document.getElementById('preset-error-summary');
            var bannerText = document.getElementById('preset-error-summary-text');
            if (banner && bannerText) {
                bannerText.textContent = 'Fix ' + totalInvalid + ' error' + (totalInvalid !== 1 ? 's' : '') + ' before saving.';
                banner.classList.remove('d-none');
            }
        }
    })();
    document.querySelectorAll('.preset-section-body').forEach(function (body) {
        if (body.querySelector('.is-invalid')) {
            body.classList.remove('preset-closed');
            var hdr = document.querySelector('[data-preset-toggle="' + body.id + '"]');
            if (hdr) {
                hdr.setAttribute('aria-expanded', 'true');
                hdr.classList.add('has-errors');
                if (!hdr.querySelector('.section-error-badge')) {
                    var errorCount = body.querySelectorAll('.is-invalid').length;
                    var badge = document.createElement('span');
                    badge.className = 'section-error-badge';
                    badge.setAttribute('aria-label', errorCount + ' error' + (errorCount !== 1 ? 's' : '') + ' in this section');
                    badge.textContent = errorCount;
                    var toggleIcon = hdr.querySelector('.toggle-icon');
                    if (toggleIcon) {
                        hdr.insertBefore(badge, toggleIcon);
                    } else {
                        hdr.appendChild(badge);
                    }
                }
            }
        }
    });
    @endif

    // ── Additional / Custom Services add & remove ─────────────────────────
    (function () {
        var list = document.getElementById('custom-services-list');
        var addBtn = document.getElementById('add-custom-service-btn');

        function makeRow() {
            var row = document.createElement('div');
            row.className = 'custom-service-row d-flex gap-2 mb-2';

            var inp = document.createElement('input');
            inp.type = 'text';
            inp.name = 'other_services[]';
            inp.className = 'form-control form-control-sm';
            inp.placeholder = 'Enter a custom service the Agent is willing to provide';
            inp.maxLength = 500;

            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-sm btn-outline-danger custom-service-remove flex-shrink-0';
            removeBtn.title = 'Remove this service';
            removeBtn.innerHTML = '<i class="fa fa-times"></i>';
            removeBtn.addEventListener('click', function () {
                row.parentNode.removeChild(row);
            });

            row.appendChild(inp);
            row.appendChild(removeBtn);
            return row;
        }

        if (addBtn && list) {
            addBtn.addEventListener('click', function () {
                var row = makeRow();
                list.appendChild(row);
                row.querySelector('input').focus();
            });

            list.addEventListener('click', function (e) {
                var btn = e.target.closest('.custom-service-remove');
                if (btn) {
                    var row = btn.closest('.custom-service-row');
                    if (row) { row.parentNode.removeChild(row); }
                }
            });
        }
    })();

    // ── Copy Hire Me Link button ──────────────────────────────────────────
    function fallbackCopy(text, callback) {
        var inp = document.createElement('input');
        inp.style.position = 'fixed';
        inp.style.opacity = '0';
        inp.value = text;
        document.body.appendChild(inp);
        inp.focus();
        inp.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(inp);
        callback();
    }

    document.querySelectorAll('.btn-copy-hire-edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = this.dataset.hireUrl;
            var self = this;
            var orig = self.innerHTML;

            function showCopied() {
                self.innerHTML = '<i class="fa fa-check me-1"></i>Copied!';
                self.classList.add('copied');
                setTimeout(function () {
                    self.innerHTML = orig;
                    self.classList.remove('copied');
                }, 2200);
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(showCopied).catch(function () {
                    fallbackCopy(url, showCopied);
                });
            } else {
                fallbackCopy(url, showCopied);
            }
        });
    });

})();
</script>
@endpush
