{{--
    Agent-Side Compatibility Questionnaire Input Partial
    Included inside each agent bid form's "Working Style & Compatibility" tab.
    Requires: $compatibility_agent_response (public array on the Livewire component)

    ═══════════════════════════════════════════════════════════════════════════════
    FIELD CONTRACT — section → field key → BYA_AGENT_NORM_V1 trait slot
    (Source: ByaAgentResponseNormalizationService resolver methods)
    ═══════════════════════════════════════════════════════════════════════════════
    SECTION                     FIELD KEY                          NORM TRAIT SLOT
    ───────────────────────────────────────────────────────────────────────────────
    communication_preferences   agent_communication_channels       communication_channel       (multi)
    communication_preferences   agent_communication_frequency      communication_frequency     (single)
    communication_preferences   agent_response_time_commitment     responsiveness_expectation  (single)
    negotiation_approach        agent_negotiation_style            negotiation_style           (single)
    guidance_style              agent_guidance_level               guidance_level              (single)
    collaboration_preferences   agent_collaboration_style          collaboration_style         (single)
    transaction_strategy        agent_transaction_pace             transaction_pace            (single)
    transaction_strategy        agent_strategy_experience          property_strategy_fit       (multi)
    representation_philosophy   agent_decision_support_style       decision_making_style       (single)
    representation_philosophy   agent_risk_posture                 risk_tolerance              (single)
    representation_philosophy   agent_representation_philosophy    representation_philosophy   (multi)
    representation_priorities   agent_representation_priorities    representation_priorities   (multi)

    INFORMATIONAL CONTEXT FIELDS (surfaced in BYA_AGENT_NORM_V1.informational_context only,
    never used as trait values):
    communication_preferences   agent_communication_notes, agent_availability_notes
    negotiation_approach        agent_negotiation_notes
    guidance_style              agent_guidance_notes
    collaboration_preferences   agent_availability_windows
    transaction_strategy        agent_strategy_notes
    representation_philosophy   agent_philosophy_narrative, agent_philosophy_notes
    representation_priorities   agent_priority_notes
    ═══════════════════════════════════════════════════════════════════════════════
--}}
<div class="row">
    <div class="col-12">

        <div class="alert border mb-4" style="background: #f0f9f9; border-color: #b2dfdb !important;">
            <div class="d-flex align-items-start gap-3">
                <i class="fa-solid fa-handshake-simple fa-lg mt-1" style="color: #049399;"></i>
                <div>
                    <strong style="color: #036b70;">Working Style &amp; Compatibility</strong><br>
                    <span class="small text-muted">Help clients understand how you work. Your answers help clients find an agent whose working style matches their needs. All fields are optional — fill in as much or as little as you like.</span>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- SECTION 1 — Communication Preferences                               --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <h5 class="fw-bold mt-3 mb-3 border-bottom pb-2" style="color: #049399;">
            <i class="fa-solid fa-comments me-2"></i>1. Communication Preferences
        </h5>

        {{-- Communication Channels (multi-select) --}}
        <div class="form-group mb-3">
            <label class="fw-semibold">Preferred Communication Channels</label>
            <div class="form-hint mb-1 small text-muted">Select all channels you reliably use with clients.</div>
            <div wire:ignore
                 class="input-cover mt-1 has-select-icon"
                 data-compat-ms="communication_preferences|agent_communication_channels"
                 data-compat-val='@json($compatibility_agent_response["communication_preferences"]["agent_communication_channels"] ?? [])'>
                <select id="compat_agent_comm_channels" multiple
                        class="form-control has-icon select2-multiple"
                        data-icon="fa-solid fa-mobile-screen"
                        data-placeholder="Select all that apply">
                    <option value="Phone Call">Phone Call</option>
                    <option value="Text Message">Text Message</option>
                    <option value="Email">Email</option>
                    <option value="Video Call">Video Call (Zoom / FaceTime)</option>
                    <option value="In-Person Meeting">In-Person Meeting</option>
                    <option value="Messaging App">Messaging App (WhatsApp / Signal)</option>
                </select>
            </div>
        </div>

        {{-- Communication Frequency (single-select) --}}
        <div class="form-group mb-3">
            <label class="fw-semibold">Proactive Update Cadence</label>
            <div class="form-hint mb-1 small text-muted">How often will you reach out with proactive updates?</div>
            <div class="input-cover mt-1 has-select-icon">
                <select class="form-control has-icon"
                        data-icon="fa-solid fa-calendar-days"
                        wire:model.defer="compatibility_agent_response.communication_preferences.agent_communication_frequency">
                    <option value="">Select</option>
                    <option value="Daily Updates">Daily Updates</option>
                    <option value="Every Few Days">Every Few Days</option>
                    <option value="Weekly">Weekly</option>
                    <option value="At Key Milestones">At Key Milestones Only</option>
                    <option value="As Needed">As Needed – Client Reaches Out</option>
                </select>
            </div>
        </div>

        {{-- Response Time (single-select) --}}
        <div class="form-group mb-3">
            <label class="fw-semibold">Response Time Commitment</label>
            <div class="form-hint mb-1 small text-muted">Your realistic inbound response time to client messages.</div>
            <div class="input-cover mt-1 has-select-icon">
                <select class="form-control has-icon"
                        data-icon="fa-solid fa-clock"
                        wire:model.defer="compatibility_agent_response.communication_preferences.agent_response_time_commitment">
                    <option value="">Select</option>
                    <option value="Within 1 Hour">Within 1 Hour</option>
                    <option value="Within a Few Hours">Within a Few Hours</option>
                    <option value="Same Business Day">Same Business Day</option>
                    <option value="Within 24 Hours">Within 24 Hours</option>
                    <option value="Within 48 Hours">Within 48 Hours</option>
                </select>
            </div>
        </div>

        <div class="form-group mb-3">
            <label class="fw-semibold">Communication Notes <span class="text-muted fw-normal">(optional)</span></label>
            <textarea class="form-control" rows="2"
                      wire:model.defer="compatibility_agent_response.communication_preferences.agent_communication_notes"
                      placeholder="E.g., I prefer text for quick questions and email for detailed follow-ups."></textarea>
        </div>

        <div class="form-group mb-3">
            <label class="fw-semibold">General Availability <span class="text-muted fw-normal">(optional)</span></label>
            <textarea class="form-control" rows="2"
                      wire:model.defer="compatibility_agent_response.communication_preferences.agent_availability_notes"
                      placeholder="E.g., Available weekdays 8am–8pm and weekends by appointment."></textarea>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- SECTION 2 — Negotiation Approach                                    --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <h5 class="fw-bold mt-4 mb-3 border-bottom pb-2" style="color: #049399;">
            <i class="fa-solid fa-scale-balanced me-2"></i>2. Negotiation Approach
        </h5>

        <div class="form-group mb-3">
            <label class="fw-semibold">Negotiation Style</label>
            <div class="form-hint mb-1 small text-muted">How do you typically approach negotiations on behalf of your clients?</div>
            <div class="input-cover mt-1 has-select-icon">
                <select class="form-control has-icon"
                        data-icon="fa-solid fa-scale-balanced"
                        wire:model.defer="compatibility_agent_response.negotiation_approach.agent_negotiation_style">
                    <option value="">Select</option>
                    <option value="Assertive">Assertive – I push hard for your best terms</option>
                    <option value="Collaborative">Collaborative – I seek win-win outcomes</option>
                    <option value="Methodical">Methodical – Data and analysis guide my strategy</option>
                    <option value="Adaptive">Adaptive – I adjust my style to the situation</option>
                    <option value="Conservative">Conservative – I prioritize risk mitigation</option>
                </select>
            </div>
        </div>

        <div class="form-group mb-3">
            <label class="fw-semibold">Negotiation Notes <span class="text-muted fw-normal">(optional)</span></label>
            <textarea class="form-control" rows="2"
                      wire:model.defer="compatibility_agent_response.negotiation_approach.agent_negotiation_notes"
                      placeholder="E.g., I use comparable sales data to anchor every negotiation and never leave money on the table."></textarea>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- SECTION 3 — Guidance Style                                          --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <h5 class="fw-bold mt-4 mb-3 border-bottom pb-2" style="color: #049399;">
            <i class="fa-solid fa-compass me-2"></i>3. Guidance Style
        </h5>

        <div class="form-group mb-3">
            <label class="fw-semibold">Level of Guidance Provided</label>
            <div class="form-hint mb-1 small text-muted">How hands-on is your default approach when working with clients?</div>
            <div class="input-cover mt-1 has-select-icon">
                <select class="form-control has-icon"
                        data-icon="fa-solid fa-compass"
                        wire:model.defer="compatibility_agent_response.guidance_style.agent_guidance_level">
                    <option value="">Select</option>
                    <option value="Hands-On">Hands-On – I guide every step of the process</option>
                    <option value="Balanced">Balanced – I provide guidance and give you space</option>
                    <option value="Advisory">Advisory – I give you options and let you decide</option>
                    <option value="Minimal">Minimal – I execute your instructions efficiently</option>
                </select>
            </div>
        </div>

        <div class="form-group mb-3">
            <label class="fw-semibold">Guidance Notes <span class="text-muted fw-normal">(optional)</span></label>
            <textarea class="form-control" rows="2"
                      wire:model.defer="compatibility_agent_response.guidance_style.agent_guidance_notes"
                      placeholder="E.g., I educate first-time buyers on every step so they feel confident throughout the process."></textarea>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- SECTION 4 — Collaboration Preferences                               --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <h5 class="fw-bold mt-4 mb-3 border-bottom pb-2" style="color: #049399;">
            <i class="fa-solid fa-people-arrows me-2"></i>4. Collaboration Preferences
        </h5>

        <div class="form-group mb-3">
            <label class="fw-semibold">Collaboration Style</label>
            <div class="form-hint mb-1 small text-muted">How do you typically operate as a professional?</div>
            <div class="input-cover mt-1 has-select-icon">
                <select class="form-control has-icon"
                        data-icon="fa-solid fa-user-tie"
                        wire:model.defer="compatibility_agent_response.collaboration_preferences.agent_collaboration_style">
                    <option value="">Select</option>
                    <option value="Highly Proactive">Highly Proactive – I anticipate and act before you ask</option>
                    <option value="Steady & Systematic">Steady &amp; Systematic – I follow a consistent process</option>
                    <option value="Flexible & Responsive">Flexible &amp; Responsive – I adapt to your needs</option>
                    <option value="Team-Oriented">Team-Oriented – I coordinate all parties closely</option>
                </select>
            </div>
        </div>

        <div class="form-group mb-3">
            <label class="fw-semibold">Availability Windows <span class="text-muted fw-normal">(optional)</span></label>
            <div class="input-cover mt-1">
                <input type="text" class="form-control has-icon"
                       data-icon="fa-solid fa-clock-rotate-left"
                       wire:model.defer="compatibility_agent_response.collaboration_preferences.agent_availability_windows"
                       placeholder="E.g., Weekdays 8am–8pm, weekends by appointment">
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- SECTION 5 — Transaction Strategy                                    --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <h5 class="fw-bold mt-4 mb-3 border-bottom pb-2" style="color: #049399;">
            <i class="fa-solid fa-chess-knight me-2"></i>5. Transaction Strategy
        </h5>

        <div class="form-group mb-3">
            <label class="fw-semibold">Transaction Pace</label>
            <div class="form-hint mb-1 small text-muted">How do you manage transaction timelines?</div>
            <div class="input-cover mt-1 has-select-icon">
                <select class="form-control has-icon"
                        data-icon="fa-solid fa-gauge-high"
                        wire:model.defer="compatibility_agent_response.transaction_strategy.agent_transaction_pace">
                    <option value="">Select</option>
                    <option value="Fast-Paced">Fast-Paced – I push for quick, efficient closes</option>
                    <option value="Moderate">Moderate – I balance speed with thoroughness</option>
                    <option value="Patient">Patient – I prioritize the right outcome over speed</option>
                    <option value="Client-Driven">Client-Driven – I match your timeline</option>
                </select>
            </div>
        </div>

        {{-- Strategy Experience (multi-select) --}}
        <div class="form-group mb-3">
            <label class="fw-semibold">Transaction Types &amp; Experience</label>
            <div class="form-hint mb-1 small text-muted">Select all transaction types you have meaningful experience with.</div>
            <div wire:ignore
                 class="input-cover mt-1 has-select-icon"
                 data-compat-ms="transaction_strategy|agent_strategy_experience"
                 data-compat-val='@json($compatibility_agent_response["transaction_strategy"]["agent_strategy_experience"] ?? [])'>
                <select id="compat_agent_strategy_exp" multiple
                        class="form-control has-icon select2-multiple"
                        data-icon="fa-solid fa-briefcase"
                        data-placeholder="Select all that apply">
                    <option value="First-Time Buyers/Sellers">First-Time Buyers / Sellers</option>
                    <option value="Move-Up / Move-Down">Move-Up / Move-Down Transactions</option>
                    <option value="Investment Properties">Investment Properties</option>
                    <option value="Distressed Properties">Distressed Properties / Short Sales</option>
                    <option value="New Construction">New Construction</option>
                    <option value="Luxury Properties">Luxury / High-End Properties</option>
                    <option value="Commercial Real Estate">Commercial Real Estate</option>
                    <option value="1031 Exchanges">1031 Exchanges</option>
                    <option value="Relocation Transactions">Relocation Transactions</option>
                    <option value="Estate & Probate Sales">Estate &amp; Probate Sales</option>
                    <option value="Multi-Family Properties">Multi-Family Properties</option>
                </select>
            </div>
        </div>

        <div class="form-group mb-3">
            <label class="fw-semibold">Strategy Notes <span class="text-muted fw-normal">(optional)</span></label>
            <textarea class="form-control" rows="2"
                      wire:model.defer="compatibility_agent_response.transaction_strategy.agent_strategy_notes"
                      placeholder="E.g., I specialize in competitive markets and have closed over 30 off-market deals."></textarea>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- SECTION 6 — Representation Philosophy                               --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <h5 class="fw-bold mt-4 mb-3 border-bottom pb-2" style="color: #049399;">
            <i class="fa-solid fa-shield-halved me-2"></i>6. Representation Philosophy
        </h5>

        <div class="form-group mb-3">
            <label class="fw-semibold">Decision Support Style</label>
            <div class="form-hint mb-1 small text-muted">How do you support clients through key decisions?</div>
            <div class="input-cover mt-1 has-select-icon">
                <select class="form-control has-icon"
                        data-icon="fa-solid fa-brain"
                        wire:model.defer="compatibility_agent_response.representation_philosophy.agent_decision_support_style">
                    <option value="">Select</option>
                    <option value="Data-Driven">Data-Driven – I present market data and analysis</option>
                    <option value="Options-Based">Options-Based – I lay out your choices clearly</option>
                    <option value="Recommendation-First">Recommendation-First – I give you my recommendation</option>
                    <option value="Collaborative Discussion">Collaborative – We think through it together</option>
                </select>
            </div>
        </div>

        <div class="form-group mb-3">
            <label class="fw-semibold">Risk Posture</label>
            <div class="form-hint mb-1 small text-muted">Your professional comfort level with transactional risk.</div>
            <div class="input-cover mt-1 has-select-icon">
                <select class="form-control has-icon"
                        data-icon="fa-solid fa-shield-halved"
                        wire:model.defer="compatibility_agent_response.representation_philosophy.agent_risk_posture">
                    <option value="">Select</option>
                    <option value="Conservative">Conservative – I protect against downside risk</option>
                    <option value="Balanced">Balanced – I weigh risk vs. reward</option>
                    <option value="Opportunistic">Opportunistic – I help you pursue upside</option>
                    <option value="Adaptive">Adaptive – I match your risk tolerance</option>
                </select>
            </div>
        </div>

        {{-- Representation Philosophy (multi-select) --}}
        <div class="form-group mb-3">
            <label class="fw-semibold">Representation Philosophy</label>
            <div class="form-hint mb-1 small text-muted">Select all that describe your professional values.</div>
            <div wire:ignore
                 class="input-cover mt-1 has-select-icon"
                 data-compat-ms="representation_philosophy|agent_representation_philosophy"
                 data-compat-val='@json($compatibility_agent_response["representation_philosophy"]["agent_representation_philosophy"] ?? [])'>
                <select id="compat_agent_rep_philosophy" multiple
                        class="form-control has-icon select2-multiple"
                        data-icon="fa-solid fa-star"
                        data-placeholder="Select all that apply">
                    <option value="Fiduciary-First">Fiduciary-First – Your interests come before all else</option>
                    <option value="Transparent Communication">Transparent Communication – I keep you fully informed</option>
                    <option value="Full-Service Partnership">Full-Service Partnership – I handle every detail</option>
                    <option value="Education-Focused">Education-Focused – I help you understand every step</option>
                    <option value="Results-Oriented">Results-Oriented – I focus on measurable outcomes</option>
                    <option value="Long-Term Relationship">Long-Term Relationship – I aim to be your agent for life</option>
                </select>
            </div>
        </div>

        <div class="form-group mb-3">
            <label class="fw-semibold">Philosophy Narrative <span class="text-muted fw-normal">(optional)</span></label>
            <textarea class="form-control" rows="3"
                      wire:model.defer="compatibility_agent_response.representation_philosophy.agent_philosophy_narrative"
                      placeholder="In your own words, describe your overall representation philosophy and what clients can expect when working with you."></textarea>
        </div>

        <div class="form-group mb-3">
            <label class="fw-semibold">Additional Philosophy Notes <span class="text-muted fw-normal">(optional)</span></label>
            <textarea class="form-control" rows="2"
                      wire:model.defer="compatibility_agent_response.representation_philosophy.agent_philosophy_notes"
                      placeholder="E.g., I believe in radical transparency – I will always tell you what I think, even if it's not what you want to hear."></textarea>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- SECTION 7 — Representation Priorities                               --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <h5 class="fw-bold mt-4 mb-3 border-bottom pb-2" style="color: #049399;">
            <i class="fa-solid fa-list-check me-2"></i>7. Representation Priorities
        </h5>

        {{-- Representation Priorities (multi-select) --}}
        <div class="form-group mb-3">
            <label class="fw-semibold">Primary Capability Strengths</label>
            <div class="form-hint mb-1 small text-muted">Select the areas where you deliver the most value to clients.</div>
            <div wire:ignore
                 class="input-cover mt-1 has-select-icon"
                 data-compat-ms="representation_priorities|agent_representation_priorities"
                 data-compat-val='@json($compatibility_agent_response["representation_priorities"]["agent_representation_priorities"] ?? [])'>
                <select id="compat_agent_rep_priorities" multiple
                        class="form-control has-icon select2-multiple"
                        data-icon="fa-solid fa-list-check"
                        data-placeholder="Select all that apply">
                    <option value="Price Optimization">Price Optimization</option>
                    <option value="Timeline Management">Timeline Management</option>
                    <option value="Negotiation Strength">Negotiation Strength</option>
                    <option value="Market Knowledge">Market Knowledge &amp; Analysis</option>
                    <option value="Client Communication">Client Communication &amp; Education</option>
                    <option value="Transaction Coordination">Transaction Coordination</option>
                    <option value="Legal & Contract Expertise">Legal &amp; Contract Expertise</option>
                    <option value="Marketing & Exposure">Marketing &amp; Exposure</option>
                    <option value="Property Search & Evaluation">Property Search &amp; Evaluation</option>
                </select>
            </div>
        </div>

        <div class="form-group mb-3">
            <label class="fw-semibold">Priority Notes <span class="text-muted fw-normal">(optional)</span></label>
            <textarea class="form-control" rows="2"
                      wire:model.defer="compatibility_agent_response.representation_priorities.agent_priority_notes"
                      placeholder="E.g., My top priority is always getting you the best price – I've saved clients an average of 4.2% below asking."></textarea>
        </div>

    </div>
</div>

<script>
(function () {
    'use strict';

    function initCompatMultiSelects() {
        document.querySelectorAll('[data-compat-ms]').forEach(function (wrapper) {
            var parts       = (wrapper.getAttribute('data-compat-ms') || '').split('|');
            if (parts.length < 2) return;
            var section     = parts[0];
            var field       = parts[1];
            var livewirePath = 'compatibility_agent_response.' + section + '.' + field;
            var rawVal      = wrapper.getAttribute('data-compat-val') || '[]';
            var currentVals = [];
            try { currentVals = JSON.parse(rawVal) || []; } catch (e) { currentVals = []; }

            var $sel = $(wrapper).find('select');
            if (!$sel.length) return;

            if ($sel.hasClass('select2-hidden-accessible')) {
                $sel.select2('destroy');
            }

            $sel.select2({
                placeholder : $sel.attr('data-placeholder') || 'Select',
                allowClear  : true,
                width       : '100%',
            });

            if (currentVals.length) {
                $sel.val(currentVals).trigger('change.select2');
            }

            $sel.off('change.compat').on('change.compat', function () {
                var vals = $(this).val() || [];
                @this.set(livewirePath, vals);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', initCompatMultiSelects);

    document.addEventListener('livewire:load', function () {
        initCompatMultiSelects();
    });

    window.addEventListener('livewire:update', function () {
        setTimeout(initCompatMultiSelects, 80);
    });

    Livewire.hook('message.processed', function () {
        setTimeout(initCompatMultiSelects, 80);
    });
}());
</script>
