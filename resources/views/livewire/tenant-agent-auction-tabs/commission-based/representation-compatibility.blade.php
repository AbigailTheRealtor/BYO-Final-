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
    <div class="input-cover mt-2 has-select-icon" wire:ignore wire:key="compat-primary-rental-goal-s2">
        <select id="compat_primary_rental_goal" class="form-control has-icon"
            data-icon="fa-solid fa-bullseye"
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
    <div class="input-cover mt-2 has-select-icon" wire:ignore wire:key="compat-representation-priorities-s2">
        <select id="compat_representation_priorities" class="form-control has-icon" multiple
            data-icon="fa-solid fa-list-check"
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

{{-- Companion "Other" input for representation_priorities --}}
<div id="compat-other-representation-priorities-wrapper"
    style="display: {{ in_array('Other', $compatibility_preferences['tenant_specific']['representation_priorities'] ?? []) ? 'block' : 'none' }};">
    <div class="form-group">
        <div class="input-cover">
            <input type="text"
                wire:model.defer="compatibility_preferences.tenant_specific.other_representation_priorities"
                class="form-control has-icon"
                data-icon="fa-solid fa-pen"
                placeholder="Enter representation priority (e.g., Proximity to public transit)"
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
    <div class="input-cover mt-2 has-select-icon" wire:ignore wire:key="compat-timeline-urgency-s2">
        <select id="compat_timeline_urgency" class="form-control has-icon"
            data-icon="fa-solid fa-clock"
            data-selected="{{ $compatibility_preferences['tenant_specific']['timeline_urgency'] ?? '' }}">
            <option value="">Select</option>
            <option value="Immediate (Within 2 Weeks)">Immediate (Within 2 Weeks)</option>
            <option value="Within 30 Days">Within 30 Days</option>
            <option value="1–2 Months">1–2 Months</option>
            <option value="2–3 Months">2–3 Months</option>
            <option value="3–6 Months">3–6 Months</option>
            <option value="6+ Months">6+ Months</option>
            <option value="Exploring Options Only">Exploring Options Only</option>
            <option value="Other">Other</option>
        </select>
    </div>
    @error('compatibility_preferences.tenant_specific.timeline_urgency')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

{{-- Companion "Other" input for timeline_urgency --}}
<div id="compat-other-timeline-urgency-wrapper"
    style="display: {{ (($compatibility_preferences['tenant_specific']['timeline_urgency'] ?? '') === 'Other') ? 'block' : 'none' }};">
    <div class="form-group">
        <div class="input-cover">
            <input type="text"
                wire:model.defer="compatibility_preferences.tenant_specific.other_timeline_urgency"
                class="form-control has-icon"
                data-icon="fa-solid fa-pen"
                placeholder="Enter timeline details (e.g., Waiting for home sale, Relocating after school year, Flexible after lease ends)"
                maxlength="500">
        </div>
    </div>
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
    <div class="input-cover mt-2 has-select-icon" wire:ignore wire:key="compat-budget-flexibility-s2">
        <select id="compat_budget_flexibility" class="form-control has-icon"
            data-icon="fa-solid fa-wallet"
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
    <div class="input-cover mt-2 has-select-icon" wire:ignore wire:key="compat-communication-style-s2">
        <select id="compat_communication_style" class="form-control has-icon"
            data-icon="fa-solid fa-comments"
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

{{-- Companion "Other" input for communication_style --}}
<div id="compat-other-communication-style-wrapper"
    style="display: {{ (($compatibility_preferences['tenant_specific']['communication_style'] ?? '') === 'Other') ? 'block' : 'none' }};">
    <div class="form-group">
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
    <div class="input-cover mt-2 has-select-icon" wire:ignore wire:key="compat-contact-frequency-s2">
        <select id="compat_contact_frequency" class="form-control has-icon"
            data-icon="fa-solid fa-calendar-check"
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
    <div class="input-cover mt-2 has-select-icon" wire:ignore wire:key="compat-preferred-contact-method-s2">
        <select id="compat_preferred_contact_method" class="form-control has-icon"
            data-icon="fa-solid fa-sun"
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
    <div class="input-cover mt-2 has-select-icon" wire:ignore wire:key="compat-preferred-agent-working-style-s2">
        <select id="compat_preferred_agent_working_style" class="form-control has-icon"
            data-icon="fa-solid fa-briefcase"
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

{{-- NEW: Most Important Agent Traits (optional, multi-select) --}}
<div class="form-group">
    <label class="fw-bold">
        Most Important Agent Traits:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the qualities that matter most to you when choosing an Agent to represent your rental search.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover mt-2 has-select-icon" wire:ignore wire:key="compat-most-important-agent-traits-s2">
        <select id="compat_most_important_agent_traits" class="form-control has-icon" multiple
            data-icon="fa-solid fa-star"
            data-selected="{{ json_encode($compatibility_preferences['tenant_specific']['most_important_agent_traits'] ?? []) }}">
            <option value="Honesty and Transparency">Honesty and Transparency</option>
            <option value="Strong Communication">Strong Communication</option>
            <option value="Market Knowledge">Market Knowledge</option>
            <option value="Negotiation Skills">Negotiation Skills</option>
            <option value="Responsiveness">Responsiveness</option>
            <option value="Local Expertise">Local Expertise</option>
            <option value="Client-Focused Approach">Client-Focused Approach</option>
            <option value="Technology-Savvy">Technology-Savvy</option>
            <option value="Attention to Detail">Attention to Detail</option>
            <option value="Problem-Solving Ability">Problem-Solving Ability</option>
            <option value="Professional Network">Professional Network</option>
            <option value="Other">Other</option>
        </select>
    </div>
    @error('compatibility_preferences.tenant_specific.most_important_agent_traits')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

{{-- Companion "Other" input for most_important_agent_traits --}}
<div id="compat-other-most-important-agent-traits-wrapper"
    style="display: {{ in_array('Other', $compatibility_preferences['tenant_specific']['most_important_agent_traits'] ?? []) ? 'block' : 'none' }};">
    <div class="form-group">
        <div class="input-cover">
            <input type="text"
                wire:model.defer="compatibility_preferences.tenant_specific.other_most_important_agent_traits"
                class="form-control has-icon"
                data-icon="fa-solid fa-pen"
                placeholder="Enter agent trait (e.g., Multilingual or Specialized in commercial properties)"
                maxlength="500">
        </div>
    </div>
</div>

{{-- NEW: Desired Level of Agent Involvement (optional, single-select) --}}
<div class="form-group">
    <label class="fw-bold">
        Desired Level of Agent Involvement:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="How much day-to-day involvement would you like your Agent to have in your property search and rental process?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover mt-2 has-select-icon" wire:ignore wire:key="compat-desired-level-of-agent-involvement-s2">
        <select id="compat_desired_level_of_agent_involvement" class="form-control has-icon"
            data-icon="fa-solid fa-sliders"
            data-selected="{{ $compatibility_preferences['tenant_specific']['desired_level_of_agent_involvement'] ?? '' }}">
            <option value="">Select</option>
            <option value="Fully Delegated – Agent manages everything, minimal input needed">Fully Delegated – Agent manages everything, minimal input needed</option>
            <option value="Mostly Delegated – Agent leads, I approve key decisions">Mostly Delegated – Agent leads, I approve key decisions</option>
            <option value="Collaborative – We work together equally throughout">Collaborative – We work together equally throughout</option>
            <option value="Mostly Hands-On – I lead, Agent supports and advises">Mostly Hands-On – I lead, Agent supports and advises</option>
            <option value="Other">Other</option>
        </select>
    </div>
    @error('compatibility_preferences.tenant_specific.desired_level_of_agent_involvement')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

{{-- Companion "Other" input for desired_level_of_agent_involvement --}}
<div id="compat-other-desired-level-of-agent-involvement-wrapper"
    style="display: {{ (($compatibility_preferences['tenant_specific']['desired_level_of_agent_involvement'] ?? '') === 'Other') ? 'block' : 'none' }};">
    <div class="form-group">
        <div class="input-cover">
            <input type="text"
                wire:model.defer="compatibility_preferences.tenant_specific.other_desired_level_of_agent_involvement"
                class="form-control has-icon"
                data-icon="fa-solid fa-pen"
                placeholder="Enter desired involvement level (e.g., Available only for final negotiations)"
                maxlength="500">
        </div>
    </div>
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
    <div class="input-cover mt-2 has-select-icon" wire:ignore wire:key="compat-negotiation-style-s2">
        <select id="compat_negotiation_style" class="form-control has-icon"
            data-icon="fa-solid fa-handshake"
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
    <div class="input-cover mt-2 has-select-icon" wire:ignore wire:key="compat-decision-making-style-s2">
        <select id="compat_decision_making_style" class="form-control has-icon"
            data-icon="fa-solid fa-brain"
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
            class="form-control has-icon"
            data-icon="fa-solid fa-comment-dots"
            rows="1"
            style="resize: none; overflow: hidden; min-height: 38px;"
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
            class="form-control has-icon"
            data-icon="fa-solid fa-note-sticky"
            rows="1"
            style="resize: none; overflow: hidden; min-height: 38px;"
            placeholder="e.g., I prefer an Agent with commercial leasing experience, or I have a strict move-in date..."
            maxlength="2000"></textarea>
    </div>
    @error('compatibility_preferences.tenant_specific.additional_compatibility_notes')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>
