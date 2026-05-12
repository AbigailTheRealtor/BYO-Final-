<h4>Client Contact Information</h4>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Client Contact Information</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small">Enter the client's contact information for this hire request.</p>
        <div class="row">
            <div class="col-md-12 mb-3">
                <label class="fw-bold">Full Name @if($isOfferListing)<span class="text-danger">*</span>@endif</label>
                <div class="input-cover">
                    <input type="text" class="form-control has-icon" data-icon="fa-solid fa-user" wire:model="client_name" placeholder="Client's full name" @if($isOfferListing) required @endif>
                </div>
                @if($isOfferListing) @error('client_name') <span class="error">{{ $message }}</span> @enderror @endif
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Phone Number @if($isOfferListing)<span class="text-danger">*</span>@endif</label>
                <div class="input-cover">
                    <input type="text" class="form-control has-icon" data-icon="fa-solid fa-phone" wire:model="client_phone" placeholder="Client's phone number" @if($isOfferListing) required @endif>
                </div>
                @if($isOfferListing) @error('client_phone') <span class="error">{{ $message }}</span> @enderror @endif
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Email Address @if($isOfferListing)<span class="text-danger">*</span>@endif</label>
                <div class="input-cover">
                    <input type="email" class="form-control has-icon" data-icon="fa-solid fa-envelope" wire:model="client_email" placeholder="Client's email address" @if($isOfferListing) required @endif>
                </div>
                @if($isOfferListing) @error('client_email') <span class="error">{{ $message }}</span> @enderror @endif
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
                <label class="fw-bold">Street Address @if($isOfferListing)<span class="text-danger">*</span>@endif</label>
                <div class="input-cover">
                    <input type="text" class="form-control has-icon" data-icon="fa-solid fa-location-dot" wire:model="client_property_address" placeholder="Street address" @if($isOfferListing) required @endif>
                </div>
                @if($isOfferListing) @error('client_property_address') <span class="error">{{ $message }}</span> @enderror @endif
            </div>
            <div class="col-md-5 mb-3">
                <label class="fw-bold">City @if($isOfferListing)<span class="text-danger">*</span>@endif</label>
                <div class="input-cover">
                    <input type="text" class="form-control has-icon" data-icon="fa-solid fa-city" wire:model="client_property_city" placeholder="City" @if($isOfferListing) required @endif>
                </div>
                @if($isOfferListing) @error('client_property_city') <span class="error">{{ $message }}</span> @enderror @endif
            </div>
            <div class="col-md-4 mb-3">
                <label class="fw-bold">State @if($isOfferListing)<span class="text-danger">*</span>@endif</label>
                <div class="input-cover">
                    <input type="text" class="form-control has-icon" data-icon="fa-solid fa-map" wire:model="client_property_state" placeholder="State" @if($isOfferListing) required @endif>
                </div>
                @if($isOfferListing) @error('client_property_state') <span class="error">{{ $message }}</span> @enderror @endif
            </div>
            <div class="col-md-3 mb-3">
                <label class="fw-bold">ZIP Code @if($isOfferListing)<span class="text-danger">*</span>@endif</label>
                <div class="input-cover">
                    <input type="text" class="form-control has-icon" data-icon="fa-solid fa-hashtag" wire:model="client_property_zip" placeholder="ZIP" @if($isOfferListing) required @endif>
                </div>
                @if($isOfferListing) @error('client_property_zip') <span class="error">{{ $message }}</span> @enderror @endif
            </div>
        </div>
    </div>
</div>
