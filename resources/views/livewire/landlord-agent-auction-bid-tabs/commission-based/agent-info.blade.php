
<h4>Agent Information</h4>

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
            <label class="fw-bold">First Name:</label>
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
            <label class="fw-bold">Last Name:</label>
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
            <label class="fw-bold">Phone Number:</label>
            <div class="input-cover">
                <input wire:model="phone" type="text" id="phone_number"
                    class="form-control has-icon" required data-icon="fa-solid fa-phone" placeholder="Enter phone number">
            </div>
            <span class="error mt-2" id="phone_error"></span>
            @error('phone')
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group required-field">
            <label class="fw-bold">Email:</label>
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
    <label class="fw-bold">Brokerage:</label>
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
            <label class="fw-bold">Real Estate License #:</label>
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
