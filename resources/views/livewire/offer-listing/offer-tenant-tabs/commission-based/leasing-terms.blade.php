<h3>Leasing Terms </h4>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>💵 Enter the desired lease terms, including the budget, lease duration, proposed lease start date, and acceptable leasing space. </strong>
        </div>
    </div>
</div>

<!-- Maximum Monthly Lease Price -->
<div class="form-group">
    <label class="fw-bold">Maximum Monthly Lease Price:<span class="text-danger">*</span>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the maximum rent the Tenant is willing to pay each month.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
                <span class="input-group-text-seller">$</span>

        <input type="text" wire:model="budget" class="form-control"
            placeholder="Enter maximum monthly lease price (e.g., 2000)" required

            data-error-id="budget_error" oninput="validateInput(this)"
                onblur="reformatNumber(this)" onpaste="handlePaste(event)">
    </div>
    <span class="error mt-2" id="budget_error"></span>
</div>

<!-- Offered Lease Length -->

@if ($property_type === 'Residential Property')

<div class="form-group">
    <label class="fw-bold">Offered Lease Term:<span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true" title="Select the Tenant's preferred lease term length.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    @php
        $selectedLeaseFor = $this->lease_for ?? [];
        if (is_string($selectedLeaseFor)) {
            $selectedLeaseFor = json_decode($selectedLeaseFor, true) ?? [];
        }
    @endphp
    <div class="input-cover has-select-icon" wire:ignore>
        <select class="lease_for form-control has-icon select2-multiple"
            data-icon="fa-solid fa-file-pen"
            data-placeholder="Select"
            multiple required>
            @foreach ($lease_for_res as $row_pt)
                <option value="{{ $row_pt['name'] }}" {{ in_array($row_pt['name'], $selectedLeaseFor) ? 'selected' : '' }}>{{ $row_pt['name'] }}</option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2 text-danger" id="custom_lease_for_error"></span>
</div>

@endif

@if ($property_type === 'Commercial Property')


<div class="form-group">
    <label class="fw-bold">Offered Lease Term:<span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true" title="Select the Tenant's preferred lease term length.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    @php
        $selectedLeaseForCom = $this->lease_for ?? [];
        if (is_string($selectedLeaseForCom)) {
            $selectedLeaseForCom = json_decode($selectedLeaseForCom, true) ?? [];
        }
    @endphp
    <div class="input-cover has-select-icon" wire:ignore>
        <select class="lease_for form-control has-icon select2-multiple"
            data-icon="fa-solid fa-file-pen"
            data-placeholder="Select"
            multiple required>
            @foreach ($lease_for_com as $row_pt)
                <option value="{{ $row_pt['name'] }}" {{ in_array($row_pt['name'], $selectedLeaseForCom) ? 'selected' : '' }}>{{ $row_pt['name'] }}</option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2 text-danger" id="custom_lease_for_error"></span>
</div>
@endif

<!-- Other Lease Input (Hidden by Default) -->
@php
    $leaseForArr = $this->lease_for ?? [];
    if (is_string($leaseForArr)) { $leaseForArr = json_decode($leaseForArr, true) ?? []; }
@endphp
<div class="form-group other_lease_for @if(!in_array('Other', $leaseForArr)) d-none @endif">
    <div class="input-cover">
        <input type="text" wire:model="other_lease_for"  class="form-control has-icon"
            data-icon="fa-solid fa-file-pen" placeholder="Enter offered lease term (e.g., 8 Months)">
    </div>
</div>

<!-- Offered Lease Date -->
<div class="form-group">
    <label class="fw-bold">Offered Lease Date:<span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the date the Tenant would like the lease to begin.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <input type="date" wire:model="lease_date" class="form-control has-icon" data-icon="fa-solid fa-calendar"
            required>
    </div>
    <span class="error mt-2" id="lease_date_error"></span>
</div>



<div class="form-group" wire:ignore>
    <label class="fw-bold"> Leasing Space:<span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the type of space the Tenant is open to leasing.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover has-select-icon" wire:ignore>
        <select id="leasing_spaces_tenant" class="form-control has-icon select2-multiple"
            data-icon="fa-solid fa-building"
            data-placeholder="Select"
            multiple required>
            @foreach ($acceptable_leasing_space as $row_pt)
                <option value="{{ $row_pt['name'] }}"
                    {{ in_array($row_pt['name'], $leasing_spaces_tenant ?? []) ? 'selected' : '' }}>{{ $row_pt['name'] }}</option>
            @endforeach
        </select>
    </div>
</div>
<!-- Leasing Details Based on Selected Space -->
{{--
<div class="form-group mt-4" id="leasing_space_details">
    @if (in_array('Entire Property', $leasing_spaces_tenant) || in_array('Accessory Unit / Guest Suite (ADU)', $leasing_spaces_tenant))
        <div class="form-group">
            <label>Restrictions include:</label>
            <div class="input-cover">
                <input type="text" wire:model="restrictions" class="form-control has-icon" data-icon="fa-solid fa-ban"
                    placeholder="Enter details (e.g., visiting hours, overnight stay rules)">
            </div>
        </div>

        <div class="form-group">
            <label>Maintenance issues are handled by:</label>
            <div class="input-cover">
                <select wire:model="maintenance_by" class="form-control has-icon" data-icon="fa-solid fa-screwdriver-wrench">
                    <option value="">Select</option>
                    <option value="Landlord">Landlord</option>
                    <option value="Property Manager">Property Manager</option>
                    <option value="Real Estate Agent">Real Estate Agent</option>
                    <option value="Tenant">Tenant</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Maintenance response time is typically:</label>
            <div class="input-cover">
                <input type="text" wire:model="maintenance_response_time" class="form-control has-icon"
                    data-icon="fa-solid fa-stopwatch" placeholder="Enter timeframe (e.g., 24 hours)">
            </div>
        </div>

        <div class="form-group">
            <label>Storage space available include:</label>
            <div class="input-cover">
                <input type="text" wire:model="storage_space" class="form-control has-icon"
                    data-icon="fa-solid fa-warehouse" placeholder="Enter size or type, (e.g., closet, basement section, garage shelf)">
            </div>
        </div>
    @endif

    @if (in_array('Single Room', $leasing_spaces_tenant))
        <div class="form-group">
            <label>Guests are:</label>
            <div class="input-cover">
                <select wire:model="guests_allowed" class="form-control has-icon" data-icon="fa-solid fa-user-group">
                    <option value="">Select</option>
                    <option value="Allowed">Allowed</option>
                    <option value="Not Allowed">Not Allowed</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Restrictions include:</label>
            <div class="input-cover">
                <input type="text" wire:model="restrictions" class="form-control has-icon" data-icon="fa-solid fa-ban"
                    placeholder="Enter details (e.g., visiting hours, overnight stay rules)">
            </div>
        </div>

        <div class="form-group">
            <label>Tenants have access to common areas such as:</label>
            <div class="input-cover">
                <input type="text" wire:model="common_areas_access" class="form-control has-icon"
                    data-icon="fa-solid fa-door-open" placeholder="Enter common areas (e.g., kitchen, living room,backyard)">
            </div>
        </div>

        <div class="form-group">
            <label>Maintenance issues are handled by:</label>
            <div class="input-cover">
                <select wire:model="maintenance_by" class="form-control has-icon" data-icon="fa-solid fa-screwdriver-wrench">
                    <option value="">Select</option>
                    <option value="Landlord">Landlord</option>
                    <option value="Property Manager">Property Manager</option>
                    <option value="Real Estate Agent">Real Estate Agent</option>
                    <option value="Tenant">Tenant</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Maintenance response time is typically:</label>
            <div class="input-cover">
                <input type="text" wire:model="maintenance_response_time" class="form-control has-icon"
                    data-icon="fa-solid fa-stopwatch" placeholder="Enter timeframe (e.g., 24 hours)">
            </div>
        </div>

        <div class="form-group">
            <label>Utilities:</label>
            <div class="input-cover">
                <select wire:model="utilities" class="form-control has-icon" data-icon="fa-solid fa-bolt">
                    <option value="">Select</option>
                    <option value="Included in Rent">Included in Rent</option>
                    <option value="Split Among Tenants">Split Among Tenants</option>
                    <option value="Individually Metered">Individually Metered</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Common areas are cleaned and maintained:</label>
            <div class="input-cover">
                <input type="text" wire:model="common_areas_cleaning" class="form-control has-icon"
                    data-icon="fa-solid fa-broom" placeholder="Enter frequency and by whom (e.g., weekly by landlord)">
            </div>
        </div>

        <div class="form-group">
            <label>Storage space available include:</label>
            <div class="input-cover">
                <input type="text" wire:model="storage_space" class="form-control has-icon"
                    data-icon="fa-solid fa-warehouse" placeholder="Enter size or type, (e.g., closet, basement section, garage shelf)">
            </div>
        </div>

        <div class="form-group">
            <label>Bathroom facilities:</label>
            <div class="input-cover">
                <select wire:model="bathroom_facilities" class="form-control has-icon" data-icon="fa-solid fa-bath">
                    <option value="">Select</option>
                    <option value="Private">Private</option>
                    <option value="Shared">Shared</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>The room available for lease is approximately:</label>
            <div class="input-cover">
                <input type="text" wire:model="room_size" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined" placeholder="Enter square footage (e.g., 300 sqft)">
            </div>
        </div>
    @endif
</div> --}}

<!-- ===== New Tenant Leasing Terms Fields ===== -->

<!-- Field 1: Desired Lease Length -->
<div class="form-group">
    <label class="fw-bold">Desired Lease Length:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the tenant's preferred lease duration (e.g., 12 months, 2 years).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <input type="text" wire:model="tenant_desired_lease_length" class="form-control has-icon"
            data-icon="fa-solid fa-clock" placeholder="Enter desired lease length (e.g., 12 months)">
    </div>
</div>

<!-- Field 2: Security Deposit Budget -->
<div class="form-group">
    <label class="fw-bold">Security Deposit Budget:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the maximum security deposit the tenant is prepared to pay.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <span class="input-group-text-seller">$</span>
        <input type="text" wire:model="security_deposit_budget" class="form-control"
            placeholder="Enter amount (e.g., 3000)"
            onblur="reformatNumber(this)" onpaste="handlePaste(event)">
    </div>
</div>

<!-- Field 3: Move-In Funds Available -->
<div class="form-group">
    <label class="fw-bold">Move-In Funds Available:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the total move-in funds the tenant has available.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <span class="input-group-text-seller">$</span>
        <input type="text" wire:model="move_in_funds_available" class="form-control"
            placeholder="Enter amount (e.g., 5000)"
            onblur="reformatNumber(this)" onpaste="handlePaste(event)">
    </div>
</div>

<!-- Field 4: First Month Rent Available -->
<div class="form-group">
    <label class="fw-bold">First Month Rent Available:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate whether the tenant has the first month's rent available upfront.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="first_month_rent_available" class="form-control has-icon"
            data-icon="fa-solid fa-calendar-check">
            <option value="">Select</option>
            <option value="Yes">Yes</option>
            <option value="No">No</option>
            <option value="Negotiable">Negotiable</option>
        </select>
    </div>
</div>

<!-- Field 5: Last Month Rent Available -->
<div class="form-group">
    <label class="fw-bold">Last Month Rent Available:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate whether the tenant has the last month's rent available upfront.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="last_month_rent_available" class="form-control has-icon"
            data-icon="fa-solid fa-calendar-check">
            <option value="">Select</option>
            <option value="Yes">Yes</option>
            <option value="No">No</option>
            <option value="Negotiable">Negotiable</option>
        </select>
    </div>
</div>

<!-- Field 9: Utility Preference -->
<div class="form-group">
    <label class="fw-bold">Utility Preference:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Describe the tenant's preferred utility arrangement.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <input type="text" wire:model="utility_preference" class="form-control has-icon"
            data-icon="fa-solid fa-bolt" placeholder="Enter utility preference (e.g., Tenant pays electric and water)">
    </div>
</div>

<!-- Field 10: Maintenance Preference -->
<div class="form-group">
    <label class="fw-bold">Maintenance Preference:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Describe the tenant's preferred maintenance arrangement.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <input type="text" wire:model="maintenance_preference" class="form-control has-icon"
            data-icon="fa-solid fa-screwdriver-wrench" placeholder="Enter maintenance preference (e.g., Landlord handles exterior maintenance)">
    </div>
</div>

<!-- Field 11: Renewal Option Requested -->
<div class="form-group" x-data="{}">
    <label class="fw-bold">Renewal Option Requested:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate whether the tenant wants the option to renew the lease.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="renewal_option_requested" class="form-control has-icon"
            data-icon="fa-solid fa-rotate-right">
            <option value="">Select</option>
            <option value="Yes">Yes</option>
            <option value="No">No</option>
            <option value="Negotiable">Negotiable</option>
        </select>
    </div>
</div>

<!-- Field 12: Renewal Option Details (conditional) -->
@if ($renewal_option_requested === 'Yes' || $renewal_option_requested === 'Negotiable')
<div class="form-group">
    <label class="fw-bold">Renewal Option Details:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Provide details about the desired renewal option terms.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <textarea wire:model="renewal_option_details" class="form-control" rows="3"
            placeholder="e.g., Option to renew for 1 additional year at the same rate"></textarea>
    </div>
</div>
@endif

<!-- Field 13: Tenant Conditions -->
<div class="form-group">
    <label class="fw-bold">Tenant Conditions:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="List any specific conditions the tenant requires before signing a lease.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <input type="text" wire:model="tenant_conditions" class="form-control has-icon"
            data-icon="fa-solid fa-list-check" placeholder="Enter tenant conditions (e.g., No smoking allowed)">
    </div>
</div>

<!-- Field 14: Additional Tenant Lease Terms -->
<div class="form-group">
    <label class="fw-bold">Additional Tenant Lease Terms:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter any additional lease terms or requests from the tenant.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <input type="text" wire:model="additional_tenant_lease_terms" class="form-control has-icon"
            data-icon="fa-solid fa-file-lines" placeholder="Enter additional tenant lease terms (e.g., Early termination option after 6 months)">
    </div>
</div>

<!-- Commercial-Only Fields (15–23) -->
@if ($property_type === 'Commercial Property')

<!-- Field 15: Commercial Lease Type Preference -->
<div class="form-group">
    <label class="fw-bold">Commercial Lease Type Preference:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the preferred type of commercial lease structure.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="commercial_lease_type_preference" class="form-control has-icon"
            data-icon="fa-solid fa-file-contract">
            <option value="">Select</option>
            <option value="Gross Lease">Gross Lease</option>
            <option value="Net Lease">Net Lease</option>
            <option value="NNN (Triple Net)">NNN (Triple Net)</option>
            <option value="Modified Gross">Modified Gross</option>
            <option value="Absolute Net">Absolute Net</option>
            <option value="Percentage Lease">Percentage Lease</option>
            <option value="Negotiable">Negotiable</option>
        </select>
    </div>
</div>

<!-- Field 16: CAM / NNN Preference -->
<div class="form-group">
    <label class="fw-bold">CAM / NNN Preference:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Describe the tenant's preference for CAM or NNN expense responsibility.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <input type="text" wire:model="cam_nnn_preference" class="form-control has-icon"
            data-icon="fa-solid fa-building-columns" placeholder="Enter CAM / NNN preference (e.g., Prefer gross lease with limited pass-throughs)">
    </div>
</div>

<!-- Field 17: Rent Escalation Preference -->
<div class="form-group">
    <label class="fw-bold">Rent Escalation Preference:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Describe how the tenant prefers rent escalations to be structured.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <input type="text" wire:model="rent_escalation_preference" class="form-control has-icon"
            data-icon="fa-solid fa-chart-line" placeholder="Enter rent escalation preference (e.g., No more than 3% annually)">
    </div>
</div>

<!-- Field 18: Buildout / Tenant Improvement Request -->
<div class="form-group">
    <label class="fw-bold">Buildout / Tenant Improvement Request:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Describe any buildout or tenant improvement allowance the tenant is requesting.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <input type="text" wire:model="buildout_tenant_improvement_request" class="form-control has-icon"
            data-icon="fa-solid fa-hammer" placeholder="Enter buildout or tenant improvement request (e.g., Paint and flooring allowance)">
    </div>
</div>

<!-- Field 19: Intended Business Use -->
<div class="form-group">
    <label class="fw-bold">Intended Business Use:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Describe how the tenant intends to use the commercial space.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <input type="text" wire:model="intended_business_use" class="form-control has-icon"
            data-icon="fa-solid fa-briefcase" placeholder="Enter intended business use (e.g., Retail clothing boutique)">
    </div>
</div>

<!-- Field 20: Signage Request -->
<div class="form-group">
    <label class="fw-bold">Signage Request:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Describe the tenant's signage requirements.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <input type="text" wire:model="signage_request" class="form-control has-icon"
            data-icon="fa-solid fa-sign-hanging" placeholder="Enter signage request (e.g., Exterior monument sign preferred)">
    </div>
</div>

<!-- Field 21: Commercial Parking / Access Needs -->
<div class="form-group">
    <label class="fw-bold">Commercial Parking / Access Needs:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Describe parking or loading dock access requirements for the commercial space.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <input type="text" wire:model="commercial_parking_access_needs" class="form-control has-icon"
            data-icon="fa-solid fa-truck" placeholder="Enter commercial parking or access needs (e.g., Customer parking near entrance)">
    </div>
</div>

<!-- Field 22: Personal Guarantee Preference -->
<div class="form-group">
    <label class="fw-bold">Personal Guarantee Preference:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate whether the tenant is willing to provide a personal guarantee.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="personal_guarantee_preference" class="form-control has-icon"
            data-icon="fa-solid fa-signature">
            <option value="">Select</option>
            <option value="Yes">Yes</option>
            <option value="No">No</option>
            <option value="Negotiable">Negotiable</option>
        </select>
    </div>
</div>

<!-- Field 23: Commercial Approval Conditions -->
<div class="form-group">
    <label class="fw-bold">Commercial Approval Conditions:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="List any conditions that must be met for the tenant to approve the commercial lease.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <input type="text" wire:model="commercial_approval_conditions" class="form-control has-icon"
            data-icon="fa-solid fa-stamp" placeholder="Enter commercial approval conditions (e.g., Subject to zoning approval)">
    </div>
</div>

@endif
<!-- End Commercial-Only Fields -->