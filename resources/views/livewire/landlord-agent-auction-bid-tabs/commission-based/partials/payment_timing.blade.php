
@if ($property_type === 'Residential Property')

    <!-- Payment Timing for Broker Fees -->
    <div class="form-group mb-4">
        <label class="fw-bold d-flex align-items-center">
            Payment Timing for Broker Fees:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select when the Broker's fee will be paid. Options include: deducting from rent collected, payment after lease execution, payment after the rent due date, or &quot;Other&quot; to define a custom arrangement.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

        <div class="input-cover mt-2">
            <select wire:model.lazy="broker_fee_timing" class="form-control has-icon" data-icon="fa-solid fa-clock">
                <option value="">Select</option>
                <option value="Deducted from Rent Collected">Deducted from Rent Collected</option>
                <option value="Paid Within Calendar Days After Executed Lease">Paid Within Calendar Days After Executed
                    Lease</option>
                <option value="Paid Within Calendar Days of Tenant Rent Payment">Paid Within Calendar Days of Tenant
                    Rent Payment</option>
                <option value="other">Other</option>
            </select>
        </div>

        <div class="mt-3">
            @if ($broker_fee_timing === 'Deducted from Rent Collected')
                {{-- <label class="form-label">Calendar Days to Pay Balance After the Rent Due Date</label> --}}
                <div class="input-group">
                    <span class="input-group-text">#</span>
                    <input type="number" wire:model.lazy="broker_fee_days_from_rent" class="form-control"
                        placeholder="Enter number of calendar days (e.g., 5)">
                </div>
            @elseif ($broker_fee_timing === 'Paid Within Calendar Days After Executed Lease')
                {{-- <label class="form-label">Calendar Days to Pay After Executed Lease</label> --}}
                <div class="input-group">
                    <span class="input-group-text">#</span>

                    <input type="number" wire:model.lazy="broker_fee_days_after_lease" class="form-control"
                        placeholder="Enter number of calendar days (e.g., 5)">
                </div>
            @elseif ($broker_fee_timing === 'Paid Within Calendar Days of Tenant Rent Payment')
                {{-- <label class="form-label">Calendar Days to Pay After Tenant Rent Payment</label> --}}
                <div class="input-group">
                    <span class="input-group-text">#</span>

                    <input type="number" wire:model.lazy="broker_fee_days_after_rent" class="form-control"
                        placeholder="Enter number of calendar days (e.g., 5)">
                </div>
            @elseif ($broker_fee_timing === 'other')
                <div class="input-group">
                    <input type="text" wire:model.lazy="broker_fee_timing_other" class="form-control"
                        placeholder="Describe payment arrangement (e.g., Broker to be paid 50% of commission upon lease execution and 50% upon tenant move-in)">
                </div>
            @endif
        </div>

    </div>

@endif

@if ($property_type === 'Commercial Property')
    <!-- Payment Timing for Broker Fees (Commercial) -->
    <div class="form-group mb-4">
        <label class="fw-bold d-flex align-items-center">
            Payment Timing for Broker Fees:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select when the Broker's fee will be paid. Options include: full payment upon execution of the lease, sales contract, or other transfer agreement; 50% upon execution with the remaining 50% due at commencement of the agreement; 50% upon execution with the remaining 50% due upon occupancy of the premises; or &quot;Other&quot; to define a custom arrangement.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

        <div class="input-cover mt-2">
            <select wire:model.lazy="broker_fee_timing" class="form-control has-icon" data-icon="fa-solid fa-clock">
                <option value="">Select</option>
                <option value="full_execution">Full amount upon execution of lease, sales contract, or other transfer
                    agreement</option>
                <option value="50% due upon execution, 50% due upon commencement of agreement"> 50% due upon execution,
                    50% due upon commencement of agreement</option>
                <option value="50% due upon execution, 50% due upon occupancy of premises"> 50% due upon execution, 50%
                    due upon occupancy of premises</option>
                <option value="Other">Other</option>
            </select>
        </div>

        <div class="mt-3">
            @if ($broker_fee_timing === 'Other')
                <div class="input-group">
                    <input type="text" wire:model.lazy="broker_fee_timing_other" class="form-control"
                        placeholder="Describe payment arrangement (e.g., Broker to be paid 25% upon lease execution, 25% upon tenant move-in, and 50% upon first month's rent payment)">
                </div>
            @endif
        </div>

    </div>
@endif
