{{-- ================================================================
     APPLICANT REQUIREMENTS TAB
     Landlord Offer Listing — Commission-Based (Create & Edit)
     Tab index: 4 (Create) / 10 (Edit)
     EAV keys (existing): min_credit_score, custom_credit_score_requirement,
             income_qualification_method, min_monthly_income_fixed,
             custom_income_requirement, employment_requirement,
             custom_employment_requirement, eviction_history_requirement,
             custom_eviction_requirement, bankruptcy_requirement,
             custom_bankruptcy_requirement, est_water_sewer_trash,
             est_electric, est_internet, est_cable
     EAV keys (new): credit_score_flexibility, pet_policy_requirement,
             custom_pet_policy_requirement, pet_restrictions,
             smoking_policy_requirement, custom_smoking_policy_requirement,
             criminal_background_requirement, custom_criminal_background_requirement,
             reference_requirement, custom_reference_requirement,
             employment_verification_requirement, income_verification_requirement,
             preferred_move_in_timeframe, custom_preferred_move_in_timeframe
     ============================================================== --}}

<div class="tab-content-inner">
    <h5 class="mb-4"><i class="fa-solid fa-user-check me-2"></i>Applicant Requirements</h5>
    <p class="text-muted mb-4">Set the qualification criteria for prospective tenants. All fields are optional — only fill in requirements you wish to enforce.</p>

    {{-- ===== SECTION: TENANCY CONDITIONS ===== --}}
    <h6 class="fw-semibold border-bottom pb-2 mb-3">Tenancy conditions</h6>

    {{-- Minimum Income Requirement (relocated from Leasing Terms) --}}
    <div class="form-group">
        <label class="fw-bold">Minimum monthly income requirement:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the minimum monthly income required for tenant qualification (e.g., 6000).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>
            <input type="text" wire:model="min_income_requirement" class="form-control"
                placeholder="Enter minimum monthly income (e.g., 6000)"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
        </div>
    </div>

    {{-- Number of Occupants Allowed (relocated from Leasing Terms) --}}
    <div class="form-group">
        <label class="fw-bold">Number of occupants allowed:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the maximum number of occupants permitted to live in the property under the lease.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="number_of_occupants_allowed" class="form-control has-icon"
                data-icon="fa-solid fa-users" min="1" placeholder="Enter max number of occupants (e.g., 4)">
        </div>
    </div>

    {{-- Landlord Approval Conditions (relocated from Leasing Terms) --}}
    <div class="form-group">
        <label class="fw-bold">Landlord approval conditions:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="List any conditions or requirements the tenant must meet for the landlord to approve the lease.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <textarea wire:model="landlord_approval_conditions" class="form-control has-icon landlord-compact-textarea" rows="1"
                data-icon="fa-solid fa-clipboard-check"
                placeholder="Enter approval conditions (e.g., Credit score 650+, Income 3x monthly rent, No prior evictions)"></textarea>
        </div>
    </div>

    {{-- ===== SECTION: CREDIT & FINANCIAL REQUIREMENTS ===== --}}
    <h6 class="fw-semibold border-bottom pb-2 mb-3 mt-4">Credit &amp; financial requirements</h6>

    {{-- Minimum Credit Score --}}
    <div class="form-group" x-data>
        <label class="fw-bold">Minimum credit score:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the minimum credit score band required for tenant qualification.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="min_credit_score" class="form-control has-icon" data-icon="fa-solid fa-chart-line">
                <option value="">Select</option>
                <option value="No requirement">No requirement</option>
                <option value="Below 500">Below 500</option>
                <option value="500–549">500–549</option>
                <option value="550–599">550–599</option>
                <option value="600–649">600–649</option>
                <option value="650–699">650–699</option>
                <option value="700–749">700–749</option>
                <option value="750–799">750–799</option>
                <option value="800+">800+</option>
                <option value="Other">Other</option>
            </select>
        </div>
        {{-- Conditional: custom credit score requirement --}}
        <div x-show="$wire.min_credit_score === 'Other'" x-cloak class="mt-2">
            <div class="input-cover">
                <input type="text" wire:model="custom_credit_score_requirement" class="form-control has-icon"
                    data-icon="fa-solid fa-chart-line"
                    placeholder="Enter credit score requirement (e.g., 720+ required, Higher deposit accepted in lieu)">
            </div>
        </div>
    </div>

    {{-- Credit Score Flexibility --}}
    <div class="form-group">
        <label class="fw-bold">Credit score flexibility:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate how strictly the minimum credit score requirement will be enforced.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="credit_score_flexibility" class="form-control has-icon" data-icon="fa-solid fa-sliders">
                <option value="">Select</option>
                <option value="No additional flexibility">No additional flexibility</option>
                <option value="Strict requirement">Strict requirement</option>
                <option value="Case-by-case review">Case-by-case review</option>
                <option value="Compensating factors considered">Compensating factors considered</option>
            </select>
        </div>
    </div>

    {{-- Income Qualification Method --}}
    <div class="form-group" x-data>
        <label class="fw-bold">Income qualification method:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select how you will verify that the tenant's income is sufficient to cover rent.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="income_qualification_method" class="form-control has-icon" data-icon="fa-solid fa-money-bill-wave">
                <option value="">Select</option>
                <option value="No requirement">No requirement</option>
                <option value="2x Rent">2x Rent</option>
                <option value="2.5x Rent">2.5x Rent</option>
                <option value="3x Rent">3x Rent</option>
                <option value="Fixed Monthly Income">Fixed Monthly Income</option>
                <option value="Other">Other</option>
            </select>
        </div>
        {{-- Conditional: fixed monthly income amount --}}
        <div x-show="$wire.income_qualification_method === 'Fixed Monthly Income'" x-cloak class="mt-2">
            <div class="input-cover">
                <span class="input-group-text-seller">$</span>
                <input type="text" wire:model="min_monthly_income_fixed" class="form-control"
                    placeholder="Enter required monthly income (e.g., 5000)"
                    oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            </div>
        </div>
        {{-- Conditional: custom income requirement --}}
        <div x-show="$wire.income_qualification_method === 'Other'" x-cloak class="mt-2">
            <div class="input-cover">
                <input type="text" wire:model="custom_income_requirement" class="form-control has-icon"
                    data-icon="fa-solid fa-money-bill-wave"
                    placeholder="Enter income requirement (e.g., Verified bank statements, 6 months reserves)">
            </div>
        </div>
    </div>

    {{-- ===== SECTION: LIFESTYLE REQUIREMENTS ===== --}}
    <h6 class="fw-semibold border-bottom pb-2 mb-3 mt-4">Lifestyle requirements</h6>

    {{-- Pet Policy --}}
    <div class="form-group" x-data>
        <label class="fw-bold">Pet policy:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Specify whether pets are allowed and any restrictions that apply.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="pet_policy_requirement" class="form-control has-icon" data-icon="fa-solid fa-paw">
                <option value="">Select</option>
                <option value="No requirement">No requirement</option>
                <option value="No pets">No pets</option>
                <option value="Cats allowed">Cats allowed</option>
                <option value="Dogs allowed">Dogs allowed</option>
                <option value="Small pets allowed">Small pets allowed</option>
                <option value="Pets allowed">Pets allowed</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div x-show="$wire.pet_policy_requirement === 'Other'" x-cloak class="mt-2">
            <div class="input-cover">
                <input type="text" wire:model="custom_pet_policy_requirement" class="form-control has-icon"
                    data-icon="fa-solid fa-paw"
                    placeholder="Enter pet policy (e.g., One small dog under 25 lbs, Non-shedding breeds only)">
            </div>
        </div>
        {{-- Pet restrictions — shown when any permitting option is selected --}}
        <div x-show="['Cats allowed','Dogs allowed','Small pets allowed','Pets allowed','Other'].includes($wire.pet_policy_requirement)" x-cloak class="mt-2">
            <label class="fw-semibold small text-muted">Pet restrictions:</label>
            <div class="input-cover">
                <input type="text" wire:model="pet_restrictions" class="form-control has-icon"
                    data-icon="fa-solid fa-circle-exclamation"
                    placeholder="Enter pet restrictions (e.g., Maximum 2 pets, No aggressive breeds, 50 lb weight limit)">
            </div>
        </div>
    </div>

    {{-- Smoking Policy --}}
    <div class="form-group" x-data>
        <label class="fw-bold">Smoking policy:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Specify the smoking policy for tenants on the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="smoking_policy_requirement" class="form-control has-icon" data-icon="fa-solid fa-smoking">
                <option value="">Select</option>
                <option value="No requirement">No requirement</option>
                <option value="No smoking">No smoking</option>
                <option value="Smoking allowed on premises">Smoking allowed on premises</option>
                <option value="Smoking allowed outside only">Smoking allowed outside only</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div x-show="$wire.smoking_policy_requirement === 'Other'" x-cloak class="mt-2">
            <div class="input-cover">
                <input type="text" wire:model="custom_smoking_policy_requirement" class="form-control has-icon"
                    data-icon="fa-solid fa-smoking"
                    placeholder="Enter smoking policy (e.g., No smoking or vaping anywhere on the property or grounds)">
            </div>
        </div>
    </div>

    {{-- ===== SECTION: BACKGROUND REQUIREMENTS ===== --}}
    <h6 class="fw-semibold border-bottom pb-2 mb-3 mt-4">Background requirements</h6>

    {{-- Employment Requirement --}}
    <div class="form-group" x-data>
        <label class="fw-bold">Employment requirement:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the employment status requirement for tenant qualification.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="employment_requirement" class="form-control has-icon" data-icon="fa-solid fa-briefcase">
                <option value="">Select</option>
                <option value="No requirement">No requirement</option>
                <option value="Employed">Employed</option>
                <option value="Self-employed allowed">Self-employed allowed</option>
                <option value="Retired allowed">Retired allowed</option>
                <option value="Student allowed">Student allowed</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div x-show="$wire.employment_requirement === 'Other'" x-cloak class="mt-2">
            <div class="input-cover">
                <input type="text" wire:model="custom_employment_requirement" class="form-control has-icon"
                    data-icon="fa-solid fa-briefcase"
                    placeholder="Enter employment requirement (e.g., Government employee, Independent contractor accepted)">
            </div>
        </div>
    </div>

    {{-- Eviction History Requirement --}}
    <div class="form-group" x-data>
        <label class="fw-bold">Eviction history requirement:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the eviction history requirement for tenant qualification.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="eviction_history_requirement" class="form-control has-icon" data-icon="fa-solid fa-gavel">
                <option value="">Select</option>
                <option value="No requirement">No requirement</option>
                <option value="No prior evictions">No prior evictions</option>
                <option value="No evictions within 3 years">No evictions within 3 years</option>
                <option value="No evictions within 5 years">No evictions within 5 years</option>
                <option value="No evictions within 7 years">No evictions within 7 years</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div x-show="$wire.eviction_history_requirement === 'Other'" x-cloak class="mt-2">
            <div class="input-cover">
                <input type="text" wire:model="custom_eviction_requirement" class="form-control has-icon"
                    data-icon="fa-solid fa-gavel"
                    placeholder="Enter eviction requirement (e.g., No evictions within 10 years, Case-by-case review)">
            </div>
        </div>
    </div>

    {{-- Bankruptcy Requirement --}}
    <div class="form-group" x-data>
        <label class="fw-bold">Bankruptcy requirement:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the bankruptcy history requirement for tenant qualification.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="bankruptcy_requirement" class="form-control has-icon" data-icon="fa-solid fa-scale-balanced">
                <option value="">Select</option>
                <option value="No requirement">No requirement</option>
                <option value="No active bankruptcy">No active bankruptcy</option>
                <option value="Discharged bankruptcy allowed">Discharged bankruptcy allowed</option>
                <option value="No bankruptcy within 2 years">No bankruptcy within 2 years</option>
                <option value="No bankruptcy within 5 years">No bankruptcy within 5 years</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div x-show="$wire.bankruptcy_requirement === 'Other'" x-cloak class="mt-2">
            <div class="input-cover">
                <input type="text" wire:model="custom_bankruptcy_requirement" class="form-control has-icon"
                    data-icon="fa-solid fa-scale-balanced"
                    placeholder="Enter bankruptcy requirement (e.g., No bankruptcy within 7 years, Discharged bankruptcy only)">
            </div>
        </div>
    </div>

    {{-- Criminal Background Requirement --}}
    <div class="form-group" x-data>
        <label class="fw-bold">Criminal background requirement:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Specify any criminal background requirements for prospective tenants.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="criminal_background_requirement" class="form-control has-icon" data-icon="fa-solid fa-shield-halved">
                <option value="">Select</option>
                <option value="No requirement">No requirement</option>
                <option value="No criminal background">No criminal background</option>
                <option value="Case-by-case review">Case-by-case review</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div x-show="$wire.criminal_background_requirement === 'Other'" x-cloak class="mt-2">
            <div class="input-cover">
                <input type="text" wire:model="custom_criminal_background_requirement" class="form-control has-icon"
                    data-icon="fa-solid fa-shield-halved"
                    placeholder="Enter criminal background requirement (e.g., No felonies within 7 years, Non-violent only)">
            </div>
        </div>
    </div>

    {{-- Landlord Reference Requirement --}}
    <div class="form-group" x-data>
        <label class="fw-bold">Prior landlord reference requirement:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate whether a prior landlord reference is required from prospective tenants.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="reference_requirement" class="form-control has-icon" data-icon="fa-solid fa-address-book">
                <option value="">Select</option>
                <option value="No requirement">No requirement</option>
                <option value="Required">Required</option>
                <option value="Preferred">Preferred</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div x-show="$wire.reference_requirement === 'Other'" x-cloak class="mt-2">
            <div class="input-cover">
                <input type="text" wire:model="custom_reference_requirement" class="form-control has-icon"
                    data-icon="fa-solid fa-address-book"
                    placeholder="Enter reference requirement (e.g., Two references required, Character reference accepted)">
            </div>
        </div>
    </div>

    {{-- Employment Verification Requirement --}}
    <div class="form-group">
        <label class="fw-bold">Employment verification requirement:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Specify whether tenants must provide proof of employment (e.g., pay stubs, offer letter).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="employment_verification_requirement" class="form-control has-icon" data-icon="fa-solid fa-file-contract">
                <option value="">Select</option>
                <option value="No requirement">No requirement</option>
                <option value="Required">Required</option>
                <option value="Preferred">Preferred</option>
            </select>
        </div>
    </div>

    {{-- Income Verification Requirement --}}
    <div class="form-group">
        <label class="fw-bold">Income verification requirement:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Specify whether tenants must provide proof of income (e.g., bank statements, tax returns).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="income_verification_requirement" class="form-control has-icon" data-icon="fa-solid fa-file-invoice-dollar">
                <option value="">Select</option>
                <option value="No requirement">No requirement</option>
                <option value="Required">Required</option>
                <option value="Preferred">Preferred</option>
            </select>
        </div>
    </div>

    {{-- ===== SECTION: MOVE-IN PREFERENCE ===== --}}
    <h6 class="fw-semibold border-bottom pb-2 mb-3 mt-4">Move-in preference</h6>

    {{-- Preferred Move-In Timeframe --}}
    <div class="form-group" x-data>
        <label class="fw-bold">Preferred move-in timeframe:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Specify when you would ideally like the tenant to move in.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="preferred_move_in_timeframe" class="form-control has-icon" data-icon="fa-solid fa-calendar-check">
                <option value="">Select</option>
                <option value="No preference">No preference</option>
                <option value="Immediately">Immediately</option>
                <option value="Within 30 days">Within 30 days</option>
                <option value="Within 60 days">Within 60 days</option>
                <option value="Within 90 days">Within 90 days</option>
                <option value="Flexible">Flexible</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div x-show="$wire.preferred_move_in_timeframe === 'Other'" x-cloak class="mt-2">
            <div class="input-cover">
                <input type="text" wire:model="custom_preferred_move_in_timeframe" class="form-control has-icon"
                    data-icon="fa-solid fa-calendar-check"
                    placeholder="Enter preferred move-in timeframe (e.g., After October 1st, School year start)">
            </div>
        </div>
    </div>

    {{-- ===== SECTION: ESTIMATED UTILITY COSTS ===== --}}
    <h6 class="fw-semibold border-bottom pb-2 mb-3 mt-4">Estimated utility costs <span class="text-muted fw-normal">(Optional)</span></h6>
    <p class="text-muted small mb-3">Provide estimated monthly utility costs to help tenants budget. These are estimates only and do not form part of the lease terms.</p>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label class="fw-bold">Est. water / sewer / trash:</label>
                <div class="input-cover">
                    <span class="input-group-text-seller">$</span>
                    <input type="text" wire:model="est_water_sewer_trash" class="form-control"
                        placeholder="e.g., 80"
                        oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label class="fw-bold">Est. electric:</label>
                <div class="input-cover">
                    <span class="input-group-text-seller">$</span>
                    <input type="text" wire:model="est_electric" class="form-control"
                        placeholder="e.g., 120"
                        oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label class="fw-bold">Est. internet:</label>
                <div class="input-cover">
                    <span class="input-group-text-seller">$</span>
                    <input type="text" wire:model="est_internet" class="form-control"
                        placeholder="e.g., 60"
                        oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label class="fw-bold">Est. cable:</label>
                <div class="input-cover">
                    <span class="input-group-text-seller">$</span>
                    <input type="text" wire:model="est_cable" class="form-control"
                        placeholder="e.g., 50"
                        oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
                </div>
            </div>
        </div>
    </div>
</div>
