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

{{-- Location & Target --}}
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Location &amp; Target</h5></div>
    <div class="card-body">
        <div class="form-group mb-0">
            <label class="fw-bold">Areas of Interest</label>
            <input type="text" class="form-control" wire:model="areas_of_interest" placeholder="e.g. Downtown, Westside, North suburbs">
        </div>
    </div>
</div>

{{-- Deal Terms --}}
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Deal Terms</h5></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Target Purchase Price / Budget</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="text" class="form-control" wire:model="target_purchase_price" placeholder="e.g. 350,000">
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Timeline to Purchase</label>
                <select class="form-control" wire:model="timeline_to_purchase">
                    <option value="">-- Select timeline --</option>
                    <option value="As soon as possible">As soon as possible</option>
                    <option value="1–3 months">1–3 months</option>
                    <option value="3–6 months">3–6 months</option>
                    <option value="6–12 months">6–12 months</option>
                    <option value="12+ months">12+ months</option>
                    <option value="Flexible">Flexible</option>
                </select>
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
                <label class="fw-bold">Pre-Approval Status</label>
                <select class="form-control" wire:model="pre_approval_status">
                    <option value="">-- Select status --</option>
                    <option value="Pre-approved">Pre-approved</option>
                    <option value="Pre-qualified">Pre-qualified</option>
                    <option value="In process">In process</option>
                    <option value="Not yet started">Not yet started</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Cash Buyer?</label>
                <select class="form-control" wire:model="cash_buyer">
                    <option value="">-- Select --</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Estimated Down Payment <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" class="form-control" wire:model="estimated_down_payment" placeholder="e.g. 20% or 70,000">
            </div>
        </div>
    </div>
</div>

