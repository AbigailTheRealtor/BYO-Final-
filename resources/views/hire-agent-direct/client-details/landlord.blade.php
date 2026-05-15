{{-- Landlord Client Details — mirrors LandlordAgentAuctionCounterTerm counter-terms partial --}}
<div class="ack-section-header"><i class="fa-solid fa-address-card"></i> Client Details</div>
<div class="ack-section-body">
    <p class="text-muted small mb-3">Provide your details so the agent can prepare for your rental. Fields marked <span class="text-danger">*</span> are required.</p>

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
                    <input type="text" name="client_phone" id="landlord_client_phone"
                           class="form-control @error('client_phone') is-invalid @enderror"
                           placeholder="Enter phone number"
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
                           placeholder="Enter city (e.g., Tampa)"
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
                           placeholder="Enter ZIP code (e.g., 33602)"
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
                               placeholder="Enter desired monthly rent (e.g., 2500)"
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

    {{-- Preferred Communication & Top Priority --}}
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Preferences</h5></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">Preferred Communication Method</label>
                    <select name="preferred_comm_method" class="form-control @error('preferred_comm_method') is-invalid @enderror"
                            onchange="landlordTogglePreferredComm(this.value)">
                        <option value="">Select</option>
                        <option value="Call" {{ old('preferred_comm_method') === 'Call' ? 'selected' : '' }}>Call</option>
                        <option value="Text" {{ old('preferred_comm_method') === 'Text' ? 'selected' : '' }}>Text</option>
                        <option value="Email" {{ old('preferred_comm_method') === 'Email' ? 'selected' : '' }}>Email</option>
                        <option value="Video Call" {{ old('preferred_comm_method') === 'Video Call' ? 'selected' : '' }}>Video Call</option>
                        <option value="Any" {{ old('preferred_comm_method') === 'Any' ? 'selected' : '' }}>Any</option>
                        <option value="Other" {{ old('preferred_comm_method') === 'Other' ? 'selected' : '' }}>Other</option>
                    </select>
                    @error('preferred_comm_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div id="landlord_preferred_comm_method_other_wrap" class="mt-2"
                         style="{{ old('preferred_comm_method') === 'Other' ? '' : 'display:none;' }}">
                        <input type="text" name="preferred_comm_method_other" id="landlord_preferred_comm_method_other"
                               class="form-control"
                               placeholder="Enter preferred method (e.g., In-Person Meeting)"
                               value="{{ old('preferred_comm_method_other') }}">
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">Top Priority</label>
                    <select name="top_priority" class="form-control @error('top_priority') is-invalid @enderror"
                            onchange="landlordToggleTopPriority(this.value)">
                        <option value="">Select</option>
                        <option value="Finding a reliable tenant" {{ old('top_priority') === 'Finding a reliable tenant' ? 'selected' : '' }}>Finding a reliable tenant</option>
                        <option value="Maximizing rental income" {{ old('top_priority') === 'Maximizing rental income' ? 'selected' : '' }}>Maximizing rental income</option>
                        <option value="Minimizing vacancy time" {{ old('top_priority') === 'Minimizing vacancy time' ? 'selected' : '' }}>Minimizing vacancy time</option>
                        <option value="Property management assistance" {{ old('top_priority') === 'Property management assistance' ? 'selected' : '' }}>Property management assistance</option>
                        <option value="Other" {{ old('top_priority') === 'Other' ? 'selected' : '' }}>Other</option>
                    </select>
                    @error('top_priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div id="landlord_top_priority_other_wrap" class="mt-2"
                         style="{{ old('top_priority') === 'Other' ? '' : 'display:none;' }}">
                        <input type="text" name="top_priority_other" id="landlord_top_priority_other"
                               class="form-control"
                               placeholder="Enter your top priority (e.g., No pets allowed)"
                               value="{{ old('top_priority_other') }}">
                    </div>
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
                        <option value="">Select</option>
                        <option value="Vacant – ready now" {{ old('occupancy_status') === 'Vacant – ready now' ? 'selected' : '' }}>Vacant – ready now</option>
                        <option value="Occupied – tenant leaving soon" {{ old('occupancy_status') === 'Occupied – tenant leaving soon' ? 'selected' : '' }}>Occupied – tenant leaving soon</option>
                        <option value="Owner-occupied" {{ old('occupancy_status') === 'Owner-occupied' ? 'selected' : '' }}>Owner-occupied</option>
                        <option value="Occupied – lease active" {{ old('occupancy_status') === 'Occupied – lease active' ? 'selected' : '' }}>Occupied – lease active</option>
                    </select>
                    @error('occupancy_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">Desired Lease Term</label>
                    <select name="desired_lease_term" id="desired_lease_term"
                            class="form-control @error('desired_lease_term') is-invalid @enderror"
                            onchange="landlordToggleLeaseTerm(this.value)">
                        <option value="">Select</option>
                        <option value="Month-to-Month" {{ old('desired_lease_term') === 'Month-to-Month' ? 'selected' : '' }}>Month-to-Month</option>
                        <option value="3 Months" {{ old('desired_lease_term') === '3 Months' ? 'selected' : '' }}>3 Months</option>
                        <option value="6 Months" {{ old('desired_lease_term') === '6 Months' ? 'selected' : '' }}>6 Months</option>
                        <option value="9 Months" {{ old('desired_lease_term') === '9 Months' ? 'selected' : '' }}>9 Months</option>
                        <option value="1 Year" {{ old('desired_lease_term') === '1 Year' ? 'selected' : '' }}>1 Year</option>
                        <option value="2 Years" {{ old('desired_lease_term') === '2 Years' ? 'selected' : '' }}>2 Years</option>
                        <option value="Other" {{ old('desired_lease_term') === 'Other' ? 'selected' : '' }}>Other</option>
                    </select>
                    @error('desired_lease_term')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div id="desired_lease_term_other_wrap" class="mt-2"
                         style="{{ old('desired_lease_term') === 'Other' ? '' : 'display:none;' }}">
                        <input type="text" name="desired_lease_term_other" id="desired_lease_term_other"
                               class="form-control"
                               placeholder="Enter desired lease term (e.g., 8 Months)"
                               value="{{ old('desired_lease_term_other') }}">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function landlordTogglePreferredComm(val) {
    var wrap = document.getElementById('landlord_preferred_comm_method_other_wrap');
    var inp  = document.getElementById('landlord_preferred_comm_method_other');
    if (!wrap) return;
    if (val === 'Other') {
        wrap.style.display = '';
    } else {
        wrap.style.display = 'none';
        if (inp) inp.value = '';
    }
}

function landlordToggleTopPriority(val) {
    var wrap = document.getElementById('landlord_top_priority_other_wrap');
    var inp  = document.getElementById('landlord_top_priority_other');
    if (!wrap) return;
    if (val === 'Other') {
        wrap.style.display = '';
    } else {
        wrap.style.display = 'none';
        if (inp) inp.value = '';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    var commSel = document.querySelector('select[name="preferred_comm_method"]');
    if (commSel) landlordTogglePreferredComm(commSel.value);
    var priSel = document.querySelector('select[name="top_priority"]');
    if (priSel) landlordToggleTopPriority(priSel.value);
    var sel = document.getElementById('desired_lease_term');
    if (sel) {
        landlordToggleLeaseTerm(sel.value);
    }
});

(function () {
    // ── Phone formatting ─────────────────────────────────────────────────────
    var phoneInput = document.getElementById('landlord_client_phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function () {
            var digits = this.value.replace(/\D/g, '').substring(0, 10);
            var formatted = digits;
            if (digits.length >= 7) {
                formatted = digits.substring(0, 3) + '-' + digits.substring(3, 6) + '-' + digits.substring(6);
            } else if (digits.length >= 4) {
                formatted = digits.substring(0, 3) + '-' + digits.substring(3);
            }
            this.value = formatted;
        });
    }

    // ── Currency helpers ─────────────────────────────────────────────────────
    function normalizeDecimal(val) {
        var raw = val.replace(/[^0-9.]/g, '');
        var firstDot = raw.indexOf('.');
        if (firstDot !== -1) {
            raw = raw.substring(0, firstDot + 1) + raw.substring(firstDot + 1).replace(/\./g, '');
        }
        return raw;
    }

    function formatWithCommas(val) {
        var raw = normalizeDecimal(val);
        var parts = raw.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return parts.length > 1 ? parts[0] + '.' + parts[1] : parts[0];
    }

    // ── Desired Monthly Rent ─────────────────────────────────────────────────
    var dmrInput = document.querySelector('input[name="desired_monthly_rent"]');
    if (dmrInput) {
        if (dmrInput.value !== '') {
            dmrInput.value = formatWithCommas(dmrInput.value);
        }
        dmrInput.addEventListener('input', function () {
            this.value = formatWithCommas(this.value);
        });
    }
}());

// ── Desired Lease Term show/hide ─────────────────────────────────────────────
function landlordToggleLeaseTerm(val) {
    var wrap = document.getElementById('desired_lease_term_other_wrap');
    var inp  = document.getElementById('desired_lease_term_other');
    if (!wrap) return;
    if (val === 'Other') {
        wrap.style.display = '';
    } else {
        wrap.style.display = 'none';
        if (inp) inp.value = '';
    }
}

</script>
@endpush
