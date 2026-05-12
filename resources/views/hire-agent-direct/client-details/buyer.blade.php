{{-- Buyer Client Details — mirrors BuyerAgentAuctionCounterTerm counter-terms partial --}}
<div class="ack-section-header"><i class="fa-solid fa-address-card"></i> Client Details</div>
<div class="ack-section-body">
    <p class="text-muted small mb-3">Provide your details so the agent can assist with your search. Fields marked <span class="text-danger">*</span> are required.</p>

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

    {{-- Location & Target --}}
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Location &amp; Target</h5></div>
        <div class="card-body">
            <div class="form-group mb-0">
                <label class="fw-bold form-label">Areas of Interest <span class="text-danger">*</span></label>
                <input type="text" name="areas_of_interest"
                       class="form-control @error('areas_of_interest') is-invalid @enderror"
                       placeholder="e.g. Downtown, Westside, North suburbs"
                       value="{{ old('areas_of_interest') }}" required>
                @error('areas_of_interest')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    {{-- Deal Terms --}}
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Deal Terms</h5></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">Target Purchase Price / Budget</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="text" name="target_purchase_price"
                               class="form-control @error('target_purchase_price') is-invalid @enderror"
                               placeholder="e.g. 350,000"
                               value="{{ old('target_purchase_price') }}">
                        @error('target_purchase_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">Timeline to Purchase</label>
                    <select name="timeline_to_purchase" class="form-control @error('timeline_to_purchase') is-invalid @enderror">
                        <option value="">-- Select timeline --</option>
                        <option value="As soon as possible" {{ old('timeline_to_purchase') === 'As soon as possible' ? 'selected' : '' }}>As soon as possible</option>
                        <option value="1–3 months" {{ old('timeline_to_purchase') === '1–3 months' ? 'selected' : '' }}>1–3 months</option>
                        <option value="3–6 months" {{ old('timeline_to_purchase') === '3–6 months' ? 'selected' : '' }}>3–6 months</option>
                        <option value="6–12 months" {{ old('timeline_to_purchase') === '6–12 months' ? 'selected' : '' }}>6–12 months</option>
                        <option value="12+ months" {{ old('timeline_to_purchase') === '12+ months' ? 'selected' : '' }}>12+ months</option>
                        <option value="Flexible" {{ old('timeline_to_purchase') === 'Flexible' ? 'selected' : '' }}>Flexible</option>
                    </select>
                    @error('timeline_to_purchase')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                    <label class="fw-bold form-label">Pre-Approval Status</label>
                    <select name="pre_approval_status" class="form-control @error('pre_approval_status') is-invalid @enderror">
                        <option value="">-- Select status --</option>
                        <option value="Pre-approved" {{ old('pre_approval_status') === 'Pre-approved' ? 'selected' : '' }}>Pre-approved</option>
                        <option value="Pre-qualified" {{ old('pre_approval_status') === 'Pre-qualified' ? 'selected' : '' }}>Pre-qualified</option>
                        <option value="In process" {{ old('pre_approval_status') === 'In process' ? 'selected' : '' }}>In process</option>
                        <option value="Not yet started" {{ old('pre_approval_status') === 'Not yet started' ? 'selected' : '' }}>Not yet started</option>
                    </select>
                    @error('pre_approval_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">Cash Buyer?</label>
                    <select name="cash_buyer" class="form-control @error('cash_buyer') is-invalid @enderror">
                        <option value="">-- Select --</option>
                        <option value="Yes" {{ old('cash_buyer') === 'Yes' ? 'selected' : '' }}>Yes</option>
                        <option value="No" {{ old('cash_buyer') === 'No' ? 'selected' : '' }}>No</option>
                    </select>
                    @error('cash_buyer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">Estimated Down Payment <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="text" name="estimated_down_payment"
                           class="form-control @error('estimated_down_payment') is-invalid @enderror"
                           placeholder="e.g. 20% or 70,000"
                           value="{{ old('estimated_down_payment') }}">
                    @error('estimated_down_payment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>
</div>
