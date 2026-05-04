<h4>Client Contact Information</h4>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Client Contact Information</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small">Enter the client's contact information for this hire request.</p>
        <div class="row">
            <div class="col-md-12 mb-3">
                <label class="fw-bold">Full Name</label>
                <input type="text" class="form-control" wire:model="client_name" placeholder="Client's full name">
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Phone Number</label>
                <input type="text" class="form-control" wire:model="client_phone" placeholder="Client's phone number">
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Email Address</label>
                <input type="email" class="form-control" wire:model="client_email" placeholder="Client's email address">
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Property Address</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small">Enter the address of the property this hire request relates to.</p>
        <div class="row">
            <div class="col-md-12 mb-3">
                <label class="fw-bold">Street Address</label>
                <input type="text" class="form-control" wire:model="client_property_address" placeholder="Street address">
            </div>
            <div class="col-md-5 mb-3">
                <label class="fw-bold">City</label>
                <input type="text" class="form-control" wire:model="client_property_city" placeholder="City">
            </div>
            <div class="col-md-4 mb-3">
                <label class="fw-bold">State</label>
                <input type="text" class="form-control" wire:model="client_property_state" placeholder="State">
            </div>
            <div class="col-md-3 mb-3">
                <label class="fw-bold">ZIP Code</label>
                <input type="text" class="form-control" wire:model="client_property_zip" placeholder="ZIP">
            </div>
        </div>
    </div>
</div>
