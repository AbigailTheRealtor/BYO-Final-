<h4>Additional Details</h4>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Client Contact Information</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small">Enter the client's contact information and the property this hire request relates to.</p>
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

<div class="form-group">
    <div class="alert alert-info bg-light-info border-info mb-4">
        <div class="d-flex align-items-center">
            <div>
                <strong>📋 Share any special instructions, terms, or preferences to help the client better understand your offer or clarify important details. This section is optional but can help set your bid apart. </strong>
            </div>
        </div>
    </div>

    <label class="fw-bold">Provide any additional details:</label>
    <div class="input-cover">
        <textarea wire:model="additional_details" class="form-control" rows="4" placeholder="Enter additional details " style="padding: 10px; font-size: 16px;"></textarea>
    </div>
</div>
