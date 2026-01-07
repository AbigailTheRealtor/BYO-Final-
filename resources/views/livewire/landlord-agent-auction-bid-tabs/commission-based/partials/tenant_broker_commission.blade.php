    <!-- Tenant's Broker Commission Structure -->
    <div class="form-group mb-4">
        <label class="fw-bold d-flex align-items-center">
            Tenant's Broker Commission Fee:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select how the Tenant’s Broker will be compensated. Options include: the Landlord’s Broker paying a portion of their commission, the Landlord paying the Tenant’s Broker directly, or offering no compensation.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

        <div class="input-cover mt-2">
            <select wire:model.lazy="tenant_broker_commission_structure" class="form-control has-icon"
                data-icon="fa-solid fa-handshake">
                <option value="">Select</option>
                <option value="he Landlord's Broker will compensate the Tenant's Broker from the
                    commission received">The Landlord's Broker will compensate the Tenant's Broker from the
                    commission received</option>
                <option value="The Landlord will pay the Tenant's Broker separately">The Landlord will pay the Tenant's Broker separately</option>
                <option value="No compensation will be offered to the Tenant's Broker">No compensation will be offered to the Tenant's Broker</option>
            </select>
        </div>

        <div class="mt-3">

            @if (
                $tenant_broker_commission_structure === "The Landlord will pay the Tenant's Broker separately" ||
                    $tenant_broker_commission_structure === "he Landlord's Broker will compensate the Tenant's Broker from the
                    commission received")
                <div class="mb-3">

                    <label class="form-label">Tenant's Broker Commission Fee Structure:

                          <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                         title="Choose how the Tenant’s Broker will be compensated if a lease is secured. Options include: a percentage of the rent due each rental period, a percentage of the gross lease value, a percentage of the first month’s rent, a flat fee, or “Other” to define a custom payment structure. Then, enter the appropriate amount based on your selection.">
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
                                placeholder="Enter percentage of the gross lease value (e.g., 5)">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                @elseif ($tenant_broker_fee_structure === 'Percentage of the First Month’s Rent')
                    <div class="mb-3">
                        <div class="input-group">
                            <input type="number" wire:model.lazy="tenant_broker_first_month_rent" class="form-control"
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
                                 data-error-id="tenant_broker_flat_fee_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
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

{{-- @if ($property_type === 'Residential Property')
    <div class="form-group mb-4">