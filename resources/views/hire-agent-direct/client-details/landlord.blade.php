{{-- Landlord Client Details — mirrors LandlordAgentAuctionCounterTerm counter-terms partial --}}
<div class="ack-section-header"><i class="fa-solid fa-address-card"></i> Client Details</div>
<div class="ack-section-body">
    <p class="text-muted small mb-3">Provide your details so the agent can prepare for your rental. Fields marked <span class="text-danger">*</span> are required.</p>

    {{-- Client Contact Information --}}
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Client Contact Information</h5></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="fw-bold form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="client_name"
                           class="form-control @error('client_name') is-invalid @enderror"
                           placeholder="Client's full name"
                           value="{{ old('client_name') }}" required>
                    @error('client_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">Phone Number <span class="text-danger">*</span></label>
                    <input type="text" name="client_phone"
                           class="form-control @error('client_phone') is-invalid @enderror"
                           placeholder="Client's phone number"
                           value="{{ old('client_phone') }}" required>
                    @error('client_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="client_email"
                           class="form-control @error('client_email') is-invalid @enderror"
                           placeholder="Client's email address"
                           value="{{ old('client_email') }}" required>
                    @error('client_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                    <label class="fw-bold form-label">Street Address <span class="text-danger">*</span></label>
                    <input type="text" name="client_property_address"
                           class="form-control @error('client_property_address') is-invalid @enderror"
                           placeholder="Street address"
                           value="{{ old('client_property_address') }}" required>
                    @error('client_property_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-5 mb-3">
                    <label class="fw-bold form-label">City <span class="text-danger">*</span></label>
                    <input type="text" name="client_property_city"
                           class="form-control @error('client_property_city') is-invalid @enderror"
                           placeholder="City"
                           value="{{ old('client_property_city') }}" required>
                    @error('client_property_city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 mb-3">
                    <label class="fw-bold form-label">State <span class="text-danger">*</span></label>
                    <input type="text" name="client_property_state"
                           class="form-control @error('client_property_state') is-invalid @enderror"
                           placeholder="State"
                           value="{{ old('client_property_state') }}" required>
                    @error('client_property_state')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3 mb-3">
                    <label class="fw-bold form-label">ZIP Code <span class="text-danger">*</span></label>
                    <input type="text" name="client_property_zip"
                           class="form-control @error('client_property_zip') is-invalid @enderror"
                           placeholder="ZIP"
                           value="{{ old('client_property_zip') }}" required>
                    @error('client_property_zip')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                    <label class="fw-bold form-label">Desired Monthly Rent <span class="text-muted fw-normal">(optional)</span></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="text" name="desired_monthly_rent"
                               class="form-control @error('desired_monthly_rent') is-invalid @enderror"
                               placeholder="e.g. 2,200"
                               value="{{ old('desired_monthly_rent') }}">
                        @error('desired_monthly_rent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">Availability Date</label>
                    <input type="date" name="availability_date"
                           class="form-control @error('availability_date') is-invalid @enderror"
                           value="{{ old('availability_date') }}">
                    @error('availability_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                    <label class="fw-bold form-label">Occupancy Status</label>
                    <select name="occupancy_status" class="form-control @error('occupancy_status') is-invalid @enderror">
                        <option value="">-- Select status --</option>
                        <option value="Vacant – ready now" {{ old('occupancy_status') === 'Vacant – ready now' ? 'selected' : '' }}>Vacant – ready now</option>
                        <option value="Occupied – tenant leaving soon" {{ old('occupancy_status') === 'Occupied – tenant leaving soon' ? 'selected' : '' }}>Occupied – tenant leaving soon</option>
                        <option value="Owner-occupied" {{ old('occupancy_status') === 'Owner-occupied' ? 'selected' : '' }}>Owner-occupied</option>
                        <option value="Occupied – lease active" {{ old('occupancy_status') === 'Occupied – lease active' ? 'selected' : '' }}>Occupied – lease active</option>
                    </select>
                    @error('occupancy_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">Flexibility</label>
                    <select name="flexibility" class="form-control @error('flexibility') is-invalid @enderror">
                        <option value="">-- Select flexibility --</option>
                        <option value="Very flexible" {{ old('flexibility') === 'Very flexible' ? 'selected' : '' }}>Very flexible</option>
                        <option value="Somewhat flexible" {{ old('flexibility') === 'Somewhat flexible' ? 'selected' : '' }}>Somewhat flexible</option>
                        <option value="Not flexible – firm on terms" {{ old('flexibility') === 'Not flexible – firm on terms' ? 'selected' : '' }}>Not flexible – firm on terms</option>
                    </select>
                    @error('flexibility')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>
</div>
