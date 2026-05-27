
{{-- @if ($property_type === 'Residential Property')
    <div class="form-group mb-4">
        <label class="fw-bold d-flex align-items-center">
            Expansion Commission for Lease Amendment:

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Specify the commission the Tenant’s Broker will receive if involved in the lease. Options include a percentage of the gross lease value, a percentage of the first month’s rent, a flat fee, or another custom arrangement. Note: The Tenant’s Broker may be a separate broker or the Landlord’s Broker providing services to both parties, depending on the brokerage relationship allowed by state law.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

        <div class="input-cover mt-2">
            <select wire:model.lazy="expansion_commission_type" class="form-control has-icon"
                data-icon="fa-solid fa-percent">
                <option value="">Select</option>
                <option value="percentage_gross_lease">Percentage of Gross Lease Value</option>
                <option value="percentage_first_month">Percentage of First Month’s Rent</option>
                <option value="flat_fee">Flat Fee</option>
                <option value="other">Other</option>
            </select>
        </div>

        <div class="mt-3">
            @if ($expansion_commission_type === 'percentage_gross_lease')
                <div class="mb-3">
                    <label class="form-label">Percentage of Gross Lease Value</label>
                    <div class="input-group">
                        <span class="input-group-text">%</span>
                        <input type="number" wire:model.lazy="expansion_gross_percentage" class="form-control"
                            placeholder="Enter percentage (e.g., 5)">
                    </div>
                </div>
            @elseif ($expansion_commission_type === 'percentage_first_month')
                <div class="mb-3">
                    <label class="form-label">Percentage of First Month’s Rent</label>
                    <div class="input-group">
                        <span class="input-group-text">%</span>
                        <input type="number" wire:model.lazy="expansion_first_month_percentage" class="form-control"
                            placeholder="Enter percentage (e.g., 50)">
                    </div>
                </div>
            @elseif ($expansion_commission_type === 'flat_fee')
                <div class="mb-3">
                    <label class="form-label">Flat Fee Amount</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" wire:model.lazy="expansion_flat_fee" class="form-control"
                            placeholder="Enter flat fee amount (e.g., 1000)">
                    </div>
                </div>
            @elseif ($expansion_commission_type === 'other')
                <div class="mb-3">
                    <label class="form-label">Custom Commission Arrangement</label>
                    <div class="input-group">
                        <span class="input-group-text">%</span>
                        <input type="text" wire:model.lazy="expansion_custom_commission" class="form-control"
                            placeholder="Enter other Tenant’s Broker commission arrangement (e.g., $500 bonus plus 2% of gross lease value)">

                    </div>
                </div>
            @endif
        </div>
    </div>
@endif --}}