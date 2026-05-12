<h3>Leasing Terms </h4>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>💵 Enter the Tenant's desired lease terms. These terms will be used as the starting point for Landlord lease offers and future counteroffers.</strong>
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
    <div class="input-cover" wire:ignore>
        <select class="lease_for form-control has-icon select2-multiple"
            data-icon="fa-solid fa-file-pen input-icon2" multiple required>
            <option value=""></option>
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
    <div class="input-cover" wire:ignore>
        <select class="lease_for form-control has-icon select2-multiple"
            data-icon="fa-solid fa-file-pen input-icon2" multiple required>
            <option value=""></option>
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
    <div class="input-cover">
  <select id="leasing_spaces_tenant" class="form-control has-icon select2-multiple"
            data-icon="fa-solid fa-building input-icon2" multiple required>
            <option value=""></option>
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
