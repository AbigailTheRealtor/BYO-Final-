<h3 class="fw-bold mb-3">Representation Preferences &amp; Compatibility</h3>

<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>Help us match you with the right Agent by sharing your leasing goals, communication style, and negotiation preferences.</strong>
        </div>
    </div>
</div>

<!-- Landlord Goals & Leasing Priorities -->
<h5 class="fw-bold mt-4 mb-3 border-bottom pb-2">Landlord Goals &amp; Leasing Priorities</h5>

@php $primaryGoal = $compatibility_preferences['landlord_specific']['primary_leasing_goal'] ?? ''; @endphp
<div class="form-group"
     x-data="{ showOtherPrimaryGoal: {{ $primaryGoal === 'Other' ? 'true' : 'false' }} }">
    <label class="fw-bold">
        Primary Leasing Goal:<span class="text-danger">*</span>
        <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
            title="What is the most important outcome you want from leasing this property?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="compatibility_preferences.landlord_specific.primary_leasing_goal"
                class="form-control has-icon" data-icon="fa-solid fa-bullseye" required
                x-on:change="showOtherPrimaryGoal = $event.target.value === 'Other'">
            <option value="">Select</option>
            <option value="Maximize Monthly Rent">Maximize Monthly Rent</option>
            <option value="Long-Term Stable Tenant">Long-Term Stable Tenant</option>
            <option value="Minimize Vacancy Time">Minimize Vacancy Time</option>
            <option value="High-Quality Tenant Profile">High-Quality Tenant Profile</option>
            <option value="Build Portfolio Cash Flow">Build Portfolio Cash Flow</option>
            <option value="Property Appreciation & Upkeep">Property Appreciation &amp; Upkeep</option>
            <option value="Other">Other</option>
        </select>
    </div>
    <div x-show="showOtherPrimaryGoal" class="mt-2" wire:key="primary-goal-other-wrapper">
        <div class="input-cover">
            <input type="text"
                   wire:model="compatibility_preferences.landlord_specific.primary_leasing_goal_other"
                   class="form-control has-icon" data-icon="fa-solid fa-pen"
                   placeholder="Enter Primary Leasing Goal (e.g., Minimize Vacancy Before Summer)">
        </div>
    </div>
    @error('compatibility_preferences.landlord_specific.primary_leasing_goal')
        <span class="text-danger">{{ $message }}</span>
    @enderror
</div>

@php
    $tenantTypePref = $compatibility_preferences['landlord_specific']['tenant_type_preference'] ?? '';
@endphp
<div class="form-group"
     x-data="{ showOtherTenantType: {{ $tenantTypePref === 'Other' ? 'true' : 'false' }} }">
    <label class="fw-bold">
        Preferred Tenant Type:
        <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
            title="What type of tenant best suits your property?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="compatibility_preferences.landlord_specific.tenant_type_preference"
                class="form-control has-icon" data-icon="fa-solid fa-users"
                x-on:change="showOtherTenantType = $event.target.value === 'Other'">
            <option value="">Select</option>
            <option value="Individual / Family">Individual / Family</option>
            <option value="Young Professionals">Young Professionals</option>
            <option value="Students">Students</option>
            <option value="Corporate / Relocation">Corporate / Relocation</option>
            <option value="Small Business">Small Business</option>
            <option value="Retail Business">Retail Business</option>
            <option value="Office Tenant">Office Tenant</option>
            <option value="No Preference">No Preference</option>
            <option value="Other">Other</option>
        </select>
    </div>
    <div x-show="showOtherTenantType" class="mt-2" wire:key="tenant-type-other-wrapper">
        <div class="input-cover">
            <input type="text"
                   wire:model="compatibility_preferences.landlord_specific.tenant_type_preference_other"
                   class="form-control has-icon" data-icon="fa-solid fa-pen"
                   placeholder="Enter Preferred Tenant Type (e.g., Long-Term Professional Tenant)">
        </div>
    </div>
</div>

<div class="form-group">
    <label class="fw-bold">
        Preferred Lease Duration:
        <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
            title="How long do you prefer lease agreements to last?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="compatibility_preferences.landlord_specific.lease_duration_preference"
                class="form-control has-icon" data-icon="fa-solid fa-calendar-days">
            <option value="">Select</option>
            <option value="Month-to-Month">Month-to-Month</option>
            <option value="3–6 Months">3–6 Months</option>
            <option value="6–12 Months">6–12 Months</option>
            <option value="1 Year">1 Year</option>
            <option value="2+ Years">2+ Years</option>
            <option value="Flexible / Negotiable">Flexible / Negotiable</option>
        </select>
    </div>
</div>

<div class="form-group">
    <label class="fw-bold">
        Level of Involvement in Day-to-Day Management:
        <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
            title="How involved do you expect to be once the property is leased?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="compatibility_preferences.landlord_specific.property_management_involvement"
                class="form-control has-icon" data-icon="fa-solid fa-sliders">
            <option value="">Select</option>
            <option value="Hands-Off (Agent Manages All)">Hands-Off (Agent Manages All)</option>
            <option value="Minimal Involvement">Minimal Involvement</option>
            <option value="Occasional Check-Ins">Occasional Check-Ins</option>
            <option value="Actively Involved">Actively Involved</option>
            <option value="Self-Manage After Placement">Self-Manage After Placement</option>
        </select>
    </div>
</div>

<!-- Communication & Working Style -->
<h5 class="fw-bold mt-4 mb-3 border-bottom pb-2">Communication &amp; Working Style</h5>

<div class="form-group">
    <label class="fw-bold">
        Preferred Communication Style:<span class="text-danger">*</span>
        <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
            title="How do you prefer to communicate with your Agent throughout the leasing process?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="compatibility_preferences.landlord_specific.communication_style"
                class="form-control has-icon" data-icon="fa-solid fa-comments" required>
            <option value="">Select</option>
            <option value="Email Only">Email Only</option>
            <option value="Phone Calls Preferred">Phone Calls Preferred</option>
            <option value="Text / SMS Preferred">Text / SMS Preferred</option>
            <option value="Video Calls Preferred">Video Calls Preferred</option>
            <option value="In-Person Meetings">In-Person Meetings</option>
            <option value="Platform Messaging">Platform Messaging</option>
            <option value="Flexible / Any Method">Flexible / Any Method</option>
        </select>
    </div>
    @error('compatibility_preferences.landlord_specific.communication_style')
        <span class="text-danger">{{ $message }}</span>
    @enderror
</div>

<div class="form-group">
    <label class="fw-bold">
        Preferred Contact Frequency:
        <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
            title="How frequently do you want the Agent to provide updates?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="compatibility_preferences.landlord_specific.preferred_contact_method"
                class="form-control has-icon" data-icon="fa-solid fa-bell">
            <option value="">Select</option>
            <option value="Daily Updates">Daily Updates</option>
            <option value="Every Few Days">Every Few Days</option>
            <option value="Weekly Check-Ins">Weekly Check-Ins</option>
            <option value="Only Major Milestones">Only Major Milestones</option>
            <option value="Only When I Ask">Only When I Ask</option>
        </select>
    </div>
</div>

<div class="form-group">
    <label class="fw-bold">
        Expected Agent Response Time:
        <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
            title="How quickly do you expect your Agent to respond to messages or inquiries?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="compatibility_preferences.landlord_specific.response_time_expectation"
                class="form-control has-icon" data-icon="fa-solid fa-clock">
            <option value="">Select</option>
            <option value="Within 1 Hour">Within 1 Hour</option>
            <option value="Within a Few Hours">Within a Few Hours</option>
            <option value="Same Business Day">Same Business Day</option>
            <option value="Within 24 Hours">Within 24 Hours</option>
            <option value="Within 48 Hours">Within 48 Hours</option>
            <option value="Flexible">Flexible</option>
        </select>
    </div>
</div>

<div class="form-group">
    <label class="fw-bold">
        Preferred Agent Working Style:<span class="text-danger">*</span>
        <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
            title="What type of Agent approach works best for you?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="compatibility_preferences.landlord_specific.preferred_agent_working_style"
                class="form-control has-icon" data-icon="fa-solid fa-user-tie" required>
            <option value="">Select</option>
            <option value="Proactive &amp; Assertive">Proactive &amp; Assertive</option>
            <option value="Consultative &amp; Advisory">Consultative &amp; Advisory</option>
            <option value="Data-Driven &amp; Analytical">Data-Driven &amp; Analytical</option>
            <option value="Relationship-Focused">Relationship-Focused</option>
            <option value="Tech-Forward &amp; Efficient">Tech-Forward &amp; Efficient</option>
            <option value="Traditional &amp; Personalized">Traditional &amp; Personalized</option>
        </select>
    </div>
    @error('compatibility_preferences.landlord_specific.preferred_agent_working_style')
        <span class="text-danger">{{ $message }}</span>
    @enderror
</div>

<!-- Negotiation & Representation -->
<h5 class="fw-bold mt-4 mb-3 border-bottom pb-2">Negotiation &amp; Representation</h5>

<div class="form-group">
    <label class="fw-bold">
        Negotiation Style:<span class="text-danger">*</span>
        <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
            title="How do you prefer your Agent to approach lease negotiations on your behalf?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="compatibility_preferences.landlord_specific.negotiation_style"
                class="form-control has-icon" data-icon="fa-solid fa-handshake" required>
            <option value="">Select</option>
            <option value="Firm on Terms">Firm on Terms</option>
            <option value="Open to Negotiation">Open to Negotiation</option>
            <option value="Collaborative Win-Win">Collaborative Win-Win</option>
            <option value="Market-Rate Anchored">Market-Rate Anchored</option>
            <option value="Flexible Case-by-Case">Flexible Case-by-Case</option>
        </select>
    </div>
    @error('compatibility_preferences.landlord_specific.negotiation_style')
        <span class="text-danger">{{ $message }}</span>
    @enderror
</div>

<div class="form-group" wire:key="representation-priorities-select2">
    <label class="fw-bold">
        Representation Priorities:<span class="text-danger">*</span>
        <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
            title="Select all areas where you most want your Agent to focus their efforts.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover mt-2 has-select-icon" wire:ignore wire:key="compat-rp-landlord-s2">
        @php
            $rpSelected = $compatibility_preferences['landlord_specific']['representation_priorities'] ?? [];
            $rpOptions = [
                'Tenant Screening & Vetting',
                'Marketing & Advertising',
                'Lease Negotiation',
                'Legal & Lease Documentation',
                'Showings & Open Houses',
                'Market Pricing Guidance',
                'Move-In Coordination',
                'Ongoing Communication & Updates',
            ];
        @endphp
        <select id="compat_representation_priorities_landlord" name="compat_representation_priorities_landlord" multiple
                data-select2="true"
                class="form-control has-icon" data-icon="fa-solid fa-list-check">
            @foreach ($rpOptions as $rpOpt)
                <option value="{{ $rpOpt }}" {{ in_array($rpOpt, $rpSelected) ? 'selected' : '' }}>
                    {{ $rpOpt }}
                </option>
            @endforeach
        </select>
    </div>
    @error('compatibility_preferences.landlord_specific.representation_priorities')
        <span class="text-danger">{{ $message }}</span>
    @enderror
</div>

<div class="form-group">
    <label class="fw-bold">
        Risk Tolerance:
        <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
            title="How comfortable are you with leasing risk, such as accepting tenants with less-than-perfect credit?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="compatibility_preferences.landlord_specific.risk_tolerance"
                class="form-control has-icon" data-icon="fa-solid fa-shield-halved">
            <option value="">Select</option>
            <option value="Low – Strict Screening Only">Low – Strict Screening Only</option>
            <option value="Moderate – Standard Criteria">Moderate – Standard Criteria</option>
            <option value="Flexible – Case-by-Case">Flexible – Case-by-Case</option>
            <option value="High – Willing to Work With Most Tenants">High – Willing to Work With Most Tenants</option>
        </select>
    </div>
</div>

<div class="form-group">
    <label class="fw-bold">
        Willingness to Offer Concessions:
        <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
            title="Are you open to offering incentives such as first month free, reduced deposit, or rent discounts?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="compatibility_preferences.landlord_specific.concessions_willingness"
                class="form-control has-icon" data-icon="fa-solid fa-gift">
            <option value="">Select</option>
            <option value="Not Open to Concessions">Not Open to Concessions</option>
            <option value="Open to Minor Concessions">Open to Minor Concessions</option>
            <option value="Willing to Negotiate Concessions">Willing to Negotiate Concessions</option>
            <option value="Actively Offering Concessions">Actively Offering Concessions</option>
        </select>
    </div>
</div>

<div class="form-group">
    <label class="fw-bold">
        Flexibility on Lease Terms:
        <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
            title="How flexible are you on adjusting lease terms to secure a qualified tenant?">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="compatibility_preferences.landlord_specific.lease_terms_flexibility"
                class="form-control has-icon" data-icon="fa-solid fa-scale-balanced">
            <option value="">Select</option>
            <option value="Firm – Standard Terms Only">Firm – Standard Terms Only</option>
            <option value="Somewhat Flexible">Somewhat Flexible</option>
            <option value="Very Flexible">Very Flexible</option>
            <option value="Fully Negotiable">Fully Negotiable</option>
        </select>
    </div>
</div>

<div class="form-group">
    <label class="fw-bold">
        Additional Notes on Representation Preferences:
        <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
            title="Share any other context that will help Agents understand how best to represent you.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover mt-2">
        <textarea wire:model="compatibility_preferences.landlord_specific.additional_representation_notes"
                  class="form-control" rows="3"
                  placeholder="Enter Additional Representation Notes (e.g., Prefer Weekly Leasing Updates and Strong Tenant Screening)"></textarea>
    </div>
</div>
