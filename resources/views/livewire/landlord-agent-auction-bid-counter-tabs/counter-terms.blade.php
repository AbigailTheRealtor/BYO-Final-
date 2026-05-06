<h4>Client Details</h4>

{{-- Client Contact Info --}}
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Client Contact Information</h5></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-12 mb-3">
                <label class="fw-bold">Full Name</label>
                <div class="input-cover">
                    <input type="text" class="form-control has-icon" data-icon="fa-solid fa-user" wire:model="client_name" placeholder="Client's full name">
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Phone Number</label>
                <div class="input-cover">
                    <input type="text" class="form-control has-icon" data-icon="fa-solid fa-phone" wire:model="client_phone" placeholder="Client's phone number">
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Email Address</label>
                <div class="input-cover">
                    <input type="email" class="form-control has-icon" data-icon="fa-solid fa-envelope" wire:model="client_email" placeholder="Client's email address">
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Property Address --}}
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Property Address</h5></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-12 mb-3">
                <label class="fw-bold">Street Address</label>
                <div class="input-cover">
                    <input type="text" class="form-control has-icon" data-icon="fa-solid fa-location-dot" wire:model="client_property_address" placeholder="Street address">
                </div>
            </div>
            <div class="col-md-5 mb-3">
                <label class="fw-bold">City</label>
                <div class="input-cover">
                    <input type="text" class="form-control has-icon" data-icon="fa-solid fa-city" wire:model="client_property_city" placeholder="City">
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <label class="fw-bold">State</label>
                <div class="input-cover">
                    <input type="text" class="form-control has-icon" data-icon="fa-solid fa-map" wire:model="client_property_state" placeholder="State">
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <label class="fw-bold">ZIP Code</label>
                <div class="input-cover">
                    <input type="text" class="form-control has-icon" data-icon="fa-solid fa-hashtag" wire:model="client_property_zip" placeholder="ZIP">
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Deal Terms --}}
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Deal Terms</h5></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Desired Monthly Rent <span class="text-muted fw-normal">(optional)</span></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="text" class="form-control" wire:model="desired_monthly_rent" placeholder="e.g. 2,200">
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Availability Date</label>
                <input type="date" class="form-control" wire:model="availability_date">
            </div>
        </div>
    </div>
</div>

{{-- Qualification Signals --}}
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Qualification Signals</h5></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Occupancy Status</label>
                <select class="form-control" wire:model="occupancy_status">
                    <option value="">-- Select status --</option>
                    <option value="Vacant – ready now">Vacant – ready now</option>
                    <option value="Occupied – tenant leaving soon">Occupied – tenant leaving soon</option>
                    <option value="Owner-occupied">Owner-occupied</option>
                    <option value="Occupied – lease active">Occupied – lease active</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Flexibility</label>
                <select class="form-control" wire:model="flexibility">
                    <option value="">-- Select flexibility --</option>
                    <option value="Very flexible">Very flexible</option>
                    <option value="Somewhat flexible">Somewhat flexible</option>
                    <option value="Not flexible – firm on terms">Not flexible – firm on terms</option>
                </select>
            </div>
        </div>
    </div>
</div>

