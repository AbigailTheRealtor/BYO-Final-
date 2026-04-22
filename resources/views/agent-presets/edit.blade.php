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
    .preset-section-body {
        padding: 1.25rem 1.4rem;
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
</style>
@endpush

@section('content')
<div class="preset-edit-wrap py-4 px-3">

    <div class="preset-header">
        <h1><i class="fa fa-sliders me-2"></i>Edit Preset: {{ $roleLabel }}</h1>
        <p>Property type: <strong>{{ $propertyLabel }}</strong> &nbsp;&middot;&nbsp; Changes save to your default profile and auto-fill future bids.</p>
    </div>

    <a href="{{ route('agent.presets.index') }}" class="btn btn-sm btn-outline-secondary mb-4">
        <i class="fa fa-arrow-left me-1"></i>Back to All Presets
    </a>

    @if ($errors->any())
        <div class="alert alert-danger mb-4">
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-1">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('agent.presets.save', [$role, $propertyType]) }}" id="preset-form">
        @csrf

        {{-- ── SERVICES ──────────────────────────────────────────────────────── --}}
        <div class="preset-section">
            <div class="preset-section-header"
                 data-bs-toggle="collapse"
                 data-bs-target="#section-services"
                 aria-expanded="true"
                 aria-controls="section-services">
                <i class="fa fa-list-ul section-icon"></i>
                Services
                <span class="section-req-badge req">Required</span>
                <i class="fa fa-chevron-down toggle-icon"></i>
            </div>
            <div class="collapse show" id="section-services">
                <div class="preset-section-body">
                    <p class="form-hint mb-3">Select the services you typically offer for this role and property type. These will be pre-selected when a client hires you directly.</p>

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
                </div>
            </div>
        </div>

        {{-- ── AGENT OVERVIEW ───────────────────────────────────────────────── --}}
        <div class="preset-section">
            <div class="preset-section-header"
                 data-bs-toggle="collapse"
                 data-bs-target="#section-overview"
                 aria-expanded="true"
                 aria-controls="section-overview">
                <i class="fa fa-user section-icon"></i>
                Agent Overview
                <span class="section-req-badge rec">Recommended</span>
                <i class="fa fa-chevron-down toggle-icon"></i>
            </div>
            <div class="collapse show" id="section-overview">
                <div class="preset-section-body">
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
        </div>

        {{-- ── AGENT CREDENTIALS ────────────────────────────────────────────── --}}
        <div class="preset-section">
            <div class="preset-section-header"
                 data-bs-toggle="collapse"
                 data-bs-target="#section-creds"
                 aria-expanded="false"
                 aria-controls="section-creds">
                <i class="fa fa-id-card-o section-icon"></i>
                Agent Credentials
                <i class="fa fa-chevron-down toggle-icon"></i>
            </div>
            <div class="collapse" id="section-creds">
                <div class="preset-section-body">
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
        </div>

        {{-- ── PRESENTATION & LINKS ─────────────────────────────────────────── --}}
        <div class="preset-section">
            <div class="preset-section-header"
                 data-bs-toggle="collapse"
                 data-bs-target="#section-links"
                 aria-expanded="false"
                 aria-controls="section-links">
                <i class="fa fa-link section-icon"></i>
                Presentation &amp; Links
                <i class="fa fa-chevron-down toggle-icon"></i>
            </div>
            <div class="collapse" id="section-links">
                <div class="preset-section-body">
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
document.addEventListener('DOMContentLoaded', function () {

    // ── Service checkbox: visual checked state ────────────────────────────
    const updateServiceItem = (checkbox) => {
        const label = checkbox.closest('.service-item');
        if (label) label.classList.toggle('checked', checkbox.checked);
    };

    const updateCount = () => {
        const total = document.querySelectorAll('#services-grid input[type="checkbox"]:checked').length;
        document.querySelectorAll('.selected-count').forEach(el => el.textContent = total);
    };

    document.querySelectorAll('#services-grid input[type="checkbox"]').forEach(cb => {
        cb.addEventListener('change', () => { updateServiceItem(cb); updateCount(); });
    });

    // ── Select All / Clear All buttons ───────────────────────────────────
    document.querySelectorAll('.btn-select-all').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('#services-grid input[type="checkbox"]').forEach(cb => {
                cb.checked = true;
                updateServiceItem(cb);
            });
            updateCount();
        });
    });

    document.querySelectorAll('.btn-clear-all').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('#services-grid input[type="checkbox"]').forEach(cb => {
                cb.checked = false;
                updateServiceItem(cb);
            });
            updateCount();
        });
    });

    // Bootstrap 5 updates aria-expanded automatically — no custom handler needed.
});
</script>
@endpush
