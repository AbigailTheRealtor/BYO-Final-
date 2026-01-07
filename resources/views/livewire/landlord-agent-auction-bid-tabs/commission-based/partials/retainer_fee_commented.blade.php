
<!-- Retainer Fee -->
{{-- <div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Retainer Fee:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether you agree to pay a non-refundable retainer fee to initiate Broker services. The retainer is separate from any commission earned unless otherwise specified.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover mt-2">
        <select wire:model="retainer_fee_option" class="form-control has-icon"
            data-icon="fa-solid fa-file-invoice-dollar">
            <option value="">Select</option>
            <option value="yes">Yes</option>
            <option value="no">No</option>
        </select>
    </div>

    @if ($retainer_fee_option === 'yes')
        <div class="mt-3">
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" wire:model="retainer_fee_amount" class="form-control"
                    placeholder="Enter retainer fee amount (e.g., 500)">
            </div>
            @error('retainer_fee_amount')
                <span class="text-danger small">{{ $message }}</span>
            @enderror

            <div class="mt-3">
                <label class="fw-bold d-flex align-items-center">
                    Retainer Fee Application:

                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Select whether the retainer fee will be credited toward the final commission owed or charged in addition to it.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                </label>
                <div class="input-cover mt-2">

                    <select wire:model="retainer_fee_application" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler">
                        <option value="">Select application method</option>
                        <option value="applied">Applied toward final compensation</option>
                        <option value="additional">Charged in addition to final compensation</option>
                    </select>
                    @error('retainer_fee_application')
                        <span class="text-danger small">{{ $message }}</span>
                    @enderror
                </div>
            </div>
        </div>
    @endif
</div> --}}