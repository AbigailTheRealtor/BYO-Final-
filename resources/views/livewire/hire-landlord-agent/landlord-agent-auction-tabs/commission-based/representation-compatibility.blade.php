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
            {{-- Every option names an OUTCOME the landlord wants from the lease. None grades a
                 person. "High-Quality Tenant Profile" was removed for that reason (it has no
                 objective referent and reads as a euphemism), and "Long-Term Stable Tenant"
                 became "Long-Term Tenancy" so the goal describes the lease rather than the
                 occupant. Both retired values are remapped by hireagent:retire-tenant-type. --}}
            <option value="">Select</option>
            <option value="Maximize Monthly Rent">Maximize Monthly Rent</option>
            <option value="Long-Term Tenancy">Long-Term Tenancy</option>
            <option value="Minimize Vacancy Time">Minimize Vacancy Time</option>
            <option value="Reliable Rent Collection">Reliable Rent Collection</option>
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
                   placeholder="Enter primary leasing goal (e.g., Minimize vacancy before summer)">
        </div>
    </div>
    @error('compatibility_preferences.landlord_specific.primary_leasing_goal')
        <span class="text-danger">{{ $message }}</span>
    @enderror
</div>

{{--
    PREFERRED BUSINESS USE — COMMERCIAL LISTINGS ONLY.

    This replaces "Preferred Tenant Type", which was retired for Fair Housing reasons. That
    field offered occupant categories (Individual / Family, Young Professionals, Students) and
    business categories (Office Tenant, Retail Business) in ONE control, rendered on residential
    and commercial listings alike, and published the answer on a route with no auth middleware —
    a housing provider's stated preference about who may live somewhere, on the open web.

    Residential gets NO replacement. There is deliberately no question anywhere in this partial
    asking a landlord what kind of person, household, age group, family structure or profession
    they would prefer as an occupant.

    THIS @if IS CONVENIENCE, NOT SECURITY. Blade cannot stop a crafted Livewire request from
    setting a public array property, so what actually keeps these keys off a residential listing
    is CompatibilityPreferencePolicy at the persist. See config/hire_agent_compatibility_keys.php.

    NOT THE SAME AS "Zoning Allows" IN LEASING TERMS, and the two must stay separate: zoning is
    an objective legal constraint on the premises, this is the landlord's marketing preference,
    and a landlord may legitimately prefer a narrower use than zoning permits.
--}}
@if (($property_type ?? '') === 'Commercial Property')
    @php
        $businessUseOptions  = config('landlord_business_use_options.options', []);
        $businessUseOther    = config('landlord_business_use_options.other_sentinel', 'Other');
        $businessUseSelected = $compatibility_preferences['landlord_specific']['preferred_business_use'] ?? [];
        $businessUseSelected = is_array($businessUseSelected) ? $businessUseSelected : [];
    @endphp
    <div class="form-group" wire:key="preferred-business-use-group"
         x-data="{ showOtherBusinessUse: {{ in_array($businessUseOther, $businessUseSelected, true) ? 'true' : 'false' }} }"
         @update-business-use-other.window="showOtherBusinessUse = $event.detail.hasOther">
        <label class="fw-bold">
            Preferred Business Use:
            <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
                title="What business uses would you prefer your agent to target for this commercial property, subject to zoning, property restrictions, and applicable law?">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover mt-2 has-select-icon" wire:ignore wire:key="compat-pbu-landlord-s2">
            <select id="compat_preferred_business_use_landlord"
                    name="compat_preferred_business_use_landlord" multiple
                    class="form-control has-icon select2-multiple" data-icon="fa-solid fa-briefcase"
                    data-placeholder="Select">
                @foreach ($businessUseOptions as $businessUseOpt)
                    <option value="{{ $businessUseOpt }}"
                        {{ in_array($businessUseOpt, $businessUseSelected, true) ? 'selected' : '' }}>
                        {{ $businessUseOpt }}
                    </option>
                @endforeach
            </select>
        </div>
        <div x-show="showOtherBusinessUse" class="mt-2" x-cloak wire:key="business-use-other-wrapper">
            <div class="input-cover">
                <input type="text"
                       wire:model="compatibility_preferences.landlord_specific.preferred_business_use_other"
                       class="form-control has-icon" data-icon="fa-solid fa-pen"
                       placeholder="Enter business use (e.g., Veterinary clinic, Self-storage)">
            </div>
        </div>
        @error('compatibility_preferences.landlord_specific.preferred_business_use')
            <span class="text-danger">{{ $message }}</span>
        @enderror
        @error('compatibility_preferences.landlord_specific.preferred_business_use.*')
            <span class="text-danger">{{ $message }}</span>
        @enderror
    </div>
@endif

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
            <option value="Weekly">Weekly Check-Ins</option>
            <option value="At Key Milestones">Only Major Milestones</option>
            <option value="As Needed">Only When I Ask</option>
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
                class="form-control has-icon select2-multiple" data-icon="fa-solid fa-list-check"
                data-placeholder="Select">
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

{{--
    APPLICANT SCREENING APPROACH — was "Risk Tolerance".

    The old question asked how much leasing RISK a landlord would tolerate, illustrated with
    "accepting tenants with less-than-perfect credit", and offered "Flexible – Case-by-Case" and
    "High – Willing to Work With Most Tenants". Two problems. Credit and rental-history leniency
    correlate with race, disability and source of income, so a published tolerance level is a
    disparate-impact surface. And discretion is the wrong thing to advertise: a defensible
    screening policy is one applied uniformly, so "case-by-case" describes the posture that gets
    a landlord into trouble rather than out of it.

    The question is now about METHOD. It asks how criteria are applied, not how much risk is
    acceptable, and every option is a process a landlord can point to afterwards.

    NEW KEY, not new options on the old one: the stored values do not map onto these choices, and
    a shared key would render "High – Willing to Work With Most Tenants" straight back to the
    owner. `risk_tolerance` is off the landlord allowlist, so stored values stop being written on
    the next save and are rendered nowhere. (Buyer keeps its own `risk_tolerance` — that one is
    about offer strategy and is unrelated.)
--}}
<div class="form-group">
    <label class="fw-bold">
        Applicant Screening Approach:
        <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
            title="How would you like applicant screening to be handled? Screening criteria must be applied consistently to every applicant.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="compatibility_preferences.landlord_specific.applicant_screening_approach"
                class="form-control has-icon" data-icon="fa-solid fa-clipboard-check">
            <option value="">Select</option>
            <option value="Written criteria, applied uniformly">Written criteria, applied uniformly</option>
            <option value="Written criteria, with a documented exception process">Written criteria, with a documented exception process</option>
            <option value="I want my agent to recommend screening criteria">I want my agent to recommend screening criteria</option>
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
                  class="form-control has-icon"
                  data-icon="fa-solid fa-note-sticky"
                  rows="1"
                  style="min-height: 48px; resize: vertical; padding-top: 10px; padding-bottom: 10px;"
                  maxlength="2000"
                  placeholder="Enter additional representation notes (e.g., Prefer weekly leasing updates and strong tenant screening)"></textarea>
    </div>
</div>
