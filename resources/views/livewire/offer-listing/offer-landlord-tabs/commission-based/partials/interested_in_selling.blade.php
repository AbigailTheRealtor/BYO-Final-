
<div class="form-group mb-4 mt-3">
    <label class="fw-bold">Interested in Selling:</label>

    <span class="ms-2 " data-bs-toggle="tooltip" data-bs-html="true"
        title="Select whether the Landlord is interested in selling the property. If “Yes” is selected, you’ll be prompted to enter compensation details.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    <div class="input-cover mt-2">

        <select wire:model.lazy="interested_in_selling" class="form-control has-icon" data-icon="fa-solid fa-ruler">
            <option value="">Select</option>
            <option value="Yes">Yes</option>
            <option value="No">No</option>
        </select>
    </div>
</div>

<!-- Lease-Option Fee Section (Conditional) -->
@if ($interested_in_selling === 'Yes')
    <div class="form-group mb-4">
        <label class="fw-bold d-flex align-items-center">
            Landlord’s Broker Purchase Fee:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Choose how the Landlord’s Broker will be compensated if the property is sold. Options include: a percentage of the purchase price, a percentage of the purchase price plus a flat fee, a flat fee, or “Other” to define a custom structure. Then, enter the appropriate amount(s) based on your selection.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

        <div class="input-cover mt-2">
            <select wire:model.lazy="interested_in_selling_type" class="form-control has-icon"
                data-icon="fa-solid fa-file-invoice-dollar">
                <option value="">Select</option>
                <option value="Percentage of the Total Purchase Price">Percentage of the Total Purchase Price</option>
                <option value="Percentage of the Total Purchase Price + Flat Fee">Percentage of the Total Purchase
                    Price + Flat
                    Fee</option>
                <option value="Flat Fee">Flat Fee</option>
                <option value="Other">Other</option>
            </select>
        </div>

        <div class="mt-3">
            @if ($interested_in_selling_type === 'Percentage of the Total Purchase Price')
                <div class="input-group">
                    <input type="number" wire:model.lazy="landlord_broker_purchase_price" class="form-control"
                        placeholder="Enter percentage of total purchase price (e.g., 6)">
                    <span class="input-group-text">%</span>
                </div>
            @elseif($interested_in_selling_type === 'Percentage of the Total Purchase Price + Flat Fee')
                <div class="row g-2">
                    <div class="col-md-6">
                        <div class="input-group">
                            <input type="number" wire:model.lazy="landlord_broker_percentage_price"
                                class="form-control" placeholder="Enter percentage of purchase price (e.g., 2)">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-md-1 text-center pt-2">+</div>

                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text"> $</span>
                            <input type="text" wire:model.lazy="landlord_broker_dollar_price" class="form-control"
                                placeholder="Enter flat fee amount (e.g., 2000)"
                                data-error-id="landlord_broker_dollar_price_error" oninput="validateInput(this)"
                                onblur="reformatNumber(this)" onpaste="handlePaste(event)">
                        </div>
                        <span class="error mt-2" id="landlord_broker_dollar_price_error"></span>
                    </div>
                </div>
            @elseif($interested_in_selling_type === 'Flat Fee')
                <div class="input-group">

                    <span class="input-group-text">$</span>
                    <input type="text" wire:model.lazy="landlord_broker_flate_fee" class="form-control"
                        placeholder="Enter flat fee amount (e.g., 5000)"
                        data-error-id="landlord_broker_flate_fee_error" oninput="validateInput(this)"
                        onblur="reformatNumber(this)" onpaste="handlePaste(event)">

                    <!--
                    <input type="text" wire:model.lazy="landlord_broker_flate_fee" class="form-control"
                        placeholder="Enter flat fee amount (e.g., 5000)"
                         data-error-id="landlord_broker_flate_fee_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)"> --}}




                <select wire:model.lazy="lease_fee_flat_type" class="form-select" style="max-width: 100px;">
                    <option value="$">$</option>
                    <option value="%">%</option>
                </select>

                <input type="text" step="any" wire:model.lazy="landlord_broker_flate_fee" class="form-control"
                    placeholder="{{ $lease_fee_flat_type === '%'
                        ? 'Enter percentage of the total flat fee (e.g., 6)'
                        : 'Enter flat fee amount (e.g., 5000)' }}"
                         data-error-id="purchase_value_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">

                <span class="input-group-text">
                    {{ $lease_fee_flat_type === '%' ? '%' : '$' }}
                </span> -->
                    <span class="error mt-2" id="landlord_broker_flate_fee_error"></span>

                </div>
            @elseif($interested_in_selling_type === 'Other')
                <div class="input-group">
                    <input type="text" wire:model.lazy="landlord_broker_other" class="form-control"
                        placeholder="Enter purchase fee structure (e.g., Tiered: 5% on the first $500,000, 3% on any amount above $500,000)">
                </div>
            @endif
        </div>

        @error('lease_option_fee_*')
            <span class="text-danger small">{{ $message }}</span>
        @enderror
    </div>

@endif