<h3>Representation Preferences &amp; Compatibility</h3>

<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>🎯 Share your rental goals, communication style, and negotiation preferences so Agents can tailor
                their proposals to match how you work best.
            </strong>
        </div>
    </div>
</div>

{{-- ─── Section 1: Tenant Goals & Rental Priorities ─────────────────────────── --}}
<h5 class="fw-bold mt-3 mb-3 border-bottom pb-2">Tenant Goals &amp; Rental Priorities</h5>

{{-- 1. Primary Rental Goal (required, single-select) --}}
<div class="form-group">
    <label class="fw-bold">
        Primary Rental Goal:<span class="text-danger">*</span>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the main reason you are searching for a rental property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div wire:ignore wire:key="compat-primary-rental-goal-s2">
        <select id="compat_primary_rental_goal" class="form-control"
            data-selected="{{ $compatibility_preferences['tenant_specific']['primary_rental_goal'] ?? '' }}"
            required>
            <option value="">Select</option>
            <option value="Find a long-term home">Find a long-term home</option>
            <option value="Temporary / short-term housing">Temporary / short-term housing</option>
            <option value="Relocating for work">Relocating for work</option>
            <option value="Downsizing">Downsizing</option>
            <option value="Upsizing">Upsizing</option>
            <option value="Investment search">Investment search</option>
            <option value="Other">Other</option>
        </select>
    </div>
    @error('compatibility_preferences.tenant_specific.primary_rental_goal')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

{{-- Companion "Other" input for primary_rental_goal --}}
<div id="compat-other-primary-rental-goal-wrapper"
    style="display: {{ (($compatibility_preferences['tenant_specific']['primary_rental_goal'] ?? '') === 'Other') ? 'block' : 'none' }};">
    <div class="form-group">
        <label class="fw-bold">Please specify your primary rental goal:</label>
        <div class="input-cover">
            <input type="text"
                wire:model.defer="compatibility_preferences.tenant_specific.other_primary_rental_goal"
                class="form-control has-icon"
                data-icon="fa-solid fa-pen"
                placeholder="Enter primary rental goal (e.g., Temporary relocation for work project)"
                maxlength="500">
        </div>
    </div>
</div>

{{-- 2. Representation Priorities (required, multi-select) --}}
<div class="form-group">
    <label class="fw-bold">
        Representation Priorities:<span class="text-danger">*</span>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select all areas where you most want your Agent to focus.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div wire:ignore wire:key="compat-representation-priorities-s2">
        <select id="compat_representation_priorities" class="form-control" multiple
            data-selected="{{ json_encode($compatibility_preferences['tenant_specific']['representation_priorities'] ?? []) }}"
            required>
            <option value="Neighborhood / location">Neighborhood / location</option>
            <option value="Budget management">Budget management</option>
            <option value="Speed of placement">Speed of placement</option>
            <option value="Lease negotiation">Lease negotiation</option>
            <option value="Property condition">Property condition</option>
            <option value="Pet-friendly options">Pet-friendly options</option>
            <option value="Accessibility features">Accessibility features</option>
            <option value="School district">School district</option>
            <option value="Other">Other</option>
        </select>
    </div>
    @error('compatibility_preferences.tenant_specific.representation_priorities')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

{{-- 3. Other Representation Priorities (companion input) --}}
<div id="compat-other-representation-priorities-wrapper"
    style="display: {{ in_array('Other', $compatibility_preferences['tenant_specific']['representation_priorities'] ?? []) ? 'block' : 'none' }};">
    <div class="form-group">
        <label class="fw-bold">Please specify other representation priority:</label>
        <div class="input-cover">
            <input type="text"
                wire:model.defer="compatibility_preferences.tenant_specific.other_representation_priorities"
                class="form-control has-icon"
                data-icon="fa-solid fa-pen"
                placeholder="Enter other representation priority (e.g., Proximity to public transit)"
                maxlength="500">
        </div>
    </div>
</div>

{{-- 4. Move-In Timeline Urgency (optional, single-select) --}}
<div class="form-group">
    <label class="fw-bold">
        Move-In Timeline Urgency:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="How soon do you need to move in? This helps Agents prioritize their search.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div wire:ignore wire:key="compat-timeline-urgency-s2">
        <select id="compat_timeline_urgency" class="form-control"
            data-selected="{{ $compatibility_preferences['tenant_specific']['timeline_urgency'] ?? '' }}">
            <option value="">Select</option>
            <option value="Immediately (within 2 weeks)">Immediately (within 2 weeks)</option>
            <option value="1–3 months">1–3 months</option>
            <option value="3–6 months">3–6 months</option>
            <option value="6+ months">6+ months</option>
            <option value="Flexible">Flexible</option>
        </select>
    </div>
    @error('compatibility_preferences.tenant_specific.timeline_urgency')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

{{-- 5. Budget Flexibility (optional, single-select) --}}
<div class="form-group">
    <label class="fw-bold">
        Budget Flexibility:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate how flexible your rental budget is if the right property comes along.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div wire:ignore wire:key="compat-budget-flexibility-s2">
        <select id="compat_budget_flexibility" class="form-control"
            data-selected="{{ $compatibility_preferences['tenant_specific']['budget_flexibility'] ?? '' }}">
            <option value="">Select</option>
            <option value="Fixed – no flexibility">Fixed – no flexibility</option>
            <option value="Slightly flexible (±5%)">Slightly flexible (±5%)</option>
            <option value="Moderately flexible (±10–15%)">Moderately flexible (±10–15%)</option>
            <option value="Very flexible (negotiable)">Very flexible (negotiable)</option>
        </select>
    </div>
    @error('compatibility_preferences.tenant_specific.budget_flexibility')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

{{-- ─── Section 2: Communication & Working Style ─────────────────────────────── --}}
<h5 class="fw-bold mt-4 mb-3 border-bottom pb-2">Communication &amp; Working Style</h5>

{{-- 6. Preferred Communication Style (required, single-select) --}}
<div class="form-group">
    <label class="fw-bold">
        Preferred Communication Style:<span class="text-danger">*</span>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="How do you prefer to communicate with your Agent?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div wire:ignore wire:key="compat-communication-style-s2">
        <select id="compat_communication_style" class="form-control"
            data-selected="{{ $compatibility_preferences['tenant_specific']['communication_style'] ?? '' }}"
            required>
            <option value="">Select</option>
            <option value="Email">Email</option>
            <option value="Phone calls">Phone calls</option>
            <option value="Text / SMS">Text / SMS</option>
            <option value="Video calls">Video calls</option>
            <option value="In-person meetings">In-person meetings</option>
            <option value="Other">Other</option>
        </select>
    </div>
    @error('compatibility_preferences.tenant_specific.communication_style')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

{{-- 7. Other Communication Style (companion input) --}}
<div id="compat-other-communication-style-wrapper"
    style="display: {{ (($compatibility_preferences['tenant_specific']['communication_style'] ?? '') === 'Other') ? 'block' : 'none' }};">
    <div class="form-group">
        <label class="fw-bold">Please specify other communication style:</label>
        <div class="input-cover">
            <input type="text"
                wire:model.defer="compatibility_preferences.tenant_specific.other_communication_style"
                class="form-control has-icon"
                data-icon="fa-solid fa-pen"
                placeholder="Enter communication style (e.g., Prefer WhatsApp or Slack messages)"
                maxlength="500">
        </div>
    </div>
</div>

{{-- 8. Preferred Contact Frequency (optional, single-select) --}}
<div class="form-group">
    <label class="fw-bold">
        Preferred Contact Frequency:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="How often would you like your Agent to provide updates?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div wire:ignore wire:key="compat-contact-frequency-s2">
        <select id="compat_contact_frequency" class="form-control"
            data-selected="{{ $compatibility_preferences['tenant_specific']['contact_frequency'] ?? '' }}">
            <option value="">Select</option>
            <option value="Daily">Daily</option>
            <option value="Every few days">Every few days</option>
            <option value="Weekly">Weekly</option>
            <option value="Only on major updates">Only on major updates</option>
            <option value="As needed">As needed</option>
        </select>
    </div>
    @error('compatibility_preferences.tenant_specific.contact_frequency')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

{{-- 9. Preferred Contact Time of Day (optional, single-select) --}}
<div class="form-group">
    <label class="fw-bold">
        Preferred Contact Time of Day:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="When is the best time for your Agent to reach you?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div wire:ignore wire:key="compat-preferred-contact-method-s2">
        <select id="compat_preferred_contact_method" class="form-control"
            data-selected="{{ $compatibility_preferences['tenant_specific']['preferred_contact_method'] ?? '' }}">
            <option value="">Select</option>
            <option value="Morning">Morning</option>
            <option value="Afternoon">Afternoon</option>
            <option value="Evening">Evening</option>
            <option value="Anytime">Anytime</option>
        </select>
    </div>
    @error('compatibility_preferences.tenant_specific.preferred_contact_method')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

{{-- 10. Preferred Agent Working Style (required, single-select) --}}
<div class="form-group">
    <label class="fw-bold">
        Preferred Agent Working Style:<span class="text-danger">*</span>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="How hands-on would you like your Agent to be throughout the process?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div wire:ignore wire:key="compat-preferred-agent-working-style-s2">
        <select id="compat_preferred_agent_working_style" class="form-control"
            data-selected="{{ $compatibility_preferences['tenant_specific']['preferred_agent_working_style'] ?? '' }}"
            required>
            <option value="">Select</option>
            <option value="Highly proactive – send regular updates without prompting">Highly proactive – send regular updates without prompting</option>
            <option value="Collaborative – frequent check-ins and joint decisions">Collaborative – frequent check-ins and joint decisions</option>
            <option value="Efficient – contact me only when needed">Efficient – contact me only when needed</option>
            <option value="Full service – handle everything and keep me informed">Full service – handle everything and keep me informed</option>
        </select>
    </div>
    @error('compatibility_preferences.tenant_specific.preferred_agent_working_style')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

{{-- ─── Section 3: Negotiation & Representation ─────────────────────────────── --}}
<h5 class="fw-bold mt-4 mb-3 border-bottom pb-2">Negotiation &amp; Representation</h5>

{{-- 11. Negotiation Style (required, single-select) --}}
<div class="form-group">
    <label class="fw-bold">
        Negotiation Style:<span class="text-danger">*</span>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="How should your Agent approach lease negotiations on your behalf?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div wire:ignore wire:key="compat-negotiation-style-s2">
        <select id="compat_negotiation_style" class="form-control"
            data-selected="{{ $compatibility_preferences['tenant_specific']['negotiation_style'] ?? '' }}"
            required>
            <option value="">Select</option>
            <option value="Aggressive – push hard for the best deal">Aggressive – push hard for the best deal</option>
            <option value="Collaborative – find mutually beneficial terms">Collaborative – find mutually beneficial terms</option>
            <option value="Conservative – prioritize securing a property over terms">Conservative – prioritize securing a property over terms</option>
            <option value="Flexible – adapt based on property and market">Flexible – adapt based on property and market</option>
        </select>
    </div>
    @error('compatibility_preferences.tenant_specific.negotiation_style')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

{{-- 12. Decision-Making Style (optional, single-select) --}}
<div class="form-group">
    <label class="fw-bold">
        Decision-Making Style:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="How do you typically approach major decisions when selecting a property?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div wire:ignore wire:key="compat-decision-making-style-s2">
        <select id="compat_decision_making_style" class="form-control"
            data-selected="{{ $compatibility_preferences['tenant_specific']['decision_making_style'] ?? '' }}">
            <option value="">Select</option>
            <option value="Quick – ready to commit fast">Quick – ready to commit fast</option>
            <option value="Deliberate – need time to consider options">Deliberate – need time to consider options</option>
            <option value="Research-driven – want all facts before deciding">Research-driven – want all facts before deciding</option>
            <option value="Collaborative – involve family / partner in decisions">Collaborative – involve family / partner in decisions</option>
        </select>
    </div>
    @error('compatibility_preferences.tenant_specific.decision_making_style')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

{{-- 13. Concerns or Barriers (optional, textarea) --}}
<div class="form-group">
    <label class="fw-bold">
        Concerns or Barriers:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Share any concerns, barriers, or sensitivities that may affect your rental search or the Agent relationship.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <textarea wire:model.defer="compatibility_preferences.tenant_specific.concerns_or_barriers"
            class="form-control"
            rows="3"
            placeholder="e.g., Previous rental disputes, credit concerns, tight timeline, specific landlord requirements..."
            maxlength="2000"></textarea>
    </div>
    @error('compatibility_preferences.tenant_specific.concerns_or_barriers')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

{{-- 14. Additional Compatibility Notes (optional, textarea) --}}
<div class="form-group">
    <label class="fw-bold">
        Additional Compatibility Notes:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Any other information that would help Agents understand your preferences and determine if they are a good fit.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <textarea wire:model.defer="compatibility_preferences.tenant_specific.additional_compatibility_notes"
            class="form-control"
            rows="3"
            placeholder="e.g., I prefer an Agent with commercial leasing experience, or I have a strict move-in date..."
            maxlength="2000"></textarea>
    </div>
    @error('compatibility_preferences.tenant_specific.additional_compatibility_notes')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>
