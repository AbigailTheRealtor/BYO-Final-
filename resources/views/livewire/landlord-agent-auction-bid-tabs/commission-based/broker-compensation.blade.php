<h3 class="mb-4">Broker Compensation & Agency Agreement Terms </h3>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>📝 Complete the compensation terms that apply. All fields are optional. If left blank, Agents may
                propose terms as part of their bid. Commission is typically paid upon lease execution or Tenant move-in.
            </strong>
        </div>
    </div>
</div>
<!-- Info Alert -->
@if (!isset($isCounterMode) || !$isCounterMode || !empty($purchase_fee_type) || !empty($purchase_fee_flat) || !empty($purchase_fee_rental_period) || !empty($purchase_fee_percentage_combo) || !empty($purchase_fee_flat_combo) || !empty($purchase_fee_other) || !empty($purchase_fee_net_aggregate) || !empty($purchase_fee_gross_rent) || !empty($sales_tax_option_gross) || !empty($purchase_fee_monthly_percentage) || !empty($purchase_fee_months) || !empty($sales_tax_option_monthly) || !empty($purchase_fee_flat_commercial) || !empty($sales_tax_option_flat) || !empty($purchase_fee_other_commercial))
@if ($property_type === 'Residential Property')

    <div class="form-group mb-4">
        <label class="fw-bold d-flex align-items-center">
            Landlord’s Broker Lease Fee:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Choose how the Landlord’s Broker will be compensated if the property is leased. Options include: a percentage of the rent due each rental period, a percentage of the gross lease value, a percentage of the first month’s rent, a flat fee, or “Other” to define a custom payment structure. Then, enter the appropriate amount based on your selection.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover mt-2">
            <select wire:model.lazy="purchase_fee_type" class="form-control has-icon"
                data-icon="fa-solid fa-file-invoice-dollar">
                <option value="">Select</option>
                <option value="Percentage of the Rent Due Each Rental Period">Percentage of the Rent Due Each Rental
                    Period</option>
                <option value="Percentage of the Gross Lease Value">Percentage of the Gross Lease Value</option>
                <option value="Percentage of the First Month’s Rent">Percentage of the First Month’s Rent</option>
                <option value="Flat Fee">Flat Fee</option>
                <option value="other">Other</option>
            </select>
        </div>

        <div class="mt-3">
            @if ($purchase_fee_type === 'Flat Fee')
                <div class="form-group">
                    {{-- <label class="fw-bold"> Flat Fee :</label> --}}

                    <div class="input-group">

                        <span class="input-group-text">$</span>
                        <input type="text" wire:model.lazy="purchase_fee_flat" class="form-control"
                            placeholder="Enter flat fee amount (e.g., 5,000)" data-error-id="purchase_fee_flat_error"
                            oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">

                        <!--
                       <select wire:model.lazy="purchase_fee_flat_type" wire:change="setType('purchase_fee_flat', $event.target.value)" class="form-select" style="max-width: 100px;">
                    <option value="$">$</option>
                    <option value="%">%</option>
                </select>

                <input type="text" step="any" wire:model.lazy="purchase_fee_flat" class="form-control"
                    placeholder="{{ $purchase_fee_flat_type === '%'
                        ? 'Enter percentage of the total flat fee (e.g., 6)'
                        : 'Enter flat fee amount (e.g., 5,000)' }}"
                         data-error-id="purchase_value_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">

                <span class="input-group-text">
                    {{ $purchase_fee_flat_type === '%' ? '%' : '$' }}
                </span> -->

                    </div>
                    <span class="error mt-2" id="purchase_fee_flat_error"></span>

                </div>
            @elseif($purchase_fee_type === 'Percentage of the Rent Due Each Rental Period')
                <div class="form-group">
                    {{-- <label class="fw-bold"> Percentage of the Rent Due Each Rental Period:</label> --}}

                    <div class="input-group">

                        <input type="number" wire:model.lazy="purchase_fee_rental_period" class="form-control"
                            placeholder="Enter percentage of the rent due each rental period (e.g., 10)">
                        <span class="input-group-text">%</span>
                    </div>

                </div>
            @elseif($purchase_fee_type === 'Percentage of the Gross Lease Value')
                <div class="form-group">
                    {{-- <label class="fw-bold"> Percentage of the Gross Lease Value:</label> --}}

                    <div class="input-group">

                        <input type="number" wire:model.lazy="purchase_fee_percentage_combo" class="form-control"
                            placeholder="Enter percentage of the gross lease value (e.g., 10)">
                        <span class="input-group-text">%</span>
                    </div>

                </div>
            @elseif($purchase_fee_type === 'Percentage of the First Month’s Rent')
                <div class="form-group">
                    {{-- <label class="fw-bold">Percentage of the First Month’s Rent:</label> --}}

                    <div class="input-group">

                        <input type="number" wire:model.lazy="purchase_fee_flat_combo" class="form-control"
                            placeholder="Enter percentage of the first month’s rent (e.g., 100)">
                        <span class="input-group-text">%</span>
                    </div>

                </div>
            @elseif($purchase_fee_type === 'other')
                <div class="input-group">

                    <input type="text" wire:model.lazy="purchase_fee_other" class="form-control"
                        placeholder="Enter lease fee structure (e.g., 100% of First Month's Rent, or a Tiered Schedule for Multi-Year Leases)">
                </div>
            @endif
        </div>
    </div>
@endif

@if ($property_type === 'Commercial Property')
    <div class="form-group mb-4">
        <label class="fw-bold d-flex align-items-center">
            Landlord’s Broker Lease Fee:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Choose how the Landlord’s Broker will be compensated if the property is leased. Options include: a percentage of the net aggregate rent, a percentage of the gross rent, a percentage of the month’s rent, a flat fee, or select “Other” to define a custom payment structure. Then, enter the appropriate amount based on your selection.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

        <div class="input-cover mt-2">
            <select wire:model.lazy="purchase_fee_type" class="form-control has-icon"
                data-icon="fa-solid fa-file-invoice-dollar">
                <option value="">Select</option>
                <option value="Percentage of the Net Aggregate Rent">Percentage of the Net Aggregate Rent</option>
                <option value="Percentage of the Gross Rent">Percentage of the Gross Rent</option>
                <option value="Percentage of Month’s Rent">Percentage of Month’s Rent</option>
                <option value="Flat Fee">Flat Fee</option>
                {{-- <option value="purchase_price">Percentage of Total Purchase Price</option> --}}
                <option value="other">Other</option>
            </select>
        </div>

        <div class="mt-3">
            @if ($purchase_fee_type === 'Percentage of the Net Aggregate Rent')
                <div class="form-group">
                    {{-- <label class="fw-bold">Percentage of Net Aggregate Rent</label> --}}
                    <div class="input-group">
                        <input type="number" wire:model.lazy="purchase_fee_net_aggregate" class="form-control"
                            placeholder="Enter percentage of the net aggregate rent (e.g., 6)">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
            @elseif ($purchase_fee_type === 'Percentage of the Gross Rent')
                <div class="form-group">
                    {{-- <label class="fw-bold">Percentage of Gross Rent</label> --}}
                    <div class="input-group">
                        <input type="number" wire:model.lazy="purchase_fee_gross_rent" class="form-control"
                            placeholder="Enter percentage of the gross rent (e.g., 5)">
                        <span class="input-group-text">%</span>
                    </div>
                    <label class="fw-bold mt-2">Sales Tax:</label>
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true" data-bs-trigger="hover focus"
                        title="Select whether commission amounts include sales tax or exclude sales tax.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>

                    <div class="input-cover mt-2">

                        <select wire:model.lazy="sales_tax_option_gross" class="form-control has-icon"
                            data-icon="fa-solid fa-ruler">
                            <option value="">Select</option>
                            <option value="including">Including Sales Tax</option>
                            <option value="excluding">Excluding Sales Tax</option>
                        </select>
                    </div>
                </div>
            @elseif ($purchase_fee_type === 'Percentage of Month’s Rent')
                <div class="form-group mb-4">
                    {{-- <label class="fw-bold">Percentage of Month’s Rent</label> --}}
                    <div class="input-group">
                        <input type="number" wire:model.lazy="purchase_fee_monthly_percentage" class="form-control"
                            placeholder="Enter percentage of month’s rent (e.g., 100)">
                        <span class="input-group-text">%</span>
                    </div>

                    <label class="fw-bold mt-3">Number of Months:</label>
                    <div class="input-group mt-1">
                        <span class="input-group-text">#</span>
                        <input type="number" wire:model.lazy="purchase_fee_months" class="form-control"
                            placeholder="Enter number of months (e.g., 1)">
                    </div>
                    <label class="fw-bold mt-3">Sales Tax:</label>
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true" data-bs-trigger="hover focus"
                        title="Select whether commission amounts include sales tax or exclude sales tax.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                    <div class="input-cover mt-2">

                        <select wire:model.lazy="sales_tax_option_monthly" class="form-control has-icon"
                            data-icon="fa-solid fa-ruler">
                            <option value="">Select</option>
                            <option value="including">Including Sales Tax</option>
                            <option value="excluding">Excluding Sales Tax</option>
                        </select>
                    </div>
                </div>
            @elseif ($purchase_fee_type === 'Flat Fee')
                <div class="form-group">
                    {{-- <label class="fw-bold">Flat Fee Amount</label> --}}
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="text" wire:model.lazy="purchase_fee_flat_commercial" class="form-control"
                            placeholder="Enter flat fee amount (e.g., 3,000)"
                            data-error-id="purchase_fee_flat_commercial_error" oninput="validateInput(this)"
                            onblur="reformatNumber(this)" onpaste="handlePaste(event)">
                    </div>
                    <span class="error mt-2" id="purchase_fee_flat_commercial_error"></span>

                    <label class="fw-bold mt-3">Sales Tax:</label>
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true" data-bs-trigger="hover focus"
                        title="Select whether commission amounts include sales tax or exclude sales tax.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                    <div class="input-cover mt-2">

                        <select wire:model.lazy="sales_tax_option_flat" class="form-control has-icon"
                            data-icon="fa-solid fa-ruler">
                            <option value="">Select</option>
                            <option value="including">Including Sales Tax</option>
                            <option value="excluding">Excluding Sales Tax</option>
                        </select>
                    </div>
                </div>
            @elseif ($purchase_fee_type === 'purchase_price')
                <div class="form-group">
                    {{-- <label class="fw-bold">Percentage of Total Purchase Price</label> --}}
                    <div class="input-group">
                        <input type="number" wire:model.lazy="purchase_fee_purchase_price" class="form-control"
                            placeholder="Enter percentage of total purchase price (e.g., 2)">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
            @elseif ($purchase_fee_type === 'other')
                <div class="input-group">
                    <input type="text" wire:model.lazy="purchase_fee_other_commercial" class="form-control"
                        placeholder="Enter lease fee structure (e.g., 100% of First Month's Rent, or a Tiered Schedule for Multi-Year Leases)">
                    {{-- <span class="input-group-text">%</span> --}}
                </div>
            @endif
        </div>
    </div>
@endif
@endif

@if (!isset($isCounterMode) || !$isCounterMode || !empty($tenant_broker_commission_structure) || !empty($tenant_broker_fee_structure) || !empty($tenant_broker_percentage) || !empty($tenant_broker_gross_lease) || !empty($tenant_broker_first_month_rent) || !empty($tenant_broker_flat_fee) || !empty($tenant_broker_other))
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
                                placeholder="Enter flat fee amount (e.g., 1,000)"
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
@endif

@if (!isset($isCounterMode) || !$isCounterMode || !empty($broker_fee_timing) || !empty($broker_fee_days_from_rent) || !empty($broker_fee_days_after_lease) || !empty($broker_fee_days_after_rent) || !empty($broker_fee_timing_other) || !empty($split_payment_due) || !empty($split_payment_due_other) || !empty($broker_fee_days_after_due_event))
@if ($property_type === 'Residential Property')

    <!-- Payment Timing for Broker Fees -->
    <div class="form-group mb-4">
        <label class="fw-bold d-flex align-items-center">
            Payment Timing for Broker Fees:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select when the Broker’s fee will be paid. Options include: deducting from rent collected, payment after lease execution, payment after the rent due date, or “Other” to define a custom arrangement.">
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
            {{-- [2026-04 audit] 'split_payment dumy' dead @elseif removed: that value was never persisted to the database --}}
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
                title="Select when the Broker’s fee will be paid. Options include: full payment upon execution of the lease, sales contract, or other transfer agreement; 50% upon execution with the remaining 50% due at commencement of the agreement; 50% upon execution with the remaining 50% due upon occupancy of the premises; or “Other” to define a custom arrangement.">
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
            {{-- [2026-04 audit] 'split_payment dumy' dead @elseif removed: that value was never persisted to the database --}}
            @endif
        </div>

    </div>
@endif
@endif

@if (!isset($isCounterMode) || !$isCounterMode || !empty($renewal_fee_type) || !empty($renewal_fee_percentage) || !empty($renewal_fee_lease_value) || !empty($renewal_fee_first_month) || !empty($renewal_fee_flat_fee) || !empty($renewal_fee_custom) || !empty($renewal_fee_sales_tax_lease_value) || !empty($renewal_fee_no_of_months) || !empty($renewal_fee_sales_tax_first_month) || !empty($renewal_fee_sales_tax_flat_fee))
<!--. Lease Renewal/Extension Fee -->
@if ($property_type === 'Residential Property')

    <div class="form-group mb-4">
        <label class="fw-bold d-flex align-items-center">
            Lease Renewal/Extension Fee:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select how the Broker will be compensated if the Tenant renews or extends the lease. Options include: a percentage of the rent due each rental period, a percentage of the gross lease value, a percentage of the first month’s rent, a flat fee, or “Other” to define a custom payment structure. Then, enter the appropriate amount based on your selection.">
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
                    <option value="Percentage of the First Month's Rent">Percentage of the First Month's Rent</option>
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
            @elseif ($renewal_fee_type === "Percentage of the First Month's Rent")
                <div class="mb-3">
                    {{-- <label class="form-label">Percentage of the First Month's Rent</label> --}}
                    <div class="input-group">
                        <input type="number" wire:model.lazy="renewal_fee_first_month" class="form-control"
                            placeholder="Enter percentage of first month's rent (e.g., 100)">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
            @elseif ($renewal_fee_type === 'Flat Fee')
                <div class="mb-3">
                    {{-- <label class="form-label">Percentage of the First Month's Rent</label> --}}
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="text" wire:model.lazy="renewal_fee_flat_fee" class="form-control"
                            placeholder="Enter flat fee amount (e.g., 2,000)"
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

<!--Commercial  Lease Renewal/Extension Fee  -->

@if ($property_type === 'Commercial Property')
    <div class="form-group mb-4">
        <label class="fw-bold d-flex align-items-center">
            Lease Renewal/Extension Fee:

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select how the Broker will be compensated if the Tenant renews or extends the lease. Options include: a percentage of the net aggregate rent, a percentage of the gross rent, a percentage of the month’s rent, a flat fee, or select “Other” to define a custom payment structure. Then, enter the appropriate amount based on your selection.">
                <i class="fa-solid fa-circle-info"></i>
            </span>

        </label>
        <div class="input-cover mt-2">
            <select wire:model.lazy="renewal_fee_type" class="form-control has-icon"
                data-icon="fa-solid fa-file-invoice-dollar">
                <option value="">Select </option>
                <option value="Percentage of the Net Aggregate Rent">Percentage of the Net Aggregate Rent</option>
                <option value="Percentage of the Gross Rent">Percentage of the Gross Rent</option>
                <option value="Percentage of Month’s Rent"> Percentage of Month’s Rent</option>
                <option value="Flat Fee">Flat Fee</option>

                <option value="other">Other</option>
            </select>
        </div>

        <div class="mt-3">
            @if ($renewal_fee_type === 'Percentage of the Net Aggregate Rent')
                <div class="mb-3">
                    {{-- <label class="form-label">Percentage of the Net Aggregate Rent</label> --}}
                    <div class="input-group">
                        <input type="number" wire:model.lazy="renewal_fee_percentage" class="form-control"
                            placeholder="Enter percentage of the net aggregate rent (e.g., 6)">
                        <span class="input-group-text">%</span>
                    </div>

                </div>
            @elseif ($renewal_fee_type === 'Percentage of the Gross Rent')
                <div class="mb-3">
                    {{-- <label class="form-label">Percentage of the Gross Rent</label> --}}
                    <div class="input-group">
                        <input type="number" wire:model.lazy="renewal_fee_lease_value" class="form-control"
                            placeholder="Enter percentage of the gross rent (e.g., 5)">
                        <span class="input-group-text">%</span>
                    </div>
                    <label class="fw-bold mt-3">Sales Tax:</label>
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Select whether commission amounts include sales tax or exclude sales tax.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                    {{-- <label class="fw-bold mt-2">Sales Tax Selection</label> --}}
                    <div class="input-cover mt-2">

                        <select wire:model.lazy="renewal_fee_sales_tax_lease_value" class="form-control has-icon"
                            data-icon="fa-solid fa-ruler">
                            <option value="">Select</option>
                            <option value="including">Including Sales Tax</option>
                            <option value="excluding">Excluding Sales Tax</option>
                        </select>
                    </div>
                </div>
            @elseif ($renewal_fee_type === 'Percentage of Month’s Rent')
                <div class="mb-3">
                    {{-- <label class="form-label">Percentage of Month’s Rent</label> --}}
                    <div class="input-group">
                        <input type="number" wire:model.lazy="renewal_fee_first_month" class="form-control"
                            placeholder="Enter percentage of month’s rent (e.g., 100)">
                        <span class="input-group-text">%</span>
                    </div>
                    <label class="form-label mt-2">Number of Months:</label>
                    <div class="input-group">
                        <span class="input-group-text">#</span>

                        <input type="number" wire:model.lazy="renewal_fee_no_of_months" class="form-control"
                            placeholder="Enter number of months (e.g., 1)">
                    </div>
                    {{-- <label class="fw-bold mt-2">Sales Tax Selection</label> --}}
                    <label class="fw-bold mt-3">Sales Tax:</label>
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Select whether commission amounts include sales tax or exclude sales tax.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                    <div class="input-cover mt-2">
                        <select wire:model.lazy="renewal_fee_sales_tax_first_month" class="form-control has-icon"
                            data-icon="fa-solid fa-ruler">
                            <option value="">Select</option>
                            <option value="including">Including Sales Tax</option>
                            <option value="excluding">Excluding Sales Tax</option>
                        </select>
                    </div>
                </div>
            @elseif($renewal_fee_type === 'Flat Fee')
                <div class="mb-3">
                    {{-- <label class="form-label">Flat Fee Amount</label> --}}
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="text" wire:model.lazy="renewal_fee_flat_fee" class="form-control"
                            placeholder="Enter flat fee amount (e.g., 5,000)" data-error-id="flat_fee_error"
                            oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
                    </div>
                    <span class="error mt-2" id="flat_fee_error"></span>
                    <label class="fw-bold mt-2">Sales Tax:</label>
                    <div class="input-cover mt-2">

                        <select wire:model.lazy="renewal_fee_sales_tax_flat_fee" class="form-control has-icon"
                            data-icon="fa-solid fa-ruler">
                            <option value="">Select</option>
                            <option value="including">Including Sales Tax</option>
                            <option value="excluding">Excluding Sales Tax</option>
                        </select>
                    </div>
                </div>
            @elseif ($renewal_fee_type === 'other')
                <div class="mb-3">
                    <div class="input-group">
                        <input type="text" wire:model.lazy="renewal_fee_custom" class="form-control"
                            placeholder=" Describe commission fee (e.g., 50% of first month’s rent plus 3% of the net aggregate rent)">
                    </div>
                </div>
            @endif
        </div>

    </div>
@endif
@endif

@if (!isset($isCounterMode) || !$isCounterMode || !empty($expansion_commission_percentage))
@if ($property_type === 'Commercial Property')
    <!-- Expansion Commission for Lease Amendment (Commercial only) -->
    <div class="form-group mb-4">
        <label class="fw-bold d-flex align-items-center">
            Expansion Commission for Lease Amendment:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the percentage of the original commission to be applied if the leased space expands under a lease amendment. This is typically calculated as a portion of the initial commission structure.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

        <div class="mt-2">
            <div class="input-group">

                <input type="number" wire:model.lazy="expansion_commission_percentage" class="form-control"
                    placeholder="Enter percentage of original commission for expansion (e.g., 50)">
                <span class="input-group-text">%</span>
            </div>
            @error('expansion_commission_percentage')
                <span class="text-danger small">{{ $message }}</span>
            @enderror
        </div>
    </div>
@endif
@endif

@if (!isset($isCounterMode) || !$isCounterMode || !empty($interested_in_property_management) || !empty($interested_in_property_management_fee) || !empty($interested_in_property_management_fee_gross_lease) || !empty($interested_in_property_management_fee_rental_periord) || !empty($interested_in_property_management_fee_flate_free) || !empty($interested_in_property_management_fee_other))
<!-- Interested in Property Management -->
<div class="form-group">
    <label class="fw-bold d-flex align-items-center">
        Interested in Property Management:

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether you would like the Agent/Broker to provide ongoing property management services in addition to leasing. If &quot;Yes&quot; is selected, you will be prompted to enter compensation details.">
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
                        class="form-control" placeholder="Enter flat fee amount (e.g., 1,000)"
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
@endif

@if (!isset($isCounterMode) || !$isCounterMode || !empty($interested_lease_option_agreement) || !empty($lease_value) || !empty($purchase_value))
<div class="form-group mb-2">
    <label class="fw-bold d-flex align-items-center">
        Interested in Offering a Lease-Option Agreement:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether the Landlord is open to a lease with option to purchase. If “Yes” is selected, you'll be prompted to enter compensation details.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover mt-2">
        <select wire:model.lazy="interested_lease_option_agreement" class="form-control has-icon"
            data-icon="fa-solid fa-file-invoice-dollar">
            <option value="">Select</option>
            <option value="Yes">Yes</option>
            <option value="No">No</option>

        </select>
    </div>
</div>

@if ($interested_lease_option_agreement === 'Yes')
    <!-- TAB 1 -->
    <div id="tab1" class="tab-content">
        <h5 class="compensation_tab fw-bold mb-3" style="color: #049399;">
            Compensation for Creating the Lease-Option Agreement:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true" data-bs-trigger="hover focus"
                title="Specify how the Broker will be compensated at the time the lease-option agreement is created. This may include a flat fee or a percentage of the option consideration paid by the party granting the option. This compensation is typically paid upfront and is separate from any commission that may be owed if the purchase option is later exercised.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </h5>

        <div class="form-group mt-2">
            <label class="fw-bold d-block mb-1">Compensation Amount</label>

            <div class="input-group">
                <!-- Select for type -->
                <select wire:model.lazy="lease_type" wire:change="setType('lease', $event.target.value)"
                    class="form-select" style="max-width: 100px;">
                    <option value="percent">%</option>
                    <option value="flat">$</option>
                </select>

                <!-- Single input -->
                <input type="text" step="any" wire:model.lazy="lease_value" class="form-control"
                    placeholder="{{ $lease_type === 'percent'
                        ? 'Enter percentage of option consideration (e.g., 5)'
                        : 'Enter flat fee amount (e.g., 1,500)' }}"
                    data-error-id="lease_value_error" oninput="validateInput(this)" onblur="reformatNumber(this)"
                    onpaste="handlePaste(event)">

                <!-- Suffix -->
                <span class="input-group-text">
                    {{ $lease_type === 'percent' ? '%' : '$' }}
                </span>
            </div>
            <span class="error mt-2" id="lease_value_error"></span>

        </div>
    </div>

    <!-- TAB 2 -->
    <div id="tab2" class="tab-content">
        <h5 class="compensation_tab fw-bold mb-3" style="color: #049399;">
            Compensation if Purchase Option is Exercised:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true" data-bs-trigger="hover focus"
                title="If the purchase option is exercised, the Broker may be entitled to additional compensation. Enter how the Broker will be compensated at that time, such as a flat fee or a percentage of the total purchase price. Any compensation already received under the lease-option agreement may be credited toward the final amount due, depending on the terms of the agreement.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </h5>

        <div class="form-group mt-2">
            <label class="fw-bold d-block mb-1">Compensation Amount</label>

            <div class="input-group">
                <!-- Select for type -->
                <select wire:model.lazy="purchase_type" wire:change="setType('purchase', $event.target.value)"
                    class="form-select" style="max-width: 100px;">
                    <option value="percent">%</option>
                    <option value="flat">$</option>
                </select>

                <!-- Single input -->
                <input type="text" step="any" wire:model.lazy="purchase_value" class="form-control"
                    placeholder="{{ $purchase_type === 'percent'
                        ? 'Enter percentage of the total purchase price (e.g., 6)'
                        : 'Enter flat fee amount (e.g., 5,000)' }}"
                    data-error-id="purchase_value_error" oninput="validateInput(this)" onblur="reformatNumber(this)"
                    onpaste="handlePaste(event)">

                <!-- Suffix -->
                <span class="input-group-text">
                    {{ $purchase_type === 'percent' ? '%' : '$' }}
                </span>
            </div>
            <span class="error mt-2" id="purchase_value_error"></span>
        </div>

    </div>

    <div class="alert alert-warning mt-3 p-2 small">
        <strong>Note:</strong> Select $ or % to switch between entering a dollar amount or a percentage.
    </div>
@endif
@endif

@if (!isset($isCounterMode) || !$isCounterMode || !empty($interested_in_selling) || !empty($interested_in_selling_type) || !empty($landlord_broker_purchase_price) || !empty($landlord_broker_percentage_price) || !empty($landlord_broker_dollar_price) || !empty($landlord_broker_flate_fee) || !empty($landlord_broker_other))
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
                                placeholder="Enter flat fee amount (e.g., 2,000) "
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
                        placeholder="Enter flat fee amount (e.g., 5,000)"
                        data-error-id="landlord_broker_flate_fee_error" oninput="validateInput(this)"
                        onblur="reformatNumber(this)" onpaste="handlePaste(event)">

                    <!--
                    <input type="text" wire:model.lazy="landlord_broker_flate_fee" class="form-control"
                        placeholder="Enter flat fee amount (e.g., 5,000)"
                         data-error-id="landlord_broker_flate_fee_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)"> --}}




                <select wire:model.lazy="lease_fee_flat_type" class="form-select" style="max-width: 100px;">
                    <option value="$">$</option>
                    <option value="%">%</option>
                </select>

                <input type="text" step="any" wire:model.lazy="landlord_broker_flate_fee" class="form-control"
                    placeholder="{{ $lease_fee_flat_type === '%'
                        ? 'Enter percentage of the total flat fee (e.g., 6)'
                        : 'Enter flat fee amount (e.g., 5,000)' }}"
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
@endif

@if (!isset($isCounterMode) || !$isCounterMode || !empty($protection_period))
@if ($property_type === 'Residential Property')
    <!-- Protection Period Timeframe -->
    <div class="form-group mb-4 mt-3">
        <label class="fw-bold d-flex align-items-center">
            Protection Period Timeframe (Days):
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the number of days after the Listing Period ends during which the Landlord agrees to pay a commission if the property is leased, sold, or otherwise transferred or acquired to a prospect with whom the Broker—or any other Broker—communicated during the Listing Period. If requested, the Broker must provide a list of such prospects, and compensation is limited to the names on that list. This protection period ends if the Landlord enters into a good-faith exclusive leasing agreement with another Broker after the Listing Period.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover mt-2">
            <input type="number" wire:model.lazy="protection_period" class="form-control has-icon"
                data-icon="fa-solid fa-shield-halved" placeholder="Enter protection period in days (e.g., 90)">
        </div>
    </div>
@endif

@if ($property_type === 'Commercial Property')
    <!-- Protection Period Timeframe -->
    <div class="form-group mb-4 mt-3">
        <label class="fw-bold d-flex align-items-center">
            Protection Period Timeframe (Days):
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the number of days after the Listing Period ends during which the Landlord agrees to pay a commission if the property is leased, sold, or otherwise transferred or acquired to a prospect with whom the Broker—or any other Broker—communicated during the Listing Period. If requested, the Broker must provide a list of such prospects, and compensation is limited to the names on that list. This protection period ends if the Landlord enters into a good-faith exclusive leasing agreement with another Broker after the Listing Period.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover mt-2">
            <input type="number" wire:model.lazy="protection_period" class="form-control has-icon"
                data-icon="fa-solid fa-shield-halved" placeholder="Enter protection period in days (e.g., 90)">
        </div>
    </div>
@endif
@endif

@if (!isset($isCounterMode) || !$isCounterMode || !empty($early_termination_fee_option))
<!-- Early Termination Fee -->
<div class="form-group mb-4">
    @if ($property_type === 'Residential Property')
        <label class="fw-bold d-flex align-items-center">
            Early Termination Fee:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select whether the Landlord agrees to pay a cancellation fee if the agreement is conditionally terminated before the end of the Listing Period. If “Yes” is selected, you’ll be prompted to enter the fee amount. The fee is due at the time of withdrawal and helps offset marketing costs. If the property is leased during the remaining Listing or Protection Period, the Broker may void the early termination, and the full commission may still apply, minus the cancellation fee.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover mt-2">
            <select wire:model.lazy="early_termination_fee_option" class="form-control has-icon"
                data-icon="fa-solid fa-triangle-exclamation">
                <option value="">Select</option>
                <option value="yes">Yes</option>
                <option value="no">No</option>
            </select>
        </div>
    @endif

    {{-- @if ($property_type === 'Commercial Property')
        <label class="fw-bold d-flex align-items-center">
            Early Termination Fee:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="If the Owner cancels the agreement early, they must sign a withdrawal form and may owe a cancellation fee. If the property is leased during the remaining agreement term or within the protection period to a prospect the Broker (or another broker) communicated with during the agreement, the Broker may still be entitled to full commission. The protection period does not apply if the Owner signs a new exclusive agreement in good faith with another broker after
this agreement ends.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover mt-2">
            <select wire:model.lazy="early_termination_fee_option" class="form-control has-icon"
                data-icon="fa-solid fa-triangle-exclamation">
                <option value="">Select</option>
                <option value="yes">Yes</option>
                <option value="no">No</option>
            </select>
        </div>
    @endif --}}

    @if ($early_termination_fee_option === 'yes')
        <div class="mt-3">
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="text" wire:model.lazy="early_termination_fee_amount" class="form-control"
                    placeholder="Enter early termination fee amount (e.g., 1000)"
                    data-error-id="early_termination_fee_amount_error" oninput="validateInput(this)"
                    onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            </div>
            @error('early_termination_fee_amount')
                <span class="text-danger small">{{ $message }}</span>
            @enderror
            <span class="error mt-2" id="early_termination_fee_amount_error"></span>
        </div>
    @endif
</div>
@endif

@if (!isset($isCounterMode) || !$isCounterMode || !empty($retainer_fee_option) || !empty($retainer_fee_amount) || !empty($retainer_fee_application))
<!-- Retainer Fee -->
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Retainer Fee:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether the Landlord agrees to pay a non-refundable retainer fee to initiate Broker services. If Yes is selected, enter the amount. This fee is separate from any commission owed unless otherwise specified.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover mt-2">
        <select wire:model.lazy="retainer_fee_option" class="form-control has-icon"
            data-icon="fa-solid fa-file-invoice-dollar">
            <option value="">Select</option>
            <option value="yes">Yes</option>
            <option value="no">No</option>
        </select>
    </div>
    @if ($retainer_fee_option === 'yes' || (isset($isCounterMode) && $isCounterMode && (!empty($retainer_fee_amount) || !empty($retainer_fee_application))))
        <div class="mt-3">
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="text" wire:model.lazy="retainer_fee_amount" class="form-control"
                    placeholder="Enter retainer fee amount (e.g., 500)"
                    data-error-id="retainer_fee_amount_error"
                    oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
                <span class="error mt-2" id="retainer_fee_amount_error"></span>
            </div>
            <div class="mt-3">
                <label class="fw-bold d-flex align-items-center">
                    Retainer Fee Application:
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Select whether the retainer fee will be credited toward the final commission or charged in addition to it.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                </label>
                <div class="input-cover mt-2">
                    <select wire:model.lazy="retainer_fee_application" class="form-control has-icon"
                        data-icon="fa-solid fa-triangle-exclamation">
                        <option value="">Select</option>
                        <option value="applied">Applied toward final compensation</option>
                        <option value="additional">Charged in addition to final compensation</option>
                    </select>
                </div>
                @error('retainer_fee_application')
                    <span class="text-danger small">{{ $message }}</span>
                @enderror
            </div>
        </div>
    @endif
</div>
@endif

@if (!isset($isCounterMode) || !$isCounterMode || !empty($agency_agreement_timeframe))
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
            data-icon="fa-solid fa-calendar-days">
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
@endif

@if (!isset($isCounterMode) || !$isCounterMode || !empty($brokerage_relationship))
<!-- Acceptable Brokerage Relationship -->
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Acceptable Brokerage Relationship:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type of legal relationship the Landlord wishes to establish with the Broker. This determines the level of representation the Broker will provide. Real estate laws vary by state, and Brokers may offer different types of agency relationships. Both the Broker and Landlord must comply with all applicable local, state, and federal laws.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>

    <div class="input-cover mt-2">
        <select wire:model.lazy="brokerage_relationship" class="form-control has-icon"
            data-icon="fa-solid fa-handshake">
            <option value="">Select</option>
            <option value="Transaction Broker Representation">Transaction Broker Representation</option>
            <option value="Single Agent Representation">Single Agent Representation</option>
            <option value="Dual Agency Representation">Dual Agency Representation</option>
            <option value="No Brokerage Relationship">No Brokerage Relationship</option>
        </select>
    </div>

    @if ($brokerage_relationship)
        <div class="mt-3 p-3 bg-light rounded">

            @if ($brokerage_relationship === 'Transaction Broker Representation')
                <h6 class="fw-bold">• Transaction Broker Representation:</h6>
                <ul class="mb-2 ps-3">
                    <li>Default in Florida unless otherwise specified.</li>
                    <li>The Broker provides limited representation to both parties without full fiduciary duties.</li>
                    <li>Must act honestly, fairly, and with skill, care, and diligence.</li>
                    <li>Not permitted in Texas, Alaska, Vermont, Kansas, or Colorado.</li>
                </ul>
            @elseif ($brokerage_relationship === 'Single Agent Representation')
                <h6 class="fw-bold">• Single Agent Representation:</h6>
                <ul class="mb-2 ps-3">
                    <li>The Broker acts as a fiduciary, providing the highest level of loyalty, confidentiality,
                        obedience, and full disclosure.</li>
                    <li>The Broker must always act in the Landlord’s best interest.</li>
                    <li>Requires written consent from both the Landlord and the Tenant.</li>
                    <li>Requires a Single Agent Notice signed by the Landlord.</li>
                </ul>
            @elseif ($brokerage_relationship === 'Dual Agency Representation')
                <h6 class="fw-bold">• Dual Agency Representation:</h6>
                <ul class="mb-2 ps-3">
                    <li>The Broker represents both the Landlord and the Tenant in the same transaction.</li>
                    <li>Must remain neutral and may not disclose confidential information from either party.</li>
                    <li>Requires written consent from both the Landlord and the Tenant.</li>
                    <li>Not permitted in Alaska, Colorado, Florida, Kansas, Maryland, Oklahoma, Texas, Vermont, and
                        Wyoming.</li>
                </ul>
            @elseif ($brokerage_relationship === 'No Brokerage Relationship')
                <h6 class="fw-bold">• No Brokerage Relationship:</h6>
                <ul class="mb-2 ps-3">
                    <li>The Broker does not represent the Landlord and has no fiduciary duties.</li>
                    <li>Still required to act honestly and disclose all known facts that materially affect the
                        property’s value.</li>
                    <li>The Landlord is responsible for their own due diligence and negotiations.</li>
                </ul>
            @endif

            <div class="alert alert-warning mt-3 p-2 small">
                <strong>⚠️ Legal Notice:</strong> Certain brokerage relationships are not permitted in all states. If
                your selection is not allowed, the Broker will establish a permitted legal alternative. Real estate laws
                change frequently. Both the Broker and Landlord are responsible for complying with all current local,
                state, and federal laws.
            </div>
        </div>
    @endif
</div>
@endif

@if (!isset($isCounterMode) || !$isCounterMode || !empty($additional_details_broker))
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
@endif

