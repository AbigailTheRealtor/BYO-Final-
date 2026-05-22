<h3>Representation Preferences &amp; Compatibility</h3>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>🤝 Help agents understand how you prefer to work. Your answers help agents tailor their approach and service to match your needs.</strong>
        </div>
    </div>
</div>

<h5 class="fw-bold mt-4 mb-3 border-bottom pb-2">Buyer Goals &amp; Priorities</h5>

<!-- 1. Primary Transaction Goal -->
<div class="form-group"
     x-data="{ showPtgOther: {{ ($compatibility_preferences['buyer_specific']['primary_transaction_goal'] ?? '') === 'Other' ? 'true' : 'false' }} }"
     @update-ptg-other.window="showPtgOther = $event.detail.showOther">
    <label class="fw-bold">
        Primary Transaction Goal:<span class="text-danger">*</span>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="What is the main reason you are purchasing a property?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div wire:ignore wire:key="compat-ptg-select" class="input-cover mt-2 has-select-icon">
        <select id="compat_primary_transaction_goal"
                data-compat-field="primary_transaction_goal"
                data-placeholder="Select"
                class="form-control has-icon" data-icon="fa-solid fa-bullseye"
                required>
            <option value="">Select</option>
            <option value="Primary Residence">Primary Residence</option>
            <option value="Vacation / Secondary Home">Vacation / Secondary Home</option>
            <option value="Investment Property">Investment Property</option>
            <option value="Fix &amp; Flip">Fix &amp; Flip</option>
            <option value="Commercial Use">Commercial Use</option>
            <option value="Land Purchase">Land Purchase</option>
            <option value="Other">Other</option>
        </select>
    </div>
    <span class="error mt-2">@error('compatibility_preferences.buyer_specific.primary_transaction_goal') {{ $message }} @enderror</span>
    <div x-show="showPtgOther" class="mt-2" x-cloak>
        <div class="input-cover">
            <input type="text"
                   wire:model.defer="compatibility_preferences.buyer_specific.primary_transaction_goal_other"
                   class="form-control has-icon" data-icon="fa-solid fa-pen"
                   placeholder="Enter primary transaction goal (e.g., Relocating for work)">
        </div>
    </div>
</div>

<!-- 2. Representation Priorities (multi-select) -->
<div class="form-group"
     x-data="{ hasRpOther: {{ in_array('Other', $compatibility_preferences['buyer_specific']['representation_priorities'] ?? []) ? 'true' : 'false' }} }"
     @update-rp-other.window="hasRpOther = $event.detail.hasOther"
     wire:key="compat-rep-priorities-group">
    <label class="fw-bold">
        Representation Priorities:<span class="text-danger">*</span>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the areas where agent expertise matters most to you. You may choose multiple.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div wire:ignore wire:key="compat-rep-priorities-select" class="input-cover mt-2 has-select-icon">
        <select id="compat_representation_priorities" name="compat_representation_priorities[]"
                multiple class="form-control has-icon select2-multiple" data-icon="fa-solid fa-list-check"
                data-placeholder="Select" required>
            <option value="Price Negotiation">Price Negotiation</option>
            <option value="Speed of Transaction">Speed of Transaction</option>
            <option value="Finding Off-Market Properties">Finding Off-Market Properties</option>
            <option value="Contract Protection">Contract Protection</option>
            <option value="Communication &amp; Updates">Communication &amp; Updates</option>
            <option value="Neighborhood Expertise">Neighborhood Expertise</option>
            <option value="Investment Analysis">Investment Analysis</option>
            <option value="First-Time Buyer Guidance">First-Time Buyer Guidance</option>
            <option value="Relocation Assistance">Relocation Assistance</option>
            <option value="Other">Other</option>
        </select>
    </div>
    <span class="error mt-2">@error('compatibility_preferences.buyer_specific.representation_priorities') {{ $message }} @enderror</span>
    <div x-show="hasRpOther" class="mt-2" x-cloak>
        <div class="input-cover">
            <input type="text"
                   wire:model.defer="compatibility_preferences.buyer_specific.representation_priorities_other"
                   class="form-control has-icon" data-icon="fa-solid fa-pen"
                   placeholder="Enter representation priorities (e.g., Investment analysis and off-market opportunities)">
        </div>
    </div>
</div>

<!-- 3. Risk Tolerance -->
<div class="form-group">
    <label class="fw-bold">
        Risk Tolerance Level:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="How comfortable are you with taking risks in your purchase (e.g., waiving contingencies, competitive bidding)?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div wire:ignore wire:key="compat-risk-select" class="input-cover mt-2 has-select-icon">
        <select id="compat_risk_tolerance"
                data-compat-field="risk_tolerance"
                data-placeholder="Select"
                class="form-control has-icon" data-icon="fa-solid fa-shield-halved">
            <option value="">Select</option>
            <option value="Very Conservative">Very Conservative – I want maximum protections</option>
            <option value="Conservative">Conservative – I prefer low risk</option>
            <option value="Moderate">Moderate – Balanced approach</option>
            <option value="Aggressive">Aggressive – I'm comfortable with risk</option>
            <option value="Very Aggressive">Very Aggressive – I'll waive contingencies to win</option>
        </select>
    </div>
</div>

<!-- 4. Decision-Making Style -->
<div class="form-group">
    <label class="fw-bold">
        Decision-Making Style:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="How do you typically make decisions when purchasing?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div wire:ignore wire:key="compat-decision-select" class="input-cover mt-2 has-select-icon">
        <select id="compat_decision_making_style"
                data-compat-field="decision_making_style"
                data-placeholder="Select"
                class="form-control has-icon" data-icon="fa-solid fa-brain">
            <option value="">Select</option>
            <option value="Quick Decisions">Quick Decisions – I act fast when I see the right property</option>
            <option value="Careful & Deliberate">Careful &amp; Deliberate – I research extensively before deciding</option>
            <option value="Collaborative with Agent">Collaborative – I rely heavily on my agent's guidance</option>
            <option value="Research-Driven">Research-Driven – I want all the data before committing</option>
            <option value="Flexible / Situational">Flexible / Situational – Depends on the property</option>
        </select>
    </div>
</div>

<!-- 5. Timeline Flexibility -->
<div class="form-group">
    <label class="fw-bold">
        Timeline Flexibility:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="How flexible is your timeline for finding and closing on a property?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div wire:ignore wire:key="compat-timeline-select" class="input-cover mt-2 has-select-icon">
        <select id="compat_timeline_flexibility"
                data-compat-field="timeline_flexibility"
                data-placeholder="Select"
                class="form-control has-icon" data-icon="fa-solid fa-calendar-days">
            <option value="">Select</option>
            <option value="Very Flexible">Very Flexible – No rush, I can take my time</option>
            <option value="Somewhat Flexible">Somewhat Flexible – Preferred timeframe but open to adjustments</option>
            <option value="Limited Flexibility">Limited Flexibility – Need to move within a set window</option>
            <option value="Strict Timeline">Strict Timeline – Must close by a specific date</option>
        </select>
    </div>
</div>


<h5 class="fw-bold mt-4 mb-3 border-bottom pb-2">Communication &amp; Working Style</h5>

<!-- 6. Communication Style -->
<div class="form-group">
    <label class="fw-bold">
        Communication Style:<span class="text-danger">*</span>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="How often would you like your agent to provide updates and check in with you?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div wire:ignore wire:key="compat-comm-style-select" class="input-cover mt-2 has-select-icon">
        <select id="compat_communication_style"
                data-compat-field="communication_style"
                data-placeholder="Select"
                class="form-control has-icon" data-icon="fa-solid fa-comments"
                required>
            <option value="">Select</option>
            <option value="Frequent Updates (Daily)">Frequent Updates – Daily check-ins</option>
            <option value="Regular Updates (Every Few Days)">Regular Updates – Every few days</option>
            <option value="Weekly Updates">Weekly Updates – Summarized weekly</option>
            <option value="Only When Necessary">Only When Necessary – Contact me on key milestones</option>
            <option value="As-Needed / On-Demand">As-Needed / On-Demand – I'll reach out when I have questions</option>
        </select>
    </div>
    <span class="error mt-2">@error('compatibility_preferences.buyer_specific.communication_style') {{ $message }} @enderror</span>
</div>

<!-- 7. Preferred Contact Method -->
<div class="form-group">
    <label class="fw-bold">
        Preferred contact method(s):
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select all contact methods that work best for you.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div wire:ignore wire:key="compat-contact-method-select" class="input-cover mt-2 has-select-icon">
        <select id="compat_preferred_contact_method"
                name="compat_preferred_contact_method[]"
                multiple
                class="form-control has-icon select2-multiple" data-icon="fa-solid fa-mobile-screen"
                data-placeholder="Select">
            <option value="Phone Call">Phone Call</option>
            <option value="Text Message">Text Message</option>
            <option value="Email">Email</option>
            <option value="Video Call">Video Call (Zoom / FaceTime)</option>
            <option value="In-Person">In-Person Meetings</option>
            <option value="Any Method">Any Method – I'm flexible</option>
        </select>
    </div>
</div>

<!-- 8. Availability / Best Times to Reach You -->
<div class="form-group">
    <label class="fw-bold">
        Availability / Best Times to Reach You:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Let your agent know when you're generally available for calls, showings, and meetings.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <input type="text"
               wire:model.defer="compatibility_preferences.buyer_specific.availability_windows"
               class="form-control has-icon" data-icon="fa-solid fa-clock"
               placeholder="Enter availability / best times (e.g., Weekday evenings after 6pm, Weekend mornings)">
    </div>
</div>

<!-- 9. Meeting / Showing Preference -->
<div class="form-group">
    <label class="fw-bold">
        Meeting / Showing Preference:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="How would you prefer to attend property showings?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div wire:ignore wire:key="compat-comm-freq-select" class="input-cover mt-2 has-select-icon">
        <select id="compat_communication_frequency"
                data-compat-field="communication_frequency"
                data-placeholder="Select"
                class="form-control has-icon" data-icon="fa-solid fa-house-magnifying-glass">
            <option value="">Select</option>
            <option value="In-Person Only">In-Person Only – I need to walk through every property</option>
            <option value="Virtual Tours Accepted">Virtual Tours Accepted – Video tours are fine for initial screening</option>
            <option value="Agent Pre-Screens for Me">Agent Pre-Screens for Me – Show me only top candidates</option>
            <option value="Flexible / No Preference">Flexible / No Preference</option>
        </select>
    </div>
</div>


<h5 class="fw-bold mt-4 mb-3 border-bottom pb-2">Negotiation &amp; Representation</h5>

<!-- 10. Negotiation Style -->
<div class="form-group">
    <label class="fw-bold">
        Negotiation Style:<span class="text-danger">*</span>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="How would you like your agent to approach negotiations on your behalf?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div wire:ignore wire:key="compat-negotiation-select" class="input-cover mt-2 has-select-icon">
        <select id="compat_negotiation_style"
                data-compat-field="negotiation_style"
                data-placeholder="Select"
                class="form-control has-icon" data-icon="fa-solid fa-scale-balanced"
                required>
            <option value="">Select</option>
            <option value="Aggressive Negotiator">Aggressive – Push hard for the lowest price and best terms</option>
            <option value="Firm but Fair">Firm but Fair – Strong position while maintaining goodwill</option>
            <option value="Collaborative">Collaborative – Work toward a win-win with the seller</option>
            <option value="Offer Full Price to Win">Offer Full Price to Win – Speed and certainty over savings</option>
            <option value="Guided by Agent">Guided by Agent – Trust my agent's expertise on strategy</option>
        </select>
    </div>
    <span class="error mt-2">@error('compatibility_preferences.buyer_specific.negotiation_style') {{ $message }} @enderror</span>
</div>

<!-- 11. Preferred Agent Working Style -->
<div class="form-group"
     x-data="{ showPawsOther: {{ ($compatibility_preferences['buyer_specific']['preferred_agent_working_style'] ?? '') === 'Other' ? 'true' : 'false' }} }"
     @update-paws-other.window="showPawsOther = $event.detail.showOther">
    <label class="fw-bold">
        Preferred Agent Working Style:<span class="text-danger">*</span>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="What type of working dynamic do you prefer with your agent?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div wire:ignore wire:key="compat-paws-select" class="input-cover mt-2 has-select-icon">
        <select id="compat_preferred_agent_working_style"
                data-compat-field="preferred_agent_working_style"
                data-placeholder="Select"
                class="form-control has-icon" data-icon="fa-solid fa-user-tie"
                required>
            <option value="">Select</option>
            <option value="Highly Proactive">Highly Proactive – Agent brings me opportunities before I ask</option>
            <option value="Responsive Partner">Responsive Partner – Agent reacts quickly to my requests</option>
            <option value="Advisor / Consultant">Advisor / Consultant – Agent provides expert guidance, I lead</option>
            <option value="Full-Service Concierge">Full-Service Concierge – Agent handles everything end-to-end</option>
            <option value="Hands-Off Facilitator">Hands-Off Facilitator – Agent opens doors, I manage the process</option>
            <option value="Other">Other</option>
        </select>
    </div>
    <span class="error mt-2">@error('compatibility_preferences.buyer_specific.preferred_agent_working_style') {{ $message }} @enderror</span>
    <div x-show="showPawsOther" class="mt-2" x-cloak>
        <div class="input-cover">
            <input type="text"
                   wire:model.defer="compatibility_preferences.buyer_specific.preferred_agent_working_style_other"
                   class="form-control has-icon" data-icon="fa-solid fa-pen"
                   placeholder="Enter preferred agent working style (e.g., Proactive communicator with strong negotiation skills)">
        </div>
    </div>
</div>

<!-- 12. Expected Level of Support -->
<div class="form-group">
    <label class="fw-bold">
        Expected Level of Agent Support:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="How involved do you expect your agent to be throughout the buying process?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div wire:ignore wire:key="compat-support-select" class="input-cover mt-2 has-select-icon">
        <select id="compat_support_level"
                data-compat-field="support_level"
                data-placeholder="Select"
                class="form-control has-icon" data-icon="fa-solid fa-life-ring">
            <option value="">Select</option>
            <option value="Minimal – Self-Sufficient">Minimal – I'm experienced and mostly self-sufficient</option>
            <option value="Moderate – Key Touchpoints">Moderate – Help me at the key decision points</option>
            <option value="High – Guided Throughout">High – Guide me through every step of the process</option>
            <option value="Full White-Glove Service">Full White-Glove – Manage everything, I'll approve and sign</option>
        </select>
    </div>
</div>

<!-- 13. Non-Negotiable Requirements / Deal Breakers -->
<div class="form-group">
    <label class="fw-bold">
        Non-Negotiable Requirements / Deal Breakers:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="List any absolute must-haves or things that would immediately disqualify a property or agent.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <input type="text"
               wire:model.defer="compatibility_preferences.buyer_specific.deal_breakers"
               class="form-control has-icon" data-icon="fa-solid fa-ban"
               placeholder="Enter non-negotiable requirements (e.g., Must have 3+ bedrooms, No HOA, Available on weekends)">
    </div>
</div>

<!-- 14. Additional Notes -->
<div class="form-group">
    <label class="fw-bold">
        Additional notes for agent:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Share anything else that would help agents understand how to work best with you.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover mt-2">
        <textarea wire:model.defer="compatibility_preferences.buyer_specific.additional_compatibility_notes"
                  class="form-control has-icon"
                  data-icon="fa-solid fa-note-sticky"
                  rows="1"
                  style="min-height: 48px; resize: vertical; padding-top: 10px; padding-bottom: 10px;"
                  maxlength="2000"
                  placeholder="Enter additional notes for agent (e.g., Prefer weekend showings and fast responses)"></textarea>
    </div>
</div>
