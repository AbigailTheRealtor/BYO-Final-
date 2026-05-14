{{-- Seller Client Details — mirrors SellerAgentAuctionCounterTerm counter-terms partial --}}
<div class="ack-section-header"><i class="fa-solid fa-address-card"></i> Client Details</div>
<div class="ack-section-body">
    <p class="text-muted small mb-3">Provide your details so the agent can prepare for your listing. Fields marked <span class="text-danger">*</span> are required.</p>

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
                    <input type="text" name="client_phone" id="seller_client_phone"
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

    {{-- Property Address --}}
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Property Address</h5></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="fw-bold form-label">Street Address <span class="text-danger">*</span></label>
                    <input type="text" name="client_property_address"
                           class="form-control @error('client_property_address') is-invalid @enderror"
                           placeholder="Enter street address (e.g., 123 Main St)"
                           value="{{ old('client_property_address') }}" required>
                    @error('client_property_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-5 mb-3">
                    <label class="fw-bold form-label">City <span class="text-danger">*</span></label>
                    <input type="text" name="client_property_city"
                           class="form-control @error('client_property_city') is-invalid @enderror"
                           placeholder="Enter city"
                           value="{{ old('client_property_city') }}" required>
                    @error('client_property_city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 mb-3">
                    <label class="fw-bold form-label">State <span class="text-danger">*</span></label>
                    <input type="text" name="client_property_state"
                           class="form-control @error('client_property_state') is-invalid @enderror"
                           placeholder="Enter state (e.g., FL)"
                           value="{{ old('client_property_state') }}" required>
                    @error('client_property_state')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3 mb-3">
                    <label class="fw-bold form-label">ZIP Code <span class="text-danger">*</span></label>
                    <input type="text" name="client_property_zip"
                           class="form-control @error('client_property_zip') is-invalid @enderror"
                           placeholder="Enter ZIP (e.g., 33701)"
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
                    <label class="fw-bold form-label">Desired Sale Price <span class="text-muted fw-normal">(optional)</span></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="text" name="desired_sale_price" id="seller_desired_sale_price"
                               class="form-control @error('desired_sale_price') is-invalid @enderror"
                               placeholder="Enter desired sale price (e.g., 450,000)"
                               value="{{ old('desired_sale_price') }}">
                        @error('desired_sale_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">Timeline to Sell</label>
                    <select name="timeline_to_sell" class="form-control @error('timeline_to_sell') is-invalid @enderror">
                        <option value="">-- Select timeline --</option>
                        <option value="As soon as possible" {{ old('timeline_to_sell') === 'As soon as possible' ? 'selected' : '' }}>As soon as possible</option>
                        <option value="1–3 months" {{ old('timeline_to_sell') === '1–3 months' ? 'selected' : '' }}>1–3 months</option>
                        <option value="3–6 months" {{ old('timeline_to_sell') === '3–6 months' ? 'selected' : '' }}>3–6 months</option>
                        <option value="6–12 months" {{ old('timeline_to_sell') === '6–12 months' ? 'selected' : '' }}>6–12 months</option>
                        <option value="12+ months" {{ old('timeline_to_sell') === '12+ months' ? 'selected' : '' }}>12+ months</option>
                        <option value="Flexible" {{ old('timeline_to_sell') === 'Flexible' ? 'selected' : '' }}>Flexible</option>
                    </select>
                    @error('timeline_to_sell')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Qualification Signals --}}
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Qualification Signals</h5></div>
        <div class="card-body">
            <div class="form-group mb-0">
                <label class="fw-bold form-label">Motivation Level</label>
                <select name="motivation_level" class="form-control @error('motivation_level') is-invalid @enderror">
                    <option value="">-- Select motivation level --</option>
                    <option value="Must sell immediately" {{ old('motivation_level') === 'Must sell immediately' ? 'selected' : '' }}>Must sell immediately</option>
                    <option value="Motivated – prefer to sell soon" {{ old('motivation_level') === 'Motivated – prefer to sell soon' ? 'selected' : '' }}>Motivated – prefer to sell soon</option>
                    <option value="Flexible – willing to wait for the right offer" {{ old('motivation_level') === 'Flexible – willing to wait for the right offer' ? 'selected' : '' }}>Flexible – willing to wait for the right offer</option>
                    <option value="Testing the market" {{ old('motivation_level') === 'Testing the market' ? 'selected' : '' }}>Testing the market</option>
                </select>
                @error('motivation_level')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    function formatPhone(input) {
        var digits = input.value.replace(/\D/g, '').substring(0, 10);
        if (digits.length >= 7) {
            input.value = digits.substring(0, 3) + '-' + digits.substring(3, 6) + '-' + digits.substring(6);
        } else if (digits.length >= 4) {
            input.value = digits.substring(0, 3) + '-' + digits.substring(3);
        } else {
            input.value = digits;
        }
    }

    function formatSalePrice(input) {
        var raw = input.value.replace(/,/g, '').replace(/[^\d]/g, '');
        input.value = raw ? parseInt(raw, 10).toLocaleString('en-US') : '';
    }

    var phoneInput = document.getElementById('seller_client_phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function () { formatPhone(this); });
    }

    var salePriceInput = document.getElementById('seller_desired_sale_price');
    if (salePriceInput) {
        salePriceInput.addEventListener('input', function () { formatSalePrice(this); });
        if (salePriceInput.value) { formatSalePrice(salePriceInput); }
    }
}());
</script>
@endpush
