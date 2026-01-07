
<!-- Early Termination Fee -->
<div class="form-group">
    <label class="fw-bold d-flex align-items-center">
<!-- Additional Terms -->
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Additional Terms:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Include any additional or custom compensation terms, conditions, or agreements not covered above.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <textarea wire:model.lazy="additional_details_broker" class="form-control mt-2" rows="3"
        placeholder="Enter any additional terms"></textarea>
</div>

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