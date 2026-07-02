
<h4>Agent Credentials & Contact Info</h4>

<div class="row">
     <div class="alert alert-info bg-light-info border-info mb-4">
        <div class="d-flex align-items-center">
            <div>
                <strong>📇 Provide your basic contact and license information. This section helps the client verify your credentials and understand who they’re hiring. </strong>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group required-field">
            <label class="fw-bold">First Name:<span class="text-danger">*</span></label>
            <div class="input-cover">
                <input type="text" wire:model="first_name" id="first_name"
                    class="form-control has-icon" required data-icon="fa-solid fa-user" placeholder="Enter first name">
            </div>
            <span class="error mt-2" id="first_name_error"></span>
            @error('first_name')
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group required-field">
            <label class="fw-bold">Last Name:<span class="text-danger">*</span></label>
            <div class="input-cover">
                <input type="text" wire:model="last_name" id="last_name"
                    class="form-control has-icon" required data-icon="fa-solid fa-user" placeholder="Enter last name" >
            </div>
            <span class="error mt-2" id="last_name_error"></span>
            @error('last_name')
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group required-field">
            <label class="fw-bold">Phone Number:<span class="text-danger">*</span></label>
            <div class="input-cover">
                <input wire:model="phone" type="text" id="phone_number"
                    class="form-control has-icon" required data-icon="fa-solid fa-phone" 
                    placeholder="Enter phone number" oninput="formatPhoneNumber(this)">
            </div>
            <span class="error mt-2" id="phone_error"></span>
            @error('phone')
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group required-field">
            <label class="fw-bold">Email:<span class="text-danger">*</span></label>
            <div class="input-cover">
                <input type="email" wire:model="email" id="email"
                    class="form-control has-icon" required data-icon="fa-solid fa-envelope" placeholder="Enter email address">
            </div>
            <span class="error mt-2" id="email_error"></span>
            @error('email')
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>
    </div>
</div>

<div class="form-group required-field">
    <label class="fw-bold">Brokerage:<span class="text-danger">*</span></label>
    <div class="input-cover">
        <input type="text" wire:model="brokerage" id="brokerage"
            class="form-control has-icon" required data-icon="fa-solid fa-building" placeholder="Enter brokerage name">
    </div>
    <span class="error mt-2" id="brokerage_error"></span>
    @error('brokerage')
        <span class="text-danger">{{ $message }}</span>
    @enderror
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group required-field">
            <label class="fw-bold">Real Estate License #:<span class="text-danger">*</span></label>
            <div class="input-cover">
                <input type="text" wire:model="license_no" id="license_no"
                    class="form-control has-icon" required data-icon="fa-solid fa-id-card" placeholder="Enter license number">
            </div>
            <span class="error mt-2" id="license_no_error"></span>
            @error('license_no')
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="fw-bold">NAR Member ID (NRDS ID):</label>
            <div class="input-cover">
                <input type="text" wire:model="nar_id" id="nar_id"
                    class="form-control has-icon" data-icon="fa-solid fa-address-card" placeholder="Enter NRDS ID">
            </div>
            <span class="error mt-2" id="nar_id_error"></span>
        </div>
    </div>
</div>

<script>
function formatPhoneNumber(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length > 10) {
        value = value.substring(0, 10);
    }
    if (value.length >= 6) {
        input.value = '(' + value.substring(0, 3) + ') ' + value.substring(3, 6) + '-' + value.substring(6);
    } else if (value.length >= 3) {
        input.value = '(' + value.substring(0, 3) + ') ' + value.substring(3);
    } else if (value.length > 0) {
        input.value = '(' + value;
    }
}
</script>

{{-- Save as Default Profile --}}
<div class="mt-4 p-3 border rounded bg-light">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <strong><i class="fa-solid fa-bookmark me-1 text-primary"></i> Save as Default Profile</strong>
            <p class="mb-0 small text-muted">Save your Agent Overview, contact info, and presentation details to pre-fill future Tenant bids ({{ ucfirst($property_type ?: 'residential') }} property type).</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" wire:click="saveAsDefaultProfile" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-floppy-disk me-1"></i> Save for {{ ucfirst($property_type ?: 'Residential') }}
            </button>
            <button type="button" wire:click="saveAsRoleDefault" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-star me-1"></i> Save as Tenant Default
            </button>
        </div>
    </div>
    <p class="mb-0 mt-1 small text-muted"><i class="fa-solid fa-circle-info me-1"></i> "Save for {{ ucfirst($property_type ?: 'Residential') }}" overrides for this property type only. "Save as Tenant Default" applies to all property types without a specific override.</p>
</div>
