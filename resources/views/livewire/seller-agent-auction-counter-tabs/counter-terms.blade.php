<h4>Client Details</h4>

{{-- Client Contact Info --}}
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Client Contact Information</h5></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-12 mb-3">
                <label class="fw-bold">Full Name @if($isOfferListing)<span class="text-danger">*</span>@endif</label>
                <input type="text" class="form-control" wire:model="client_name" placeholder="Client's full name" @if($isOfferListing) required @endif>
                @if($isOfferListing) @error('client_name') <span class="error">{{ $message }}</span> @enderror @endif
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Phone Number @if($isOfferListing)<span class="text-danger">*</span>@endif</label>
                <input type="text" class="form-control" wire:model="client_phone" placeholder="Client's phone number" @if($isOfferListing) required @endif>
                @if($isOfferListing) @error('client_phone') <span class="error">{{ $message }}</span> @enderror @endif
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Email Address @if($isOfferListing)<span class="text-danger">*</span>@endif</label>
                <input type="email" class="form-control" wire:model="client_email" placeholder="Client's email address" @if($isOfferListing) required @endif>
                @if($isOfferListing) @error('client_email') <span class="error">{{ $message }}</span> @enderror @endif
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
                <label class="fw-bold">Street Address @if($isOfferListing)<span class="text-danger">*</span>@endif</label>
                <input type="text" class="form-control" wire:model="client_property_address" placeholder="Street address" @if($isOfferListing) required @endif>
                @if($isOfferListing) @error('client_property_address') <span class="error">{{ $message }}</span> @enderror @endif
            </div>
            <div class="col-md-5 mb-3">
                <label class="fw-bold">City @if($isOfferListing)<span class="text-danger">*</span>@endif</label>
                <input type="text" class="form-control" wire:model="client_property_city" placeholder="City" @if($isOfferListing) required @endif>
                @if($isOfferListing) @error('client_property_city') <span class="error">{{ $message }}</span> @enderror @endif
            </div>
            <div class="col-md-4 mb-3">
                <label class="fw-bold">State @if($isOfferListing)<span class="text-danger">*</span>@endif</label>
                <input type="text" class="form-control" wire:model="client_property_state" placeholder="State" @if($isOfferListing) required @endif>
                @if($isOfferListing) @error('client_property_state') <span class="error">{{ $message }}</span> @enderror @endif
            </div>
            <div class="col-md-3 mb-3">
                <label class="fw-bold">ZIP Code @if($isOfferListing)<span class="text-danger">*</span>@endif</label>
                <input type="text" class="form-control" wire:model="client_property_zip" placeholder="ZIP" @if($isOfferListing) required @endif>
                @if($isOfferListing) @error('client_property_zip') <span class="error">{{ $message }}</span> @enderror @endif
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
                <label class="fw-bold">Desired Sale Price <span class="text-muted fw-normal">(optional)</span></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="text" class="form-control" wire:model="desired_sale_price" placeholder="e.g. 450,000">
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Timeline to Sell</label>
                <select class="form-control" wire:model="timeline_to_sell">
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
        <div class="form-group mb-0">
            <label class="fw-bold">Motivation Level</label>
            <select class="form-control" wire:model="motivation_level">
                <option value="">-- Select motivation level --</option>
                <option value="Must sell immediately">Must sell immediately</option>
                <option value="Motivated – prefer to sell soon">Motivated – prefer to sell soon</option>
                <option value="Flexible – willing to wait for the right offer">Flexible – willing to wait for the right offer</option>
                <option value="Testing the market">Testing the market</option>
            </select>
        </div>
    </div>
</div>
