@php
$safeKey = function(...$parts) {
    return implode('-', array_map(function($p) {
        if (!is_scalar($p) || $p === '' || $p === null) return 'none';
        return preg_replace('/[^a-z0-9\-]/', '', strtolower((string)$p));
    }, $parts));
};
@endphp
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
                            placeholder="Enter flat fee amount (e.g., 5000)" data-error-id="purchase_fee_flat_error"
                            oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">

                        <!--
                       <select wire:model.lazy="purchase_fee_flat_type" wire:change="setType('purchase_fee_flat', $event.target.value)" class="form-select" style="max-width: 100px;">
                    <option value="$">$</option>
                    <option value="%">%</option>
                </select>

                <input type="text" step="any" wire:model.lazy="purchase_fee_flat" class="form-control"
                    placeholder="{{ $purchase_fee_flat_type === '%'
                        ? 'Enter percentage of the total flat fee (e.g., 6)'
                        : 'Enter flat fee amount (e.g., 5000)' }}"
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
                            placeholder="Enter percentage of the net aggregate rent (e.g., 5)">
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
                            placeholder="Enter flat fee amount (e.g., 3000)"
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
                                placeholder="Enter percentage of the gross lease value (e.g., 5)">
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
    <div id="tab1" class="tab-content mt-3" wire:key="{{ $safeKey('lease-option-section', $interested_lease_option_agreement) }}">
        <div class="form-group">
            <label class="fw-bold">
                Compensation for Creating the Lease-Option Agreement:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Specify how the Broker will be compensated at the time the lease-option agreement is created. This may include a flat fee or a percentage of the option consideration paid by the party granting the option. This compensation is typically paid upfront and is separate from any commission that may be owed if the purchase option is later exercised.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>

            <div class="input-group mt-2">
                <select wire:model="lease_type" wire:change="setType('lease', $event.target.value)"
                    class="form-select" style="max-width: 100px;">
                    <option value="percent">%</option>
                    <option value="flat">$</option>
                </select>

                @if ($lease_type === 'flat')
                    <span class="input-group-text">$</span>
                @endif

                <input type="text" step="any" wire:model.lazy="lease_value" class="form-control"
                    wire:key="lease-value-input-{{ $lease_type }}"
                    placeholder="{{ $lease_type === 'percent'
                        ? 'Enter percentage of option consideration (e.g., 5)'
                        : 'Enter flat fee amount (e.g., 1,500)' }}"
                    data-error-id="lease_value_error"
                    oninput="{{ $lease_type === 'flat' ? 'formatWithCommas(this)' : 'validateInput(this)' }}"
                    onblur="{{ $lease_type === 'flat' ? 'formatWithCommas(this)' : 'reformatNumber(this)' }}"
                    onpaste="handlePaste(event)">

                @if ($lease_type === 'percent')
                    <span class="input-group-text">%</span>
                @endif
            </div>
            <span class="error mt-2" id="lease_value_error"></span>
        </div>
    </div>

    <div id="tab2" class="tab-content mt-3">
        <div class="form-group">
            <label class="fw-bold">
                Compensation if Purchase Option is Exercised:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="If the purchase option is exercised, the Broker may be entitled to additional compensation. Enter how the Broker will be compensated at that time, such as a flat fee or a percentage of the total purchase price. Any compensation already received under the lease-option agreement may be credited toward the final amount due, depending on the terms of the agreement.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>

            <div class="input-group mt-2">
                <select wire:model="purchase_type" wire:change="setType('purchase', $event.target.value)"
                    class="form-select" style="max-width: 100px;">
                    <option value="percent">%</option>
                    <option value="flat">$</option>
                </select>

                @if ($purchase_type === 'flat')
                    <span class="input-group-text">$</span>
                @endif

                <input type="text" step="any" wire:model.lazy="purchase_value" class="form-control"
                    wire:key="purchase-value-input-{{ $purchase_type }}"
                    placeholder="{{ $purchase_type === 'percent'
                        ? 'Enter percentage of the total purchase price (e.g., 6)'
                        : 'Enter flat fee amount (e.g., 5,000)' }}"
                    data-error-id="purchase_value_error"
                    oninput="{{ $purchase_type === 'flat' ? 'formatWithCommas(this)' : 'validateInput(this)' }}"
                    onblur="{{ $purchase_type === 'flat' ? 'formatWithCommas(this)' : 'reformatNumber(this)' }}"
                    onpaste="handlePaste(event)">

                @if ($purchase_type === 'percent')
                    <span class="input-group-text">%</span>
                @endif
            </div>
            <span class="error mt-2" id="purchase_value_error"></span>
        </div>
    </div>
@endif
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
                                class="form-control" placeholder="Enter percentage of purchase price  (e.g., 2)">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-md-1 text-center pt-2">+</div>

                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text"> $</span>
                            <input type="text" wire:model.lazy="landlord_broker_dollar_price" class="form-control"
                                placeholder="Enter flat fee amount (e.g., 2000) "
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
                        placeholder="Enter flat fee amount (e.g., 5000)"
                        data-error-id="landlord_broker_flate_fee_error" oninput="validateInput(this)"
                        onblur="reformatNumber(this)" onpaste="handlePaste(event)">

                    <!--
                    <input type="text" wire:model.lazy="landlord_broker_flate_fee" class="form-control"
                        placeholder="Enter flat fee amount (e.g., 5000)"
                         data-error-id="landlord_broker_flate_fee_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)"> --}}




                <select wire:model.lazy="lease_fee_flat_type" class="form-select" style="max-width: 100px;">
                    <option value="$">$</option>
                    <option value="%">%</option>
                </select>

                <input type="text" step="any" wire:model.lazy="landlord_broker_flate_fee" class="form-control"
                    placeholder="{{ $lease_fee_flat_type === '%'
                        ? 'Enter percentage of the total flat fee (e.g., 6)'
                        : 'Enter flat fee amount (e.g., 5000)' }}"
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

    </div>

@endif
