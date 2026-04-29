
<!-- Early Termination Fee -->
<div class="form-group mb-4">
    @if ($property_type === 'Residential Property')
        <label class="fw-bold d-flex align-items-center">
            Early Termination Fee:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select whether the Landlord agrees to pay a cancellation fee if the agreement is conditionally terminated before the end of the Listing Period. If “Yes” is selected, you’ll be prompted to enter the fee amount. The fee is due at the time of withdrawal and helps offset marketing costs. If the property is leased during the remaining Listing or Protection Period, the Broker may void the early termination, and the full commission may still apply, minus the cancellation fee.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover mt-2">
            <select wire:model.lazy="early_termination_fee_option" class="form-control has-icon"
                data-icon="fa-solid fa-triangle-exclamation">
                <option value="">Select</option>
                <option value="yes">Yes</option>
                <option value="no">No</option>
            </select>
        </div>
    @endif

    {{-- @if ($property_type === 'Commercial Property')
        <label class="fw-bold d-flex align-items-center">
            Early Termination Fee:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="If the Owner cancels the agreement early, they must sign a withdrawal form and may owe a cancellation fee. If the property is leased during the remaining agreement term or within the protection period to a prospect the Broker (or another broker) communicated with during the agreement, the Broker may still be entitled to full commission. The protection period does not apply if the Owner signs a new exclusive agreement in good faith with another broker after
this agreement ends.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover mt-2">
            <select wire:model.lazy="early_termination_fee_option" class="form-control has-icon"
                data-icon="fa-solid fa-triangle-exclamation">
                <option value="">Select</option>
                <option value="yes">Yes</option>
                <option value="no">No</option>
            </select>
        </div>
    @endif --}}

    @if ($early_termination_fee_option === 'yes')
        <div class="mt-3">
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="text" wire:model.lazy="early_termination_fee_amount" class="form-control"
                    placeholder="Enter early termination fee amount (e.g., 1000)"
                    data-error-id="early_termination_fee_amount_error" oninput="validateInput(this)"
                    onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            </div>
            @error('early_termination_fee_amount')
                <span class="text-danger small">{{ $message }}</span>
            @enderror
            <span class="error mt-2" id="early_termination_fee_amount_error"></span>
        </div>
    @endif
</div>