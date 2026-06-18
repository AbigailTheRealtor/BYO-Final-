{{-- ================================================================
     APPLICANT REQUIREMENTS TAB
     Landlord Offer Listing — Commission-Based (Create & Edit)
     Tab index: 4 (Create) / 10 (Edit)
     Fields: min_income_requirement, number_of_occupants_allowed,
             landlord_approval_conditions (relocated from Leasing Terms)
     New EAV keys: min_credit_score, income_qualification_method,
             min_monthly_income_fixed, custom_income_requirement,
             employment_requirement, custom_employment_requirement,
             eviction_history_requirement, custom_eviction_requirement,
             bankruptcy_requirement, custom_bankruptcy_requirement,
             est_water_sewer_trash, est_electric, est_internet, est_cable
     ============================================================== --}}

<div class="tab-content-inner">
    <h5 class="mb-4"><i class="fa-solid fa-user-check me-2"></i>Applicant Requirements</h5>
    <p class="text-muted mb-4">Set the qualification criteria for prospective tenants. All fields are optional — only fill in requirements you wish to enforce.</p>

    {{-- ===== SECTION: TENANCY CONDITIONS ===== --}}
    <h6 class="fw-semibold border-bottom pb-2 mb-3">Tenancy Conditions</h6>

    {{-- Minimum Income Requirement (relocated from Leasing Terms) --}}
    <div class="form-group">
        <label class="fw-bold">Minimum Monthly Income Requirement:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the minimum monthly income required for tenant qualification (e.g., 6000).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>
            <input type="text" wire:model="min_income_requirement" class="form-control"
                placeholder="Enter minimum monthly income requirement (e.g., 6000)"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
        </div>
    </div>

    {{-- Number of Occupants Allowed (relocated from Leasing Terms) --}}
    <div class="form-group">
        <label class="fw-bold">Number of Occupants Allowed:</label>
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
        <label class="fw-bold">Landlord Approval Conditions:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="List any conditions or requirements the Tenant must meet for the Landlord to approve the lease (e.g., credit check, income verification, references).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <textarea wire:model="landlord_approval_conditions" class="form-control has-icon landlord-compact-textarea" rows="1"
                data-icon="fa-solid fa-clipboard-check"
                placeholder="Enter approval conditions (e.g., Credit score 650+, Income 3x monthly rent, No prior evictions)"></textarea>
        </div>
    </div>

    {{-- ===== SECTION: CREDIT & FINANCIAL REQUIREMENTS ===== --}}
    <h6 class="fw-semibold border-bottom pb-2 mb-3 mt-4">Credit &amp; Financial Requirements</h6>

    {{-- Minimum Credit Score --}}
    <div class="form-group" x-data>
        <label class="fw-bold">Minimum Credit Score:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the minimum credit score required for tenant qualification.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="min_credit_score" class="form-control has-icon" data-icon="fa-solid fa-chart-line">
                <option value="">Select</option>
                <option value="No Minimum">No Minimum</option>
                <option value="580+">580+</option>
                <option value="600+">600+</option>
                <option value="620+">620+</option>
                <option value="640+">640+</option>
                <option value="660+">660+</option>
                <option value="680+">680+</option>
                <option value="700+">700+</option>
                <option value="Other">Other</option>
            </select>
        </div>
        {{-- Conditional: Custom credit score requirement --}}
        <div x-show="$wire.min_credit_score === 'Other'" x-cloak class="mt-2">
            <label class="fw-bold">Custom Minimum Credit Score:</label>
            <div class="input-cover">
                <input type="text" wire:model="custom_credit_score_requirement" class="form-control has-icon"
                    data-icon="fa-solid fa-chart-line"
                    placeholder="Enter title (e.g., example)">
            </div>
        </div>
    </div>

    {{-- Income Qualification Method --}}
    <div class="form-group" x-data>
        <label class="fw-bold">Income Qualification Method:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select how you will verify that the tenant's income is sufficient to cover rent.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="income_qualification_method" class="form-control has-icon" data-icon="fa-solid fa-money-bill-wave">
                <option value="">Select</option>
                <option value="No Requirement">No Requirement</option>
                <option value="2x Rent">2x Rent</option>
                <option value="2.5x Rent">2.5x Rent</option>
                <option value="3x Rent">3x Rent</option>
                <option value="Fixed Monthly Income">Fixed Monthly Income</option>
                <option value="Other">Other</option>
            </select>
        </div>
        {{-- Conditional: Fixed Monthly Income amount --}}
        <div x-show="$wire.income_qualification_method === 'Fixed Monthly Income'" x-cloak class="mt-2">
            <label class="fw-bold">Required Monthly Income (Fixed):</label>
            <div class="input-cover">
                <span class="input-group-text-seller">$</span>
                <input type="text" wire:model="min_monthly_income_fixed" class="form-control"
                    placeholder="Enter required monthly income (e.g., 5000)"
                    oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            </div>
        </div>
        {{-- Conditional: Custom income requirement --}}
        <div x-show="$wire.income_qualification_method === 'Other'" x-cloak class="mt-2">
            <label class="fw-bold">Custom Income Requirement:</label>
            <div class="input-cover">
                <input type="text" wire:model="custom_income_requirement" class="form-control has-icon"
                    data-icon="fa-solid fa-money-bill-wave"
                    placeholder="Enter title (e.g., example)">
            </div>
        </div>
    </div>

    {{-- ===== SECTION: BACKGROUND REQUIREMENTS ===== --}}
    <h6 class="fw-semibold border-bottom pb-2 mb-3 mt-4">Background Requirements</h6>

    {{-- Employment Requirement --}}
    <div class="form-group" x-data>
        <label class="fw-bold">Employment Requirement:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the employment status requirement for tenant qualification.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="employment_requirement" class="form-control has-icon" data-icon="fa-solid fa-briefcase">
                <option value="">Select</option>
                <option value="No Requirement">No Requirement</option>
                <option value="Employed">Employed</option>
                <option value="Self-Employed Allowed">Self-Employed Allowed</option>
                <option value="Retired Allowed">Retired Allowed</option>
                <option value="Student Allowed">Student Allowed</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div x-show="$wire.employment_requirement === 'Other'" x-cloak class="mt-2">
            <label class="fw-bold">Custom Employment Requirement:</label>
            <div class="input-cover">
                <input type="text" wire:model="custom_employment_requirement" class="form-control has-icon"
                    data-icon="fa-solid fa-briefcase"
                    placeholder="Enter title (e.g., example)">
            </div>
        </div>
    </div>

    {{-- Eviction History Requirement --}}
    <div class="form-group" x-data>
        <label class="fw-bold">Eviction History Requirement:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the eviction history requirement for tenant qualification.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="eviction_history_requirement" class="form-control has-icon" data-icon="fa-solid fa-gavel">
                <option value="">Select</option>
                <option value="No Requirement">No Requirement</option>
                <option value="No Prior Evictions">No Prior Evictions</option>
                <option value="No Evictions Within 3 Years">No Evictions Within 3 Years</option>
                <option value="No Evictions Within 5 Years">No Evictions Within 5 Years</option>
                <option value="No Evictions Within 7 Years">No Evictions Within 7 Years</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div x-show="$wire.eviction_history_requirement === 'Other'" x-cloak class="mt-2">
            <label class="fw-bold">Custom Eviction History Requirement:</label>
            <div class="input-cover">
                <input type="text" wire:model="custom_eviction_requirement" class="form-control has-icon"
                    data-icon="fa-solid fa-gavel"
                    placeholder="Enter title (e.g., example)">
            </div>
        </div>
    </div>

    {{-- Bankruptcy Requirement --}}
    <div class="form-group" x-data>
        <label class="fw-bold">Bankruptcy Requirement:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the bankruptcy history requirement for tenant qualification.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="bankruptcy_requirement" class="form-control has-icon" data-icon="fa-solid fa-scale-balanced">
                <option value="">Select</option>
                <option value="No Requirement">No Requirement</option>
                <option value="No Active Bankruptcy">No Active Bankruptcy</option>
                <option value="Discharged Bankruptcy Allowed">Discharged Bankruptcy Allowed</option>
                <option value="No Bankruptcy Within 2 Years">No Bankruptcy Within 2 Years</option>
                <option value="No Bankruptcy Within 5 Years">No Bankruptcy Within 5 Years</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div x-show="$wire.bankruptcy_requirement === 'Other'" x-cloak class="mt-2">
            <label class="fw-bold">Custom Bankruptcy Requirement:</label>
            <div class="input-cover">
                <input type="text" wire:model="custom_bankruptcy_requirement" class="form-control has-icon"
                    data-icon="fa-solid fa-scale-balanced"
                    placeholder="Enter title (e.g., example)">
            </div>
        </div>
    </div>

    {{-- ===== SECTION: ESTIMATED UTILITY COSTS ===== --}}
    <h6 class="fw-semibold border-bottom pb-2 mb-3 mt-4">Estimated Utility Costs <span class="text-muted fw-normal">(Optional)</span></h6>
    <p class="text-muted small mb-3">Provide estimated monthly utility costs to help tenants budget. These are estimates only and do not form part of the lease terms.</p>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label class="fw-bold">Est. Water / Sewer / Trash:</label>
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
                <label class="fw-bold">Est. Electric:</label>
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
                <label class="fw-bold">Est. Internet:</label>
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
                <label class="fw-bold">Est. Cable:</label>
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
