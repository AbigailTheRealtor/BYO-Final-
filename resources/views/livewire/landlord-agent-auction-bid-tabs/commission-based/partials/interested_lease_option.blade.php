
<div class="form-group mb-2">
    <label class="fw-bold d-flex align-items-center">
        Interested in Offering a Lease-Option Agreement:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether the Landlord is open to a lease with option to purchase. If “Yes” is selected, you'll be prompted to enter compensation details.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover mt-2">
        <select wire:model.lazy="interested_lease_option_agreement" class="form-control has-icon"
            data-icon="fa-solid fa-file-invoice-dollar">
            <option value="">Select</option>
            <option value="Yes">Yes</option>
            <option value="No">No</option>

        </select>
    </div>
</div>

@if ($interested_lease_option_agreement === 'Yes')
    <!-- TAB 1 -->
    <div id="tab1" class="tab-content">
        <h5 class="compensation_tab fw-bold mb-3" style="color: #049399;">
            Compensation for Creating the Lease-Option Agreement:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true" data-bs-trigger="hover focus"
                title="Specify how the Broker will be compensated at the time the lease-option agreement is created. This may include a flat fee or a percentage of the option consideration paid by the party granting the option. This compensation is typically paid upfront and is separate from any commission that may be owed if the purchase option is later exercised.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </h5>

        <div class="form-group mt-2">
            <label class="fw-bold d-block mb-1">Compensation Amount</label>

            <div class="input-group">
                <!-- Select for type -->
                <select wire:model.lazy="lease_type" wire:change="setType('lease', $event.target.value)"
                    class="form-select" style="max-width: 100px;">
                    <option value="percent">%</option>
                    <option value="flat">$</option>
                </select>

                <!-- Single input -->
                <input type="text" step="any" wire:model.lazy="lease_value" class="form-control"
                    placeholder="{{ $lease_type === 'percent'
                        ? 'Enter percentage of option consideration (e.g., 5)'
                        : 'Enter flat fee amount (e.g., 1500)' }}"
                    data-error-id="lease_value_error" oninput="validateInput(this)" onblur="reformatNumber(this)"
                    onpaste="handlePaste(event)">

                <!-- Suffix -->
                <span class="input-group-text">
                    {{ $lease_type === 'percent' ? '%' : '$' }}
                </span>
            </div>
            <span class="error mt-2" id="lease_value_error"></span>

        </div>
    </div>

    <!-- TAB 2 -->
    <div id="tab2" class="tab-content">
        <h5 class="compensation_tab fw-bold mb-3" style="color: #049399;">
            Compensation if Purchase Option is Exercised:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true" data-bs-trigger="hover focus"
                title="If the purchase option is exercised, the Broker may be entitled to additional compensation. Enter how the Broker will be compensated at that time, such as a flat fee or a percentage of the total purchase price. Any compensation already received under the lease-option agreement may be credited toward the final amount due, depending on the terms of the agreement.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </h5>

        <div class="form-group mt-2">
            <label class="fw-bold d-block mb-1">Compensation Amount</label>

            <div class="input-group">
                <!-- Select for type -->
                <select wire:model.lazy="purchase_type" wire:change="setType('purchase', $event.target.value)"
                    class="form-select" style="max-width: 100px;">
                    <option value="percent">%</option>
                    <option value="flat">$</option>
                </select>

                <!-- Single input -->
                <input type="text" step="any" wire:model.lazy="purchase_value" class="form-control"
                    placeholder="{{ $purchase_type === 'percent'
                        ? 'Enter percentage of the total purchase price (e.g., 6)'
                        : 'Enter flat fee amount (e.g., 5000)' }}"
                    data-error-id="purchase_value_error" oninput="validateInput(this)" onblur="reformatNumber(this)"
                    onpaste="handlePaste(event)">

                <!-- Suffix -->
                <span class="input-group-text">
                    {{ $purchase_type === 'percent' ? '%' : '$' }}
                </span>
            </div>
            <span class="error mt-2" id="purchase_value_error"></span>
        </div>

    </div>

    <div class="alert alert-warning mt-3 p-2 small">
        <strong>Note:</strong> Select $ or % to switch between entering a dollar amount or a percentage.
    </div>
@endif