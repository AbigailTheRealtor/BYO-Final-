{{-- Tenant Client Details — mirrors TenantAgentAuctionCounterTerm counter-terms partial --}}
<div class="ack-section-header"><i class="fa-solid fa-address-card"></i> Client Details</div>
<div class="ack-section-body">
    <p class="text-muted small mb-3">Provide your details so the agent can assist with your search. Fields marked <span class="text-danger">*</span> are required.</p>

    {{-- Client Contact Information --}}
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Client Contact Information</h5></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="client_first_name"
                           class="form-control @error('client_first_name') is-invalid @enderror"
                           placeholder="Enter first name"
                           value="{{ old('client_first_name') }}" required>
                    @error('client_first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="client_last_name"
                           class="form-control @error('client_last_name') is-invalid @enderror"
                           placeholder="Enter last name"
                           value="{{ old('client_last_name') }}" required>
                    @error('client_last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">Phone Number <span class="text-danger">*</span></label>
                    <input type="text" name="client_phone" id="tenant_client_phone"
                           class="form-control @error('client_phone') is-invalid @enderror"
                           placeholder="(555) 555-5555"
                           value="{{ old('client_phone') }}" required>
                    @error('client_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="client_email"
                           class="form-control @error('client_email') is-invalid @enderror"
                           placeholder="Enter email address"
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
                       placeholder="Enter areas of interest (e.g., Downtown, Midtown, Suburb areas)"
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
                    <label class="fw-bold form-label">Max Monthly Lease Price</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="text" name="max_monthly_lease_price"
                               class="form-control @error('max_monthly_lease_price') is-invalid @enderror"
                               placeholder="Enter max monthly lease price (e.g., 2,500)"
                               value="{{ old('max_monthly_lease_price') }}">
                        @error('max_monthly_lease_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">Desired Lease Length</label>
                    <select name="desired_lease_length" class="form-control @error('desired_lease_length') is-invalid @enderror">
                        <option value="">-- Select length --</option>
                        <option value="Month-to-month" {{ old('desired_lease_length') === 'Month-to-month' ? 'selected' : '' }}>Month-to-month</option>
                        <option value="6 months" {{ old('desired_lease_length') === '6 months' ? 'selected' : '' }}>6 months</option>
                        <option value="12 months" {{ old('desired_lease_length') === '12 months' ? 'selected' : '' }}>12 months</option>
                        <option value="18 months" {{ old('desired_lease_length') === '18 months' ? 'selected' : '' }}>18 months</option>
                        <option value="24 months" {{ old('desired_lease_length') === '24 months' ? 'selected' : '' }}>24 months</option>
                        <option value="Flexible" {{ old('desired_lease_length') === 'Flexible' ? 'selected' : '' }}>Flexible</option>
                    </select>
                    @error('desired_lease_length')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">Move-In Date</label>
                    <input type="date" name="move_in_date"
                           class="form-control @error('move_in_date') is-invalid @enderror"
                           value="{{ old('move_in_date') }}">
                    @error('move_in_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                    <label class="fw-bold form-label">Number of Occupants</label>
                    <input type="text" name="number_of_occupants"
                           class="form-control @error('number_of_occupants') is-invalid @enderror"
                           placeholder="Enter number of occupants (e.g., 2 adults, 1 child)"
                           value="{{ old('number_of_occupants') }}">
                    @error('number_of_occupants')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">Household Monthly Income</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="text" name="household_monthly_income"
                               class="form-control @error('household_monthly_income') is-invalid @enderror"
                               placeholder="Enter household monthly income (e.g., 7,500)"
                               value="{{ old('household_monthly_income') }}">
                        @error('household_monthly_income')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    var phoneInput = document.getElementById('tenant_client_phone');
    if (!phoneInput) return;
    phoneInput.addEventListener('input', function() {
        var digits = this.value.replace(/\D/g, '').substring(0, 10);
        var formatted = digits;
        if (digits.length >= 7) {
            formatted = digits.substring(0,3) + '-' + digits.substring(3,6) + '-' + digits.substring(6);
        } else if (digits.length >= 4) {
            formatted = digits.substring(0,3) + '-' + digits.substring(3);
        }
        this.value = formatted;
    });
})();
</script>
@endpush
