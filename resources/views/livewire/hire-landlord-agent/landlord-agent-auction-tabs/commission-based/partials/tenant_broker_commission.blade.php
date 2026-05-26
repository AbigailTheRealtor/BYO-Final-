@if ($property_type === 'Residential Property')

    <!-- Tenant's Broker Commission Structure -->
    <div class="form-group mb-4">
        <label class="fw-bold d-flex align-items-center">
            Tenant's Broker Commission Structure:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select how the Tenant's Broker will be compensated. Options include compensation from the Landlord's Broker commission, the Landlord paying the Tenant's Broker separately, or offering no compensation to the Tenant's Broker.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

        <div class="input-cover mt-2">
            <select wire:model.lazy="tenant_broker_commission_structure" class="form-control has-icon"
                data-icon="fa-solid fa-handshake">
                <option value="">Select</option>
                <option value="Landlord's Broker to Compensate Tenant's Broker from Landlord's Broker Commission">Landlord's Broker to Compensate Tenant's Broker from Landlord's Broker Commission</option>
                <option value="Landlord to Pay Tenant's Broker Separately">Landlord to Pay Tenant's Broker Separately</option>
                <option value="No Compensation Offered to the Tenant's Broker">No Compensation Offered to the Tenant's Broker</option>
            </select>
        </div>

        <div class="mt-3">

            @if (
                $tenant_broker_commission_structure === "Landlord to Pay Tenant's Broker Separately" ||
                    $tenant_broker_commission_structure === "Landlord's Broker to Compensate Tenant's Broker from Landlord's Broker Commission")
                <div class="mb-3">

                    <label class="form-label">Tenant's Broker Commission Fee:

                        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                            title="Enter the amount offered to the Tenant’s Broker if a lease is secured. This can be a percentage of rent, a percentage of the gross lease value, a percentage of the first month’s rent, a flat fee, or a custom amount if &quot;Other&quot; is selected. Enter the appropriate amount based on your selection.">
                            <i class="fa-solid fa-circle-info"></i>
                        </span>
                    </label>
                    <div class="input-cover mt-2">
                        <select wire:model.lazy="tenant_broker_fee_structure" class="form-control has-icon"
                            data-icon="fa-solid fa-ruler">
                            <option value="">Select</option>
                            <option value="Percentage of the Rent Due Each Rental Period">Percentage of the Rent Due
                                Each Rental Period</option>
                            <option value="Percentage of the Gross Lease Value">Percentage of the Gross Lease Value
                            </option>
                            <option value="Percentage of the First Month’s Rent">Percentage of the First Month’s Rent
                            </option>
                            <option value="Flat fee">Flat fee</option>
                            <option value="Other">Other</option>
                        </select>

                    </div>
                </div>

                @if ($tenant_broker_fee_structure === 'Percentage of the Rent Due Each Rental Period')
                    <div class="mb-3">
                        <div class="input-group">
                            <input type="number" wire:model.lazy="tenant_broker_percentage" class="form-control"
                                placeholder="Enter percentage of the rent due each rental period (e.g., 5)">
                            <span class="input-group-text">%</span>
                        </div>

                    </div>
                @elseif ($tenant_broker_fee_structure === 'Percentage of the Gross Lease Value')
                    <div class="mb-3">
                        <div class="input-group">
                            <input type="number" wire:model.lazy="tenant_broker_gross_lease" class="form-control"
                                placeholder="Enter percentage of the gross lease value (e.g., 10)">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                @elseif ($tenant_broker_fee_structure === 'Percentage of the First Month’s Rent')
                    <div class="mb-3">
                        <div class="input-group">
                            <input type="number" wire:model.lazy="tenant_broker_first_month_rent"
                                class="form-control"
                                placeholder="Enter percentage of the first month’s rent (e.g., 50)">
                            <span class="input-group-text">%</span>
                        </div>

                    </div>
                @elseif ($tenant_broker_fee_structure === 'Flat fee')
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="text" wire:model.lazy="tenant_broker_flat_fee" class="form-control"
                                placeholder="Enter flat fee amount (e.g., 1000)"
                                data-error-id="tenant_broker_flat_fee_error" oninput="validateInput(this)"
                                onblur="reformatNumber(this)" onpaste="handlePaste(event)">
                        </div>
                        <span class="error mt-2" id="tenant_broker_flat_fee_error"></span>

                    </div>
                @elseif ($tenant_broker_fee_structure === 'Other')
                    <div class="mb-3">
                        <div class="input-group">
                            <input type="text" wire:model.lazy="tenant_broker_other" class="form-control"
                                placeholder="Enter Tenant’s Broker commission arrangement (e.g., $500 bonus plus 2% of gross lease value)">
                        </div>

                    </div>
                @endif
            @endif
        </div>

    </div>

@endif