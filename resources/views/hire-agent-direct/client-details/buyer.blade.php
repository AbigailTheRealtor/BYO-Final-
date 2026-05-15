{{-- Buyer Client Details — mirrors BuyerAgentAuctionCounterTerm counter-terms partial --}}
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
                    <input type="text" name="client_phone" id="buyer_client_phone"
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

    {{-- Location & Target --}}
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Location &amp; Target</h5></div>
        <div class="card-body">
            <div class="form-group mb-0">
                <label class="fw-bold form-label">Areas of Interest <span class="text-danger">*</span></label>
                <input type="text" name="areas_of_interest"
                       class="form-control @error('areas_of_interest') is-invalid @enderror"
                       placeholder="Enter areas of interest (e.g., Downtown, Westside, North suburbs)"
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
                               placeholder="Enter target purchase price (e.g., 350000)"
                               value="{{ old('target_purchase_price') }}">
                        @error('target_purchase_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">Timeline to Purchase</label>
                    <select name="timeline_to_purchase" class="form-control @error('timeline_to_purchase') is-invalid @enderror">
                        <option value="">Select</option>
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

    {{-- Preferred Communication & Top Priority --}}
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Preferences</h5></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">Preferred Communication Method</label>
                    <select name="preferred_comm_method" class="form-control @error('preferred_comm_method') is-invalid @enderror"
                            onchange="buyerTogglePreferredComm(this.value)">
                        <option value="">Select</option>
                        <option value="Call" {{ old('preferred_comm_method') === 'Call' ? 'selected' : '' }}>Call</option>
                        <option value="Text" {{ old('preferred_comm_method') === 'Text' ? 'selected' : '' }}>Text</option>
                        <option value="Email" {{ old('preferred_comm_method') === 'Email' ? 'selected' : '' }}>Email</option>
                        <option value="Video Call" {{ old('preferred_comm_method') === 'Video Call' ? 'selected' : '' }}>Video Call</option>
                        <option value="Any" {{ old('preferred_comm_method') === 'Any' ? 'selected' : '' }}>Any</option>
                        <option value="Other" {{ old('preferred_comm_method') === 'Other' ? 'selected' : '' }}>Other</option>
                    </select>
                    @error('preferred_comm_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div id="buyer_preferred_comm_method_other_wrap" class="mt-2"
                         style="{{ old('preferred_comm_method') === 'Other' ? '' : 'display:none;' }}">
                        <input type="text" name="preferred_comm_method_other" id="buyer_preferred_comm_method_other"
                               class="form-control"
                               placeholder="Enter preferred method (e.g., In-Person Meeting)"
                               value="{{ old('preferred_comm_method_other') }}">
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">Top Priority</label>
                    <select name="top_priority" class="form-control @error('top_priority') is-invalid @enderror"
                            onchange="buyerToggleTopPriority(this.value)">
                        <option value="">Select</option>
                        <option value="Finding the right property" {{ old('top_priority') === 'Finding the right property' ? 'selected' : '' }}>Finding the right property</option>
                        <option value="Getting the best purchase price" {{ old('top_priority') === 'Getting the best purchase price' ? 'selected' : '' }}>Getting the best purchase price</option>
                        <option value="Moving quickly" {{ old('top_priority') === 'Moving quickly' ? 'selected' : '' }}>Moving quickly</option>
                        <option value="Securing financing" {{ old('top_priority') === 'Securing financing' ? 'selected' : '' }}>Securing financing</option>
                        <option value="Other" {{ old('top_priority') === 'Other' ? 'selected' : '' }}>Other</option>
                    </select>
                    @error('top_priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div id="buyer_top_priority_other_wrap" class="mt-2"
                         style="{{ old('top_priority') === 'Other' ? '' : 'display:none;' }}">
                        <input type="text" name="top_priority_other" id="buyer_top_priority_other"
                               class="form-control"
                               placeholder="Enter your top priority (e.g., Specific school district)"
                               value="{{ old('top_priority_other') }}">
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Financing Details --}}
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Financing Details</h5></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">Financing Status</label>
                    <select name="financing_status" class="form-control @error('financing_status') is-invalid @enderror"
                            onchange="buyerToggleFinancingStatus(this.value)">
                        <option value="">Select</option>
                        <option value="Cash Buyer" {{ old('financing_status') === 'Cash Buyer' ? 'selected' : '' }}>Cash Buyer</option>
                        <option value="Pre-Approved" {{ old('financing_status') === 'Pre-Approved' ? 'selected' : '' }}>Pre-Approved</option>
                        <option value="Pre-Qualified" {{ old('financing_status') === 'Pre-Qualified' ? 'selected' : '' }}>Pre-Qualified</option>
                        <option value="Working With Lender" {{ old('financing_status') === 'Working With Lender' ? 'selected' : '' }}>Working With Lender</option>
                        <option value="Financing Needed" {{ old('financing_status') === 'Financing Needed' ? 'selected' : '' }}>Financing Needed</option>
                        <option value="Not Sure Yet" {{ old('financing_status') === 'Not Sure Yet' ? 'selected' : '' }}>Not Sure Yet</option>
                        <option value="Other" {{ old('financing_status') === 'Other' ? 'selected' : '' }}>Other</option>
                    </select>
                    @error('financing_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div id="buyer_financing_status_other_wrap" class="mt-2"
                         style="{{ old('financing_status') === 'Other' ? '' : 'display:none;' }}">
                        <input type="text" name="financing_status_other" id="buyer_financing_status_other"
                               class="form-control"
                               placeholder="Enter financing status (e.g., FHA loan in process)"
                               value="{{ old('financing_status_other') }}">
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold form-label">Estimated Down Payment <span class="text-muted fw-normal">(optional)</span></label>
                    <div class="input-group" id="down-payment-group">
                        <button type="button" id="dp-toggle-btn"
                                class="btn btn-outline-secondary btn-sm"
                                style="border-top-right-radius:0;border-bottom-right-radius:0;min-width:44px;font-weight:700;"
                                title="Toggle % or $">%</button>
                        <input type="hidden" name="down_payment_type" id="down_payment_type" value="{{ old('down_payment_type', 'percent') }}">
                        <input type="text" name="estimated_down_payment"
                               id="estimated_down_payment"
                               class="form-control @error('estimated_down_payment') is-invalid @enderror"
                               placeholder="Enter estimated down payment (e.g., 20)"
                               value="{{ old('estimated_down_payment') }}">
                        @error('estimated_down_payment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="text-muted small mt-1">Toggle between % (percentage) and $ (dollar amount).</div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function buyerTogglePreferredComm(val) {
    var wrap = document.getElementById('buyer_preferred_comm_method_other_wrap');
    var inp  = document.getElementById('buyer_preferred_comm_method_other');
    if (!wrap) return;
    if (val === 'Other') {
        wrap.style.display = '';
    } else {
        wrap.style.display = 'none';
        if (inp) inp.value = '';
    }
}

function buyerToggleTopPriority(val) {
    var wrap = document.getElementById('buyer_top_priority_other_wrap');
    var inp  = document.getElementById('buyer_top_priority_other');
    if (!wrap) return;
    if (val === 'Other') {
        wrap.style.display = '';
    } else {
        wrap.style.display = 'none';
        if (inp) inp.value = '';
    }
}

function buyerToggleFinancingStatus(val) {
    var wrap = document.getElementById('buyer_financing_status_other_wrap');
    var inp  = document.getElementById('buyer_financing_status_other');
    if (!wrap) return;
    if (val === 'Other') {
        wrap.style.display = '';
    } else {
        wrap.style.display = 'none';
        if (inp) inp.value = '';
    }
}

(function() {
    var phoneInput = document.getElementById('buyer_client_phone');
    if (phoneInput) {
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
    }

    // Shared helpers
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

    // Target Purchase Price — comma formatting
    var tppInput = document.querySelector('input[name="target_purchase_price"]');
    if (tppInput) {
        tppInput.addEventListener('input', function() {
            this.value = formatWithCommas(this.value);
        });
    }

    var dpToggleBtn  = document.getElementById('dp-toggle-btn');
    var dpTypeInput  = document.getElementById('down_payment_type');
    var dpAmtInput   = document.getElementById('estimated_down_payment');

    function applyDpMode(mode) {
        if (!dpToggleBtn || !dpTypeInput || !dpAmtInput) return;
        if (mode === 'dollar') {
            dpToggleBtn.textContent = '$';
            dpTypeInput.value = 'dollar';
            dpAmtInput.placeholder = 'Enter estimated down payment (e.g., 70000)';
            if (dpAmtInput.value !== '') {
                dpAmtInput.value = formatWithCommas(dpAmtInput.value);
            }
        } else {
            dpToggleBtn.textContent = '%';
            dpTypeInput.value = 'percent';
            dpAmtInput.placeholder = 'Enter estimated down payment (e.g., 20)';
            if (dpAmtInput.value !== '') {
                dpAmtInput.value = normalizeDecimal(dpAmtInput.value);
            }
        }
    }

    // Init from old() value
    applyDpMode(dpTypeInput ? dpTypeInput.value : 'percent');

    if (dpToggleBtn) {
        dpToggleBtn.addEventListener('click', function() {
            var current = dpTypeInput.value === 'dollar' ? 'dollar' : 'percent';
            applyDpMode(current === 'dollar' ? 'percent' : 'dollar');
        });
    }

    if (dpAmtInput) {
        dpAmtInput.addEventListener('input', function() {
            if (dpTypeInput.value === 'dollar') {
                this.value = formatWithCommas(this.value);
            } else {
                this.value = normalizeDecimal(this.value);
            }
        });
    }

    // Strip formatting before form submission — both fields, both modes
    var buyerForm = tppInput ? tppInput.closest('form') : null;
    if (buyerForm) {
        buyerForm.addEventListener('submit', function() {
            if (tppInput) {
                tppInput.value = tppInput.value.replace(/[$,]/g, '');
            }
            if (dpAmtInput) {
                dpAmtInput.value = dpAmtInput.value.replace(/[$,]/g, '');
            }
        });
    }

    var commSel = document.querySelector('select[name="preferred_comm_method"]');
    if (commSel) { buyerTogglePreferredComm(commSel.value); }
    var priSel = document.querySelector('select[name="top_priority"]');
    if (priSel) { buyerToggleTopPriority(priSel.value); }
    var finSel = document.querySelector('select[name="financing_status"]');
    if (finSel) { buyerToggleFinancingStatus(finSel.value); }
})();
</script>
@endpush
