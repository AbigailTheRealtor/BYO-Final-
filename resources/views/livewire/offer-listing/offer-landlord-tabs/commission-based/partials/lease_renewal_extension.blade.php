
<!--. Lease Renewal/Extension Fee -->
@if ($property_type === 'Residential Property')

    <div class="form-group mb-4">
        <label class="fw-bold d-flex align-items-center">
            Lease Renewal/Extension Fee:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select how the Broker will be compensated if the Tenant renews or extends the lease. Options include: a percentage of the rent due each rental period, a percentage of the gross lease value, a percentage of the first month’s rent, a flat fee, or &quot;Other&quot; to define a custom payment structure. Then, enter the appropriate amount based on your selection.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

        <div class="input-cover mt-2">
            <select wire:model.lazy="renewal_fee_type" class="form-control has-icon"
                data-icon="fa-solid fa-file-invoice-dollar">
                <option value="">Select</option>
                @if ($property_type === 'Residential Property')
                    <option value="Percentage of the Rent Due Each Rental Period">Percentage of the Rent Due Each
                        Rental Period</option>
                @endif
                <option value="Percentage of the Gross Lease Value">Percentage of the Gross Lease Value</option>
                @if ($property_type === 'Residential Property')
                    <option value="Percentage of the First Month’s Rent">Percentage of the First Month’s Rent</option>
                    <option value="Flat Fee">Flat Fee</option>
                @endif
                <option value="other">Other</option>

            </select>
        </div>

        <div class="mt-3">
            @if ($renewal_fee_type === 'Percentage of the Rent Due Each Rental Period')
                <div class="mb-3">
                    {{-- <label class="form-label">Percentage of the Rent Due Each Rental Period</label> --}}
                    <div class="input-group">
                        <input type="number" wire:model.lazy="renewal_fee_percentage" class="form-control"
                            placeholder="Enter percentage of the rent due each rental period (e.g., 10)">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
            @elseif ($renewal_fee_type === 'Percentage of the Gross Lease Value')
                <div class="mb-3">
                    {{-- <label class="form-label">Percentage of the Gross Lease Value</label> --}}
                    <div class="input-group">
                        <input type="number" wire:model.lazy="renewal_fee_lease_value" class="form-control"
                            placeholder="Enter percentage of the gross lease value (e.g., 10)">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
            @elseif ($renewal_fee_type === "Percentage of the First Month’s Rent")
                <div class="mb-3">
                    {{-- <label class="form-label">Percentage of the First Month’s Rent</label> --}}
                    <div class="input-group">
                        <input type="number" wire:model.lazy="renewal_fee_first_month" class="form-control"
                            placeholder="Enter percentage of first month’s rent (e.g., 100)">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
            @elseif ($renewal_fee_type === 'Flat Fee')
                <div class="mb-3">
                    {{-- <label class="form-label">Percentage of the First Month’s Rent</label> --}}
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="text" wire:model.lazy="renewal_fee_flat_free" class="form-control"
                            placeholder="Enter flat fee amount (e.g., 2000)"
                            data-error-id="renewal_fee_percentage_error" oninput="validateInput(this)"
                            onblur="reformatNumber(this)" onpaste="handlePaste(event)">
                    </div>
                    <span class="error mt-2" id="renewal_fee_percentage_error"></span>
                </div>
            @elseif ($renewal_fee_type === 'other')
                <div class="mb-3">
                    <div class="input-group">
                        <input type="text" wire:model.lazy="renewal_fee_custom" class="form-control"
                            placeholder="Enter commission structure (e.g., $500 flat fee plus 5% of the gross lease value)">
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif
