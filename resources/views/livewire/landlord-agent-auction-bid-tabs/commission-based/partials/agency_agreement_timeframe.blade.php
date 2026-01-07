<!-- 10.        Landlord  Agency Agreement Timeframe -->
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Landlord Agency Agreement Timeframe:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select how long the agreement between the Landlord and the Broker will remain in effect. Choose from preset durations or select “Other” to enter a custom timeframe.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover mt-2">
        <select wire:model.lazy="agency_agreement_timeframe" class="form-control has-icon"
            data-icon="fa-solid fa-calendar-alt">
            <option value="">Select</option>
            <option value="3 Months">3 Months</option>
            <option value="6 Months">6 Months</option>
            <option value="9 Months">9 Months</option>
            <option value="12 Months">12 Months</option>
            <option value="Other">Other</option>
        </select>
    </div>

    @if ($agency_agreement_timeframe === 'Other')
        <div class="mt-3">
            <div class="input-group">
                <span class="input-group-text">#</span>
                <input type="text" wire:model.lazy="agency_agreement_custom" class="form-control"
                    placeholder="Enter Landlord agency agreement timeframe (e.g., 8 Months)">

            </div>
        </div>
    @endif
</div>