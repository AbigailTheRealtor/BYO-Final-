{{--
    Select2 fields here use wire:ignore + JS @this.set() — NOT wire:model directly.
    This is the established platform pattern for Select2 throughout this wizard
    (identical to #appliances, #non_negotiable_amenities, #exchange_item, etc.).
    wire:model on a <select> inside a wire:ignore region is not processed by Livewire
    on re-renders, making @this.set() the correct and only reliable binding mechanism.
    All values are synced to Livewire on change and rehydrated via initCompatSelect2Fields()
    on message.processed / draftLoaded — providing full bidirectional state management.
--}}
<h3>Representation Preferences &amp; Compatibility</h3>

<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>🤝 Help us match you with the right agent. These questions help us understand your communication style, negotiation approach, and working preferences so agents can tailor their bid to you.</strong>
        </div>
    </div>
</div>

{{-- ===== Section 1: Communication Preferences ===== --}}
<h5 class="section-header bg-info text-white p-2 mb-3">1. Communication Preferences</h5>

{{-- Communication Style (required, Select2 single) --}}
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        How would you describe your preferred communication style?
        <span class="text-danger ms-1">*</span>
        <span data-bs-toggle="tooltip" data-bs-html="true"
            title="This helps agents understand how often and through what channel you prefer to communicate."
            class="ms-2 cursor-pointer">
            <i class="fa-solid fa-circle-info text-muted"></i>
        </span>
    </label>
    <div wire:key="compat-cs-s2" wire:ignore class="mt-2">
        <select id="compat_communication_style" class="form-control" required>
            <option value="">Select</option>
            <option value="Frequent & Proactive">Frequent &amp; Proactive — I like regular updates</option>
            <option value="As-Needed Updates">As-Needed Updates — Contact me when something important comes up</option>
            <option value="Available On-Demand">Available On-Demand — I'll reach out when I have questions</option>
            <option value="Structured Check-Ins">Structured Check-Ins — Scheduled meetings/calls at agreed intervals</option>
        </select>
    </div>
    @error('compatibility_preferences.seller_specific.communication_style')
        <div class="text-danger mt-1 small">{{ $message }}</div>
    @enderror
</div>

{{-- Preferred Contact Method (multi-select) --}}
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Preferred Contact Method(s)
        <span data-bs-toggle="tooltip" data-bs-html="true"
            title="Select all contact methods that work best for you."
            class="ms-2 cursor-pointer">
            <i class="fa-solid fa-circle-info text-muted"></i>
        </span>
    </label>
    <div wire:key="compat-pcm-s2" wire:ignore class="input-cover mt-2 has-select-icon">
        <select id="compat_preferred_contact_method" multiple class="form-control has-icon" data-icon="fa-solid fa-phone">
            <option value="Phone Call">Phone Call</option>
            <option value="Text/SMS">Text / SMS</option>
            <option value="Email">Email</option>
            <option value="Video Call">Video Call</option>
            <option value="In-Person Meeting">In-Person Meeting</option>
        </select>
    </div>
</div>

{{-- Response Time Expectation (Select2 single) --}}
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Expected Agent Response Time
        <span data-bs-toggle="tooltip" data-bs-html="true"
            title="How quickly do you expect your agent to respond to messages?"
            class="ms-2 cursor-pointer">
            <i class="fa-solid fa-circle-info text-muted"></i>
        </span>
    </label>
    <div wire:key="compat-rte-s2" wire:ignore class="mt-2">
        <select id="compat_response_time_expectation" class="form-control">
            <option value="">Select</option>
            <option value="Within 1 Hour">Within 1 Hour</option>
            <option value="Within a Few Hours">Within a Few Hours</option>
            <option value="Same Day">Same Day</option>
            <option value="Next Business Day">Next Business Day</option>
        </select>
    </div>
</div>

{{-- ===== Section 2: Negotiation Style ===== --}}
<h5 class="section-header bg-info text-white p-2 mb-3 mt-4">2. Negotiation Style</h5>

{{-- Negotiation Style (required, Select2 single) --}}
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        How would you describe your negotiation style?
        <span class="text-danger ms-1">*</span>
        <span data-bs-toggle="tooltip" data-bs-html="true"
            title="This helps the agent align their negotiation approach with your expectations."
            class="ms-2 cursor-pointer">
            <i class="fa-solid fa-circle-info text-muted"></i>
        </span>
    </label>
    <div wire:key="compat-ns-s2" wire:ignore class="mt-2">
        <select id="compat_negotiation_style" class="form-control" required>
            <option value="">Select</option>
            <option value="Aggressive — Push for Maximum Profit">Aggressive — Push for Maximum Profit</option>
            <option value="Balanced — Fair & Reasonable">Balanced — Fair &amp; Reasonable</option>
            <option value="Flexible — Prioritize Quick Sale">Flexible — Prioritize Quick Sale</option>
            <option value="Collaborative — Seller & Buyer Both Win">Collaborative — Seller &amp; Buyer Both Win</option>
        </select>
    </div>
    @error('compatibility_preferences.seller_specific.negotiation_style')
        <div class="text-danger mt-1 small">{{ $message }}</div>
    @enderror
</div>

{{-- Willing to Negotiate On (multi-select) --}}
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Areas You Are Willing to Negotiate On
        <span data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the transaction elements where you are open to negotiation."
            class="ms-2 cursor-pointer">
            <i class="fa-solid fa-circle-info text-muted"></i>
        </span>
    </label>
    <div wire:key="compat-wtn-s2" wire:ignore class="input-cover mt-2 has-select-icon">
        <select id="compat_willing_to_negotiate_on" multiple class="form-control has-icon" data-icon="fa-solid fa-handshake">
            <option value="Price Reductions">Price Reductions</option>
            <option value="Closing Costs">Closing Costs</option>
            <option value="Repairs / Credits">Repairs / Credits</option>
            <option value="Possession Date">Possession Date</option>
            <option value="Contingency Waivers">Contingency Waivers</option>
            <option value="Inclusions / Exclusions">Inclusions / Exclusions</option>
            <option value="Not Open to Negotiation">Not Open to Negotiation</option>
        </select>
    </div>
</div>

{{-- Firm on Price (Select2 single) --}}
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Are You Firm on Your Asking Price?
        <span data-bs-toggle="tooltip" data-bs-html="true"
            title="Helps the agent understand how firm your listing price expectations are."
            class="ms-2 cursor-pointer">
            <i class="fa-solid fa-circle-info text-muted"></i>
        </span>
    </label>
    <div wire:key="compat-fop-s2" wire:ignore class="mt-2">
        <select id="compat_firm_on_price" class="form-control">
            <option value="">Select</option>
            <option value="Yes — Firm on Price">Yes — Firm on Price</option>
            <option value="Somewhat — Open to Reasonable Offers">Somewhat — Open to Reasonable Offers</option>
            <option value="Flexible — Willing to Negotiate Significantly">Flexible — Willing to Negotiate Significantly</option>
        </select>
    </div>
</div>

{{-- ===== Section 3: Primary Transaction Goal ===== --}}
<h5 class="section-header bg-info text-white p-2 mb-3 mt-4">3. Primary Transaction Goal</h5>

{{-- Primary Goal (required, Select2 single) --}}
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        What is your primary goal for this sale?
        <span class="text-danger ms-1">*</span>
        <span data-bs-toggle="tooltip" data-bs-html="true"
            title="Knowing your primary goal helps the agent prioritize the right strategies."
            class="ms-2 cursor-pointer">
            <i class="fa-solid fa-circle-info text-muted"></i>
        </span>
    </label>
    <div wire:key="compat-ptg-s2" wire:ignore class="mt-2">
        <select id="compat_primary_transaction_goal" class="form-control" required>
            <option value="">Select</option>
            <option value="Maximum Sale Price">Maximum Sale Price</option>
            <option value="Quick Sale">Quick Sale</option>
            <option value="Minimal Disruption">Minimal Disruption to Daily Life</option>
            <option value="Specific Closing Timeline">Meet a Specific Closing Timeline</option>
            <option value="Other">Other</option>
        </select>
    </div>
    @error('compatibility_preferences.seller_specific.primary_transaction_goal')
        <div class="text-danger mt-1 small">{{ $message }}</div>
    @enderror
</div>

{{-- Other: Primary Transaction Goal — companion input, shown only when "Other" is selected --}}
<div class="form-group mb-4" id="compat_ptg_other_wrapper"
    style="display: {{ ($compatibility_preferences['seller_specific']['primary_transaction_goal'] ?? '') === 'Other' ? '' : 'none' }}">
    <label class="fw-bold d-flex align-items-center">
        Please Describe Your Primary Goal
        <span class="text-danger ms-1">*</span>
        <span data-bs-toggle="tooltip" data-bs-html="true"
            title="Since you selected Other, please briefly describe your primary goal for this sale."
            class="ms-2 cursor-pointer">
            <i class="fa-solid fa-circle-info text-muted"></i>
        </span>
    </label>
    <div class="input-cover mt-2">
        <input type="text"
            wire:model="compatibility_preferences.seller_specific.primary_transaction_goal_other"
            id="compat_primary_transaction_goal_other"
            class="form-control has-icon" data-icon="fa-solid fa-bullseye"
            placeholder="Enter Primary Transaction Goal (e.g., sell quickly to relocate, maximize equity)">
    </div>
</div>

{{-- Target Sale Timeline --}}
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Target Sale Timeline
        <span data-bs-toggle="tooltip" data-bs-html="true"
            title="Approximate timeframe within which you hope to complete the sale."
            class="ms-2 cursor-pointer">
            <i class="fa-solid fa-circle-info text-muted"></i>
        </span>
    </label>
    <div class="input-cover mt-2">
        <input type="text"
            wire:model="compatibility_preferences.seller_specific.target_sale_timeline"
            class="form-control has-icon" data-icon="fa-solid fa-calendar-days"
            placeholder="Enter Target Sale Timeline (e.g., 30–60 days, 3 months)">
    </div>
</div>

{{-- Flexibility on Timeline (Select2 single) --}}
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        How Flexible Are You on the Timeline?
        <span data-bs-toggle="tooltip" data-bs-html="true"
            title="Lets the agent know how strictly you need to adhere to your target timeline."
            class="ms-2 cursor-pointer">
            <i class="fa-solid fa-circle-info text-muted"></i>
        </span>
    </label>
    <div wire:key="compat-fot-s2" wire:ignore class="mt-2">
        <select id="compat_flexibility_on_timeline" class="form-control">
            <option value="">Select</option>
            <option value="Very Flexible">Very Flexible</option>
            <option value="Somewhat Flexible">Somewhat Flexible</option>
            <option value="Firm on Timeline">Firm on Timeline</option>
        </select>
    </div>
</div>

{{-- Post-Sale Plan (Select2 single) --}}
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Post-Sale Plans
        <span data-bs-toggle="tooltip" data-bs-html="true"
            title="Understanding your plans after the sale can help the agent coordinate the closing timeline."
            class="ms-2 cursor-pointer">
            <i class="fa-solid fa-circle-info text-muted"></i>
        </span>
    </label>
    <div wire:key="compat-psp-s2" wire:ignore class="mt-2">
        <select id="compat_post_sale_plan" class="form-control">
            <option value="">Select</option>
            <option value="Purchasing Another Property">Purchasing Another Property</option>
            <option value="Renting">Renting</option>
            <option value="Relocating Out of Area">Relocating Out of Area</option>
            <option value="Moving to Family / Friends">Moving to Family / Friends</option>
            <option value="Undecided">Undecided</option>
        </select>
    </div>
</div>

{{-- ===== Section 4: Representation Priorities ===== --}}
<h5 class="section-header bg-info text-white p-2 mb-3 mt-4">4. Representation Priorities</h5>

{{-- Representation Priorities (required, multi-select) --}}
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        What matters most in an agent? (Select all that apply)
        <span class="text-danger ms-1">*</span>
        <span data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the qualities that are most important to you when choosing an agent to represent you."
            class="ms-2 cursor-pointer">
            <i class="fa-solid fa-circle-info text-muted"></i>
        </span>
    </label>
    <div wire:key="compat-rp-s2" wire:ignore class="input-cover mt-2 has-select-icon">
        <select id="compat_representation_priorities" multiple class="form-control has-icon" data-icon="fa-solid fa-list-check" required>
            <option value="Market Expertise">Market Expertise</option>
            <option value="Strong Negotiator">Strong Negotiator</option>
            <option value="High Communication">High Communication &amp; Responsiveness</option>
            <option value="Local Connections">Local Connections &amp; Network</option>
            <option value="Marketing Strategy">Marketing Strategy</option>
            <option value="Staging / Presentation Expertise">Staging / Presentation Expertise</option>
            <option value="Digital Marketing">Digital &amp; Social Media Marketing</option>
            <option value="Transaction Management">Transaction Management &amp; Coordination</option>
        </select>
    </div>
    @error('compatibility_preferences.seller_specific.representation_priorities')
        <div class="text-danger mt-1 small">{{ $message }}</div>
    @enderror
</div>

{{-- Qualities Most Important (multi-select) --}}
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Agent Qualities Most Important to You
        <span data-bs-toggle="tooltip" data-bs-html="true"
            title="Beyond skills, what personal qualities do you value most in an agent?"
            class="ms-2 cursor-pointer">
            <i class="fa-solid fa-circle-info text-muted"></i>
        </span>
    </label>
    <div wire:key="compat-qmi-s2" wire:ignore class="input-cover mt-2 has-select-icon">
        <select id="compat_qualities_most_important" multiple class="form-control has-icon" data-icon="fa-solid fa-star">
            <option value="Honesty & Transparency">Honesty &amp; Transparency</option>
            <option value="Patience">Patience</option>
            <option value="Assertiveness">Assertiveness</option>
            <option value="Attention to Detail">Attention to Detail</option>
            <option value="Tech-Savvy">Tech-Savvy</option>
            <option value="Empathy">Empathy</option>
            <option value="Proactivity">Proactivity</option>
        </select>
    </div>
</div>

{{-- Past Agent Experience (Select2 single) --}}
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Past Experience Working with a Real Estate Agent
        <span data-bs-toggle="tooltip" data-bs-html="true"
            title="Have you worked with an agent before, and how did it go?"
            class="ms-2 cursor-pointer">
            <i class="fa-solid fa-circle-info text-muted"></i>
        </span>
    </label>
    <div wire:key="compat-pae-s2" wire:ignore class="mt-2">
        <select id="compat_past_agent_experience" class="form-control">
            <option value="">Select</option>
            <option value="First Time Working with Agent">First Time Working with an Agent</option>
            <option value="Positive Experience">Positive Experience with Past Agent(s)</option>
            <option value="Negative Experience">Negative Experience with Past Agent(s)</option>
            <option value="Mixed Experience">Mixed Experience</option>
        </select>
    </div>
</div>

{{-- What Did Not Work Before --}}
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        What Did Not Work Well with Past Agents? (Optional)
        <span data-bs-toggle="tooltip" data-bs-html="true"
            title="Briefly describe what went wrong so future agents can avoid it."
            class="ms-2 cursor-pointer">
            <i class="fa-solid fa-circle-info text-muted"></i>
        </span>
    </label>
    <div class="mt-2">
        <textarea wire:model="compatibility_preferences.seller_specific.what_did_not_work_before"
            class="form-control" rows="3"
            placeholder="Enter What Did Not Work Well with Past Agents (e.g., poor communication, overpriced listing)"></textarea>
    </div>
</div>

{{-- ===== Section 5: Decision-Making Style ===== --}}
<h5 class="section-header bg-info text-white p-2 mb-3 mt-4">5. Decision-Making Style</h5>

{{-- Decision Making Style (Select2 single) --}}
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        How do you typically make decisions?
        <span data-bs-toggle="tooltip" data-bs-html="true"
            title="This helps agents understand your pace and style when reviewing offers or making decisions."
            class="ms-2 cursor-pointer">
            <i class="fa-solid fa-circle-info text-muted"></i>
        </span>
    </label>
    <div wire:key="compat-dms-s2" wire:ignore class="mt-2">
        <select id="compat_decision_making_style" class="form-control">
            <option value="">Select</option>
            <option value="Independent — I Decide Quickly">Independent — I Decide Quickly</option>
            <option value="Collaborative — I Value Agent Input">Collaborative — I Value Agent Input</option>
            <option value="Cautious — I Need Time to Think">Cautious — I Need Time to Think</option>
            <option value="Data-Driven — Show Me the Numbers">Data-Driven — Show Me the Numbers</option>
        </select>
    </div>
</div>

{{-- Involvement Level (Select2 single) --}}
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        How involved do you want to be in the process?
        <span data-bs-toggle="tooltip" data-bs-html="true"
            title="Helps agents calibrate how frequently to loop you in on decisions."
            class="ms-2 cursor-pointer">
            <i class="fa-solid fa-circle-info text-muted"></i>
        </span>
    </label>
    <div wire:key="compat-il-s2" wire:ignore class="mt-2">
        <select id="compat_involvement_level" class="form-control">
            <option value="">Select</option>
            <option value="Very Involved — I want to be part of every decision">Very Involved — Part of every decision</option>
            <option value="Moderately Involved — Keep me informed on major steps">Moderately Involved — Major steps only</option>
            <option value="Mostly Hands-Off — I trust my agent to handle it">Mostly Hands-Off — I trust my agent</option>
        </select>
    </div>
</div>

{{-- Additional Decision Makers --}}
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Other Decision Makers Involved (Optional)
        <span data-bs-toggle="tooltip" data-bs-html="true"
            title="List anyone else involved in the final selling decision (e.g., co-owner, spouse, financial advisor)."
            class="ms-2 cursor-pointer">
            <i class="fa-solid fa-circle-info text-muted"></i>
        </span>
    </label>
    <div class="input-cover mt-2">
        <input type="text"
            wire:model="compatibility_preferences.seller_specific.additional_decision_makers"
            class="form-control has-icon" data-icon="fa-solid fa-users"
            placeholder="Enter Other Decision Makers Involved (e.g., Spouse, Co-owner, Financial Advisor)">
    </div>
</div>

{{-- ===== Section 6: Working Style Preferences ===== --}}
<h5 class="section-header bg-info text-white p-2 mb-3 mt-4">6. Working Style Preferences</h5>

{{-- Preferred Agent Working Style (required, Select2 single) --}}
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        What working style do you prefer in an agent?
        <span class="text-danger ms-1">*</span>
        <span data-bs-toggle="tooltip" data-bs-html="true"
            title="Choose the approach that best matches how you like to work with your agent."
            class="ms-2 cursor-pointer">
            <i class="fa-solid fa-circle-info text-muted"></i>
        </span>
    </label>
    <div wire:key="compat-paws-s2" wire:ignore class="mt-2">
        <select id="compat_preferred_agent_working_style" class="form-control" required>
            <option value="">Select</option>
            <option value="Proactive & Takes Initiative">Proactive &amp; Takes Initiative — Anticipates needs before I ask</option>
            <option value="Consultative & Guides Me">Consultative &amp; Guides Me — Explains options and leads me through decisions</option>
            <option value="Responsive & Available">Responsive &amp; Available — I reach out and they respond promptly</option>
            <option value="Process-Oriented & Detail-Focused">Process-Oriented &amp; Detail-Focused — Thorough, organized, and precise</option>
        </select>
    </div>
    @error('compatibility_preferences.seller_specific.preferred_agent_working_style')
        <div class="text-danger mt-1 small">{{ $message }}</div>
    @enderror
</div>

{{-- Showing Availability (multi-select) --}}
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Showing Availability
        <span data-bs-toggle="tooltip" data-bs-html="true"
            title="When are you generally available to allow property showings?"
            class="ms-2 cursor-pointer">
            <i class="fa-solid fa-circle-info text-muted"></i>
        </span>
    </label>
    <div wire:key="compat-sa-s2" wire:ignore class="input-cover mt-2 has-select-icon">
        <select id="compat_showing_availability" multiple class="form-control has-icon" data-icon="fa-solid fa-calendar-days">
            <option value="Weekday Mornings">Weekday Mornings</option>
            <option value="Weekday Afternoons">Weekday Afternoons</option>
            <option value="Weekday Evenings">Weekday Evenings</option>
            <option value="Weekend Mornings">Weekend Mornings</option>
            <option value="Weekend Afternoons">Weekend Afternoons</option>
            <option value="Weekend Evenings">Weekend Evenings</option>
            <option value="Flexible / Anytime">Flexible / Anytime</option>
        </select>
    </div>
</div>

{{-- Open House Preference (Select2 single) --}}
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Open House Preference
        <span data-bs-toggle="tooltip" data-bs-html="true"
            title="Let the agent know your preference around hosting open houses."
            class="ms-2 cursor-pointer">
            <i class="fa-solid fa-circle-info text-muted"></i>
        </span>
    </label>
    <div wire:key="compat-ohp-s2" wire:ignore class="mt-2">
        <select id="compat_open_house_preference" class="form-control">
            <option value="">Select</option>
            <option value="Strongly Prefer Open Houses">Strongly Prefer Open Houses</option>
            <option value="Open to It">Open to It</option>
            <option value="Prefer Not To">Prefer Not To</option>
            <option value="No Open Houses">No Open Houses</option>
        </select>
    </div>
</div>

{{-- Additional Compatibility Notes --}}
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Additional Compatibility Notes (Optional)
        <span data-bs-toggle="tooltip" data-bs-html="true"
            title="Anything else you would like agents to know about how you prefer to work?"
            class="ms-2 cursor-pointer">
            <i class="fa-solid fa-circle-info text-muted"></i>
        </span>
    </label>
    <div class="mt-2">
        <textarea wire:model="compatibility_preferences.seller_specific.additional_compatibility_notes"
            class="form-control" rows="4"
            placeholder="Enter Additional Compatibility Notes (e.g., prefer a bilingual agent, need flexible scheduling)"></textarea>
    </div>
</div>
