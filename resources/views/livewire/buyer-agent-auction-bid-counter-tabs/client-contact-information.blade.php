<h4>Client Details</h4>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Client Contact Information</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small">Enter the client's contact information for this hire request.</p>
        <div class="row">
            <div class="col-md-12 mb-3">
                <label class="fw-bold">Full Name <span class="text-danger">*</span></label>
                <div class="input-cover">
                    <input type="text" class="form-control has-icon" data-icon="fa-solid fa-user" wire:model="client_name" placeholder="Client's full name" required>
                </div>
                @error('client_name') <span class="error">{{ $message }}</span> @enderror
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Phone Number <span class="text-danger">*</span></label>
                <div class="input-cover">
                    <input type="text" class="form-control has-icon" data-icon="fa-solid fa-phone" wire:model="client_phone" placeholder="Client's phone number" required>
                </div>
                @error('client_phone') <span class="error">{{ $message }}</span> @enderror
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Email Address <span class="text-danger">*</span></label>
                <div class="input-cover">
                    <input type="email" class="form-control has-icon" data-icon="fa-solid fa-envelope" wire:model="client_email" placeholder="Client's email address" required>
                </div>
                @error('client_email') <span class="error">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Target Search Area</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small">Enter the area where the client is looking to purchase a property.</p>
        <div class="row">
            <div class="col-md-5 mb-3">
                <label class="fw-bold">City <span class="text-danger">*</span></label>
                <div class="input-cover">
                    <input type="text" class="form-control has-icon" data-icon="fa-solid fa-city" wire:model="client_target_city" placeholder="City" required>
                </div>
                @error('client_target_city') <span class="error">{{ $message }}</span> @enderror
            </div>
            <div class="col-md-4 mb-3">
                <label class="fw-bold">State <span class="text-danger">*</span></label>
                <div class="input-cover">
                    <input type="text" class="form-control has-icon" data-icon="fa-solid fa-map" wire:model="client_target_state" placeholder="State" required>
                </div>
                @error('client_target_state') <span class="error">{{ $message }}</span> @enderror
            </div>
            <div class="col-md-3 mb-3">
                <label class="fw-bold">ZIP Code <span class="text-danger">*</span></label>
                <div class="input-cover">
                    <input type="text" class="form-control has-icon" data-icon="fa-solid fa-hashtag" wire:model="client_target_zip" placeholder="ZIP" required>
                </div>
                @error('client_target_zip') <span class="error">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>
</div>
