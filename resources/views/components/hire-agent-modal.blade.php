{{--
    x-hire-agent-modal
    Props:
      $listingId       (int)    — listing record ID
      $listingType     (string) — seller_offer / buyer_offer / landlord_offer / tenant_offer
      $listingRole     (string) — seller / buyer / landlord / tenant (derived from type if blank)
      $listingTitle    (string) — optional human-readable listing title for attribution
      $listingUrl      (string) — optional canonical URL of the listing page
      $prefillPropType (string) — optional property type pre-select from listing meta
      $modalId         (string) — unique modal ID (default: hireAgentModal)
--}}
@props([
    'listingId',
    'listingType',
    'listingRole'     => '',
    'listingTitle'    => '',
    'listingUrl'      => '',
    'prefillPropType' => '',
    'modalId'         => 'hireAgentModal',
])

@php
$repTypeOptions = [
    'buyer'    => "Buyer's Agent — I need help buying a property",
    'seller'   => "Seller's Agent — I need help selling a property",
    'landlord' => "Landlord's Agent — I need help renting out a property",
    'tenant'   => "Tenant's Agent — I need help finding a rental",
];

$propTypesByRole = [
    'buyer'    => ['residential' => 'Residential', 'income' => 'Income / Multi-family', 'commercial' => 'Commercial', 'business' => 'Business', 'vacant_land' => 'Vacant Land'],
    'seller'   => ['residential' => 'Residential', 'income' => 'Income / Multi-family', 'commercial' => 'Commercial', 'business' => 'Business', 'vacant_land' => 'Vacant Land'],
    'landlord' => ['residential' => 'Residential', 'commercial' => 'Commercial'],
    'tenant'   => ['residential' => 'Residential', 'commercial' => 'Commercial'],
];

$propTypesByRoleJson    = json_encode($propTypesByRole);
$prefillPropTypeEscaped = e($prefillPropType);
$listingTypeEscaped     = e($listingType);
$listingIdVal           = (int) $listingId;
$listingRoleVal         = e($listingRole ?: str_replace('_offer', '', $listingType));
$listingTitleVal        = e($listingTitle);
$listingUrlVal          = e($listingUrl ?: request()->url());
$storeUrl               = route('hire.agent.leads.store');
$matchUrl               = route('hire.agent.leads.match-presets');
$csrfToken              = csrf_token();
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1"
     aria-labelledby="{{ $modalId }}Label" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content" style="border-radius:.85rem;overflow:hidden;border:none;box-shadow:0 8px 40px rgba(0,0,0,.18);">

            {{-- Header --}}
            <div class="modal-header" style="background:linear-gradient(135deg,#0f766e,#0369a1);color:#fff;border:none;padding:1.1rem 1.4rem;">
                <h5 class="modal-title fw-bold mb-0" id="{{ $modalId }}Label" style="font-size:1rem;">
                    <i class="fa-solid fa-user-tie me-2"></i>Find a Real Estate Agent
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1);" aria-label="Close"></button>
            </div>

            {{-- Alpine wizard --}}
            <div class="modal-body p-0"
                 x-data="hireAgentWizard_{{ $modalId }}()"
                 x-init="init()">

                {{-- Progress bar (3 visible steps) --}}
                <div style="height:3px;background:#e2e8f0;">
                    <div style="height:3px;background:linear-gradient(90deg,#0f766e,#0369a1);transition:width .3s;"
                         :style="'width:' + (step === 1 ? '25' : step === 2 ? '50' : step === 3 ? '75' : '100') + '%'"></div>
                </div>

                <div style="padding:1.4rem 1.5rem;">

                    {{-- ── STEP 1: Representation type ───────────────────── --}}
                    <div x-show="step === 1" x-transition:enter="hal-fade-in">
                        <p class="fw-semibold mb-3" style="font-size:.9rem;color:#1e293b;">
                            What type of agent do you need?
                        </p>
                        <div class="d-flex flex-column gap-2">
                            @foreach($repTypeOptions as $key => $label)
                            <label class="d-flex align-items-start gap-3 p-3 rounded-3 border"
                                   style="cursor:pointer;transition:border-color .15s,background .15s;"
                                   :class="representationType === '{{ $key }}' ? 'border-primary bg-primary bg-opacity-10' : 'border-light-subtle'">
                                <input type="radio" name="hal_rep_type_{{ $modalId }}" value="{{ $key }}"
                                       x-model="representationType" class="form-check-input mt-0 flex-shrink-0">
                                <div>
                                    <div class="fw-semibold" style="font-size:.85rem;color:#1e293b;">
                                        {{ Str::before($label, ' — ') }}
                                    </div>
                                    <div style="font-size:.76rem;color:#64748b;margin-top:1px;">
                                        {{ Str::after($label, ' — ') }}
                                    </div>
                                </div>
                            </label>
                            @endforeach
                        </div>
                        <div x-show="errors.representationType" class="text-danger mt-2" style="font-size:.78rem;" x-text="errors.representationType"></div>

                        <div class="d-flex justify-content-end mt-4">
                            <button type="button" class="btn btn-primary px-4"
                                    style="background:linear-gradient(135deg,#0f766e,#0369a1);border:none;"
                                    @click="goToStep2()">
                                Next <i class="fa-solid fa-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>

                    {{-- ── STEP 2: Property type ─────────────────────────── --}}
                    <div x-show="step === 2" x-transition:enter="hal-fade-in">
                        <p class="fw-semibold mb-3" style="font-size:.9rem;color:#1e293b;">
                            What type of property?
                        </p>

                        <div class="d-flex flex-column gap-2" x-show="propertyTypes.length > 0 || Object.keys(propertyTypes).length > 0">
                            <template x-for="(label, key) in propertyTypes" :key="key">
                                <label class="d-flex align-items-center gap-3 p-3 rounded-3 border"
                                       style="cursor:pointer;transition:border-color .15s,background .15s;"
                                       :class="selectedPropertyType === key ? 'border-primary bg-primary bg-opacity-10' : 'border-light-subtle'">
                                    <input type="radio" :name="'hal_prop_type_{{ $modalId }}'" :value="key"
                                           x-model="selectedPropertyType" class="form-check-input mt-0 flex-shrink-0">
                                    <span class="fw-semibold" style="font-size:.85rem;color:#1e293b;" x-text="label"></span>
                                </label>
                            </template>
                        </div>
                        <div x-show="errors.selectedPropertyType" class="text-danger mt-2" style="font-size:.78rem;" x-text="errors.selectedPropertyType"></div>

                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-outline-secondary px-3" @click="step = 1">
                                <i class="fa-solid fa-arrow-left me-1"></i> Back
                            </button>
                            <button type="button" class="btn btn-primary px-4"
                                    style="background:linear-gradient(135deg,#0f766e,#0369a1);border:none;"
                                    @click="goToStep3()"
                                    :disabled="matching">
                                <span x-show="!matching">Next <i class="fa-solid fa-arrow-right ms-1"></i></span>
                                <span x-show="matching"><i class="fa-solid fa-spinner fa-spin me-1"></i>Matching…</span>
                            </button>
                        </div>
                    </div>

                    {{-- ── STEP 3: No-preset fallback OR Contact form ── --}}
                    <div x-show="step === 3" x-transition:enter="hal-fade-in">

                        {{-- No-preset fallback: agent exists but hasn't configured a preset for this role/property type --}}
                        <template x-if="noPresetMessage && !showContactForm">
                            <div>
                                <div class="alert alert-warning mb-3 py-3 px-3" style="font-size:.85rem;border-radius:.7rem;">
                                    <div class="fw-semibold mb-1">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                                        No preset configured for this role
                                    </div>
                                    <div style="color:#6b5a00;">
                                        The agent on this listing hasn't set up a
                                        <strong x-text="representationTypeLabel"></strong> /
                                        <strong x-text="selectedPropertyTypeLabel"></strong>
                                        profile yet. You can still send them a general inquiry and they'll get back to you.
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between mt-2">
                                    <button type="button" class="btn btn-outline-secondary px-3" @click="step = 2">
                                        <i class="fa-solid fa-arrow-left me-1"></i> Back
                                    </button>
                                    <button type="button" class="btn btn-primary px-4 fw-semibold"
                                            style="background:linear-gradient(135deg,#0f766e,#0369a1);border:none;"
                                            @click="showContactForm = true">
                                        <i class="fa-solid fa-envelope me-1"></i>Send General Inquiry
                                    </button>
                                </div>
                            </div>
                        </template>

                        {{--
                            Contact form: shown only when showContactForm is explicitly true.
                            showContactForm is set true by goToStep3() for all non-redirect, non-no_preset
                            fallback reasons (no_agent, no_listing, no_short_id, network error),
                            and by the "Send General Inquiry" button in the no-preset block above.
                        --}}
                        <template x-if="showContactForm">
                            <div>
                                {{-- No-match context banner (no_agent / no_listing / error paths) --}}
                                <template x-if="matchStatus === 'no_match'">
                                    <div class="alert alert-secondary mb-3 py-2 px-3" style="font-size:.82rem;border-radius:.6rem;">
                                        <i class="fa-solid fa-info-circle me-1"></i>
                                        No exact agent match found yet. Fill in your details and we'll connect you with an agent.
                                    </div>
                                </template>

                                {{--
                                    matchStatus === 'matched' banner removed:
                                    A listing-context match (hired agent + preset) now always issues a redirect;
                                    the contact form is never shown in the matched state for listing flows.

                                    matchStatus === 'multiple_matches' is retained for forward-compatibility
                                    with a future marketplace/global-search flow where a non-listing entry
                                    point (no source_listing_id) could surface multiple available agents.
                                    In listing-first routing this path is unreachable today because the
                                    endpoint returns action='redirect' or action='contact_form' (never
                                    multiple_matches as an action outcome).
                                --}}
                                <template x-if="matchStatus === 'multiple_matches'">
                                    <div class="mb-3">
                                        <p class="fw-semibold mb-2" style="font-size:.88rem;color:#1e293b;">
                                            <i class="fa-solid fa-users me-1 text-primary"></i>
                                            <span x-text="matchPresets.length"></span> agents available — choose one:
                                        </p>
                                        <div class="d-flex flex-column gap-2">
                                            <template x-for="(preset, idx) in matchPresets" :key="preset.preset_id">
                                                <label class="d-flex align-items-center gap-3 p-3 rounded-3 border"
                                                       style="cursor:pointer;transition:border-color .15s,background .15s;"
                                                       :class="selectedPresetId === preset.preset_id ? 'border-primary bg-primary bg-opacity-10' : 'border-light-subtle'">
                                                    <input type="radio" :name="'hal_preset_{{ $modalId }}'" :value="preset.preset_id"
                                                           x-model.number="selectedPresetId"
                                                           @change="selectedAgentId = preset.agent_id"
                                                           class="form-check-input mt-0 flex-shrink-0">
                                                    <div style="flex:1;">
                                                        <div class="fw-semibold" style="font-size:.84rem;color:#1e293b;" x-text="preset.agent_name || 'Agent ' + (idx + 1)"></div>
                                                        <div style="font-size:.74rem;color:#64748b;">
                                                            <span x-text="preset.match_type === 'exact' ? 'Exact match' : 'Broad match'"></span>
                                                            <template x-if="preset.service_count > 0">
                                                                <span> · <span x-text="preset.service_count"></span> services</span>
                                                            </template>
                                                        </div>
                                                    </div>
                                                    <template x-if="preset.match_type === 'exact'">
                                                        <span class="badge bg-success" style="font-size:.6rem;">Exact</span>
                                                    </template>
                                                </label>
                                            </template>
                                        </div>
                                        <div x-show="errors.selectedPreset" class="text-danger mt-1" style="font-size:.78rem;" x-text="errors.selectedPreset"></div>
                                    </div>
                                </template>

                                <div class="row g-2 mb-2">
                                    <div class="col-12">
                                        <label class="form-label" style="font-size:.78rem;font-weight:600;color:#475569;">Your Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" x-model="form.name"
                                               placeholder="Full name" maxlength="191"
                                               :class="errors.name ? 'is-invalid' : ''">
                                        <div class="invalid-feedback" style="font-size:.75rem;" x-text="errors.name"></div>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label" style="font-size:.78rem;font-weight:600;color:#475569;">Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control form-control-sm" x-model="form.email"
                                               placeholder="your@email.com" maxlength="191"
                                               :class="errors.email ? 'is-invalid' : ''">
                                        <div class="invalid-feedback" style="font-size:.75rem;" x-text="errors.email"></div>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label" style="font-size:.78rem;font-weight:600;color:#475569;">Phone</label>
                                        <input type="tel" class="form-control form-control-sm" x-model="form.phone"
                                               placeholder="Optional" maxlength="64">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" style="font-size:.78rem;font-weight:600;color:#475569;">Message</label>
                                        <textarea class="form-control form-control-sm" x-model="form.message"
                                                  rows="3" placeholder="Tell the agent about your needs…" maxlength="2000"></textarea>
                                    </div>
                                </div>

                                <div x-show="submitError" class="alert alert-danger py-2 px-3 mb-2" style="font-size:.8rem;" x-text="submitError"></div>

                                <div class="d-flex justify-content-between mt-3">
                                    <button type="button" class="btn btn-outline-secondary px-3" @click="step = 2">
                                        <i class="fa-solid fa-arrow-left me-1"></i> Back
                                    </button>
                                    <button type="button" class="btn btn-primary px-4 fw-semibold"
                                            style="background:linear-gradient(135deg,#0f766e,#0369a1);border:none;"
                                            @click="submit()"
                                            :disabled="submitting">
                                        <span x-show="!submitting"><i class="fa-solid fa-paper-plane me-1"></i>Send Request</span>
                                        <span x-show="submitting"><i class="fa-solid fa-spinner fa-spin me-1"></i>Sending…</span>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- ── STEP 4: Success ──────────────────────────────── --}}
                    <div x-show="step === 4" x-transition:enter="hal-fade-in" class="text-center py-3">
                        <div style="width:64px;height:64px;border-radius:50%;background:#d1fae5;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                            <i class="fa-solid fa-circle-check" style="font-size:1.8rem;color:#059669;"></i>
                        </div>
                        <h6 class="fw-bold mb-1" style="color:#1e293b;">Request Sent!</h6>
                        <p style="font-size:.85rem;color:#64748b;max-width:280px;margin:0 auto;">
                            An agent will review your request and reach out to you soon.
                        </p>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-4"
                                data-bs-dismiss="modal">Close</button>
                    </div>

                </div>{{-- /padding wrapper --}}
            </div>{{-- /modal-body --}}

        </div>
    </div>
</div>

<style>
.hal-fade-in { animation: halFadeIn .2s ease; }
@keyframes halFadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:none; } }
</style>

@once
<script>
function hireAgentWizard_{{ $modalId }}() {
    return {
        step: 1,
        representationType: '',
        selectedPropertyType: '',
        propertyTypes: {},
        matchStatus: 'no_match',   // 'no_match' | 'matched' | 'multiple_matches'
        matchPresets: [],
        matching: false,
        selectedPresetId: null,
        selectedAgentId: null,
        noPresetMessage: false,    // true when agent exists but has no preset for chosen role/type
        showContactForm: false,    // true when user explicitly opens contact form from no-preset state
        form: { name: '', email: '', phone: '', message: '' },
        errors: {},
        submitError: '',
        submitting: false,
        sourceListingType:  '{{ $listingTypeEscaped }}',
        sourceListingId:    {{ $listingIdVal }},
        sourceListingRole:  '{{ $listingRoleVal }}',
        sourceListingTitle: '{{ $listingTitleVal }}',
        sourceListingUrl:   '{{ $listingUrlVal }}',
        storeUrl: '{{ $storeUrl }}',
        matchUrl: '{{ $matchUrl }}',
        csrfToken: '{{ $csrfToken }}',
        propTypesByRole: {!! $propTypesByRoleJson !!},
        prefillPropType: '{{ $prefillPropTypeEscaped }}',

        init() {
            @auth
            if (!this.form.name)  this.form.name  = '{{ addslashes(auth()->user()->user_name ?? '') }}';
            if (!this.form.email) this.form.email = '{{ addslashes(auth()->user()->email ?? '') }}';
            @endauth
        },

        get representationTypeLabel() {
            const labels = { buyer: "Buyer's Agent", seller: "Seller's Agent", landlord: "Landlord's Agent", tenant: "Tenant's Agent" };
            return labels[this.representationType] || this.representationType;
        },

        get selectedPropertyTypeLabel() {
            return (this.propertyTypes || {})[this.selectedPropertyType] || this.selectedPropertyType;
        },

        goToStep2() {
            this.errors = {};
            if (!this.representationType) { this.errors.representationType = 'Please select a representation type.'; return; }
            this.propertyTypes     = this.propTypesByRole[this.representationType] || {};
            this.selectedPropertyType = '';
            // Pre-fill from listing context if valid for this role
            if (this.prefillPropType && this.propertyTypes[this.prefillPropType]) {
                this.selectedPropertyType = this.prefillPropType;
            }
            this.step = 2;
        },

        async goToStep3() {
            this.errors = {};
            if (!this.selectedPropertyType) { this.errors.selectedPropertyType = 'Please select a property type.'; return; }

            this.matching = true;
            this.matchPresets     = [];
            this.matchStatus      = 'no_match';
            this.selectedPresetId = null;
            this.selectedAgentId  = null;
            this.noPresetMessage  = false;
            this.showContactForm  = false;

            try {
                const res = await fetch(this.matchUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        source_listing_type:    this.sourceListingType,
                        source_listing_id:      this.sourceListingId,
                        representation_type:    this.representationType,
                        selected_property_type: this.selectedPropertyType,
                    }),
                });
                const data = await res.json();

                // ── Primary branch: redirect to the agent's preset review page ──
                if (data.action === 'redirect' && data.url) {
                    // No lead created, no contact form — go straight to the preset review page.
                    window.location.href = data.url;
                    return; // matching spinner stays visible during navigation
                }

                // ── Agent exists but has no matching preset ──
                if (data.action === 'contact_form' && data.reason === 'no_preset') {
                    this.noPresetMessage = true;
                    this.showContactForm  = false;
                    this.matchStatus      = data.match_status || 'no_match';
                    this.matchPresets     = [];
                    this.matching = false;
                    this.step = 3;
                    return;
                }

                // ── Fallback: no_agent, no_listing, no_short_id, or other contact_form reason ──
                // Explicitly open the contact form — no implicit condition tricks.
                this.matchStatus     = data.match_status || 'no_match';
                this.matchPresets    = data.presets || [];
                this.noPresetMessage = false;
                this.showContactForm = true;

                // Auto-select preset when multiple presets are surfaced (future marketplace path).
                if (this.matchStatus === 'multiple_matches' && this.matchPresets.length === 1) {
                    this.selectedPresetId = this.matchPresets[0].preset_id;
                    this.selectedAgentId  = this.matchPresets[0].agent_id;
                }
            } catch (e) {
                // Network / parse error — fall through to general inquiry form.
                this.matchStatus     = 'no_match';
                this.matchPresets    = [];
                this.noPresetMessage = false;
                this.showContactForm = true;
            }

            this.matching = false;
            this.step = 3;
        },

        async submit() {
            this.errors     = {};
            this.submitError = '';

            if (!this.form.name.trim())  { this.errors.name  = 'Please enter your name.'; return; }
            if (!this.form.email.trim()) { this.errors.email = 'Please enter your email.'; return; }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.form.email)) { this.errors.email = 'Please enter a valid email.'; return; }

            // Validate preset selection if multiple matches
            if (this.matchStatus === 'multiple_matches' && !this.selectedPresetId) {
                this.errors.selectedPreset = 'Please choose an agent above.';
                return;
            }

            this.submitting = true;
            try {
                const payload = {
                    source_listing_type:    this.sourceListingType,
                    source_listing_id:      this.sourceListingId,
                    representation_type:    this.representationType,
                    selected_property_type: this.selectedPropertyType,
                    requester_name:         this.form.name,
                    requester_email:        this.form.email,
                    requester_phone:        this.form.phone || null,
                    message:                this.form.message || null,
                };
                if (this.selectedPresetId) payload.selected_preset_id = this.selectedPresetId;
                if (this.selectedAgentId)  payload.selected_agent_id  = this.selectedAgentId;

                const res = await fetch(this.storeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (data.success) {
                    this.step = 4;
                } else {
                    this.submitError = data.message || 'Something went wrong. Please try again.';
                }
            } catch (e) {
                this.submitError = 'Network error. Please try again.';
            }
            this.submitting = false;
        },
    };
}
</script>
@endonce
