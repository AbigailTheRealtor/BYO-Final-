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