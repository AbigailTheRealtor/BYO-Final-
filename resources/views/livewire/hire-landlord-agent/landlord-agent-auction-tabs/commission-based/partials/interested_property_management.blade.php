<!-- Early Termination Fee -->
<div class="form-group">
    <label class="fw-bold d-flex align-items-center">
        Interested in Property Management:

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select “Yes” if you would like the Agent/Broker to provide ongoing property management services in addition to leasing. Property management typically includes tasks such as rent collection, maintenance coordination, Tenant communications, lease enforcement, and renewals.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover mt-2">
        <select wire:model.lazy="interested_in_property_management" class="form-control has-icon"
            data-icon="fa-solid fa-ruler">
            <option value="">Select</option>
            <option value="yes">Yes</option>
            <option value="no">No</option>
        </select>
    </div>

    @if ($interested_in_property_management === 'yes')

        <div class="mt-3">

            <label class="form-label">Property Management Fee:

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Choose how the Broker will be compensated for ongoing property management services. Options include: a percentage of the gross lease value, a percentage of the rent due each rental period, a flat fee, or “Other” to define a custom management fee structure. Then, enter the appropriate amount or terms based on your selection.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>

            </label>
            <div class="input-cover mt-2">
                <select wire:model.lazy="interested_in_property_management_fee" class="form-control has-icon"
                    data-icon="fa-solid fa-file-invoice-dollar">
                    <option value="">Select</option>
                    <option value="Percentage of the Gross Lease Value">Percentage of the Gross Lease Value</option>
                    <option value="Percentage of the Rent Due Each Rental Period">Percentage of the Rent Due Each
                        Rental Period</option>
                    <option value="Flat Fee">Flat Fee</option>

                    <option value="Other">Other</option>
                </select>

            </div>
        </div>

        @if ($interested_in_property_management_fee === 'Percentage of the Gross Lease Value')
            <div class="mt-3">
                <div class="input-group">
                    <input type="number" wire:model.lazy="interested_in_property_management_fee_gross_lease"
                        class="form-control" placeholder="Enter percentage of the gross lease value (e.g., 10)">
                    <span class="input-group-text">%</span>
                </div>

            </div>
        @elseif ($interested_in_property_management_fee === 'Percentage of the Rent Due Each Rental Period')
            <div class="mt-3">
                <div class="input-group">
                    <input type="number" wire:model.lazy="interested_in_property_management_fee_rental_periord"
                        class="form-control"
                        placeholder="Enter percentage of the rent due each rental period (e.g., 10)">
                    <span class="input-group-text">%</span>
                </div>

            </div>
        @elseif ($interested_in_property_management_fee === 'Flat Fee')
            <div class="mt-3">
                <div class="input-group">
                    <span class="input-group-text">$</span>

                    <input type="text" wire:model.lazy="interested_in_property_management_fee_flate_free"
                        class="form-control" placeholder="Enter flat fee amount (e.g., 1000)"
                        data-error-id="interested_in_property_management_fee_flate_free_error"
                        oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
                </div>
                <span class="error mt-2" id="interested_in_property_management_fee_flate_free_error"></span>

            </div>
        @elseif ($interested_in_property_management_fee === 'Other')
            <div class="mt-3">
                <div class="input-group">
                    <input type="text" wire:model.lazy="interested_in_property_management_fee_other"
                        class="form-control"
                        placeholder="Enter property management fee (e.g., 4% of the gross lease value + $500)">
                </div>

            </div>
        @endif

    @endif

    <div class="alert alert-warning mt-3 p-2 small">
        <strong>⚖️ Note:</strong> Property management requires a separate property management agreement and is billed
        separately from leasing services. Fees are usually charged as a monthly flat fee or percentage of rent.
        Availability and terms may vary by Agent/Broker and are subject to brokerage policies and state law.
    </div>
</div>
