<h3>Broker Compensation & Agency Agreement Terms</h3>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>📝 Complete the compensation terms that apply. All fields are optional. If left
                blank, Agents may propose terms as part of their bid. Commission is typically paid upon successful
                property closing.
            </strong>
        </div>
    </div>
</div>
<!-- Info Alert -->

<!-- Tenant's Broker Commission Structure -->
{{-- <div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Seller's Broker Commission Structure:

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select how the Broker will be compensated: either directly by the Buyer or as part of the offer to the Seller. If the Seller declines to pay, the Buyer is responsible.">
            <i class="fa-solid fa-circle-info"></i>

        </span>
    </label>
    <div class="input-cover mt-2">
        <select wire:model="commission_structure" class="form-control has-icon"
            data-icon="fa-solid fa-file-invoice-dollar">
            <option value="">Select</option>
            <option value="Out-of-Pocket Payment">Out-of-Pocket Payment</option>
            <option value="Included in Offer">Included in Offer</option>
        </select>
    </div>
    @error('commission_structure')
        <span class="text-danger small">{{ $message }}</span>
    @enderror
</div> --}}
<!-- Tenant's Broker Purchase Fee -->
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Seller's Broker Purchase Fee:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Choose how the Seller's Broker will be compensated. Options include a percentage of the total purchase price, a flat fee, a combination of both, or select “Other” to define a custom structure. Then enter the amount based on your selection.">
            <i class="fa-solid fa-circle-info"></i>

        </span>
    </label>
    <div class="input-cover mt-2">
        <select wire:model="purchase_fee_type" class="form-control has-icon"
            data-icon="fa-solid fa-file-invoice-dollar">
            <option value="">Select</option>
            <option value="percentage">Percentage of the Total Purchase Price</option>
            <option value="flat">Flat Fee</option>
            <option value="combo">Percentage of the Total Purchase Price + Flat Fee</option>
            <option value="other">Other</option>
        </select>
    </div>

    <div class="mt-3">
        @if ($purchase_fee_type === 'flat')
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="text" wire:model="purchase_fee_flat" class="form-control"
                    placeholder="Enter flat fee amount (e.g., 5000)" data-error-id="purchase_fee_flat_error"
                    oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">

                <span class="error mt-2" id="purchase_fee_flat_error"></span>

            </div>
        @elseif($purchase_fee_type === 'percentage')
            <div class="input-group">
                <input type="number" wire:model="purchase_fee_percentage" class="form-control"
                    placeholder="Enter percentage of total purchase price (e.g., 6)">
                <span class="input-group-text">%</span>
            </div>
        @elseif($purchase_fee_type === 'combo')
            <div class="row g-2">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="number" wire:model="purchase_fee_percentage_combo" class="form-control"
                            placeholder="Enter percentage of purchase price  (e.g., 2)">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-md-1 text-center pt-2">+</div>

                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text"> $</span>
                        <input type="text" wire:model="purchase_fee_flat_combo" class="form-control"
                            placeholder="Enter flat fee amount (e.g., 2000)"
                            data-error-id="purchase_fee_flat_combo_error" oninput="validateInput(this)"
                            onblur="reformatNumber(this)" onpaste="handlePaste(event)">
                    </div>

                    <span class="error mt-2" id="purchase_fee_flat_combo_error"></span>

                </div>
            </div>
        @elseif($purchase_fee_type === 'other')
            <input type="text" wire:model="purchase_fee_other" class="form-control mt-2"
                placeholder="Enter commission structure (e.g., Tiered fee: 5% on the first $500,000, 3% on any amount above $500,000)">
        @endif
    </div>
    @error('purchase_fee_*')
        <span class="text-danger small">{{ $message }}</span>
    @enderror
</div>

@if (in_array($property_type, ['Income', 'Commercial', 'Business']))
    <div class="form-group">
        <label class="fw-bold">Nominal Consideration Fee:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="If the property is transferred for nominal value (e.g., a gift or very low sale price), enter the flat fee the Seller's Broker will be paid instead of a percentage-based commission.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <span class="input-group-text-seller"> $</span>
            <input type="number" wire:model="nominal" class="form-control has-icon"
                placeholder="Enter nominal consideration fee amount (e.g., 1000)">
        </div>
    </div>
@endif

<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Buyer's Broker Commission Structure:

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select how the Buyer's Broker will be compensated. Options include compensation from the Seller's Broker commission, the Seller paying the Buyer's Broker separately, or offering no compensation to the Buyer's Broker.">
            <i class="fa-solid fa-circle-info"></i>

        </span>
    </label>
    <div class="input-cover mt-2">
        <select wire:model="commission_structure" class="form-control has-icon"
            data-icon="fa-solid fa-file-invoice-dollar">
            <option value="">Select</option>
            <option value="Seller's Broker to Compensate Buyer's Broker from Seller's Broker Commission">Seller's Broker to
                Compensate Buyer's Broker from Seller's Broker Commission</option>
            <option value="Seller to Pay Buyer's Broker Separately">Seller to Pay Buyer's Broker Separately</option>
            <option value="No Compensation Offered to the Buyer's Broker">No Compensation Offered to the Buyer's Broker
            </option>
            {{-- <option value="Negotiable">Negotiable</option> --}}
        </select>
    </div>

</div>
@if (
    $commission_structure == 'Seller\'s Broker to Compensate Buyer\'s Broker from Seller\'s Broker Commission' ||
        $commission_structure == 'Seller to Pay Buyer\'s Broker Separately')
    <!-- Tenant's Broker Purchase Fee -->
    <div class="form-group mb-4">
        <label class="fw-bold d-flex align-items-center">
            Buyer's Broker Commission Fee:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the amount offered to the Buyer's Broker. This can be a percentage of the purchase price, a flat fee, or a custom amount if &quot;Other&quot; is selected. Enter the appropriate amount based on your selection.">
                <i class="fa-solid fa-circle-info"></i>

            </span>
        </label>
        <div class="input-cover mt-2">
            <select wire:model="commission_structure_type" class="form-control has-icon"
                data-icon="fa-solid fa-file-invoice-dollar">
                <option value="">Select</option>
                <option value="Percentage of the Total Purchase Price">Percentage of the Total Purchase Price</option>
                <option value="Flat Fee">Flat Fee</option>
                {{-- <option value="No Compensation Offered to the Buyer's Broker">No Compensation Offered to the Buyer's
                    Broker</option> --}}
                {{-- <option value="Percentage of the Total Purchase Price + Flat Fee">Percentage of the Total Purchase Price + Flat Fee</option> --}}
                <option value="other">Other</option>
            </select>
        </div>

        <div class="mt-3">
            @if ($commission_structure_type === 'Flat Fee')
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="text" wire:model="commission_structure_type_fee_flat" class="form-control"
                        placeholder="Enter flat fee amount (e.g., 4000)"
                        data-error-id="commission_structure_type_fee_flat_error" oninput="validateInput(this)"
                        onblur="reformatNumber(this)" onpaste="handlePaste(event)">

                    <span class="error mt-2" id="commission_structure_type_fee_flat_error"></span>

                </div>
            @elseif($commission_structure_type === 'Percentage of the Total Purchase Price')
                <div class="input-group">
                    <input type="number" wire:model="commission_structure_type_fee_percentage" class="form-control"
                        placeholder="Enter percentage of total purchase price (e.g., 6)">
                    <span class="input-group-text">%</span>
                </div>
            @elseif($commission_structure_type === 'Percentage of the Total Purchase Price + Flat Fee')
                <div class="row g-2">
                    <div class="col-md-6">
                        <div class="input-group">
                            <input type="number" wire:model="commission_structure_type_fee_percentage_combo"
                                class="form-control" placeholder="Enter % of total purchase price (e.g., 3)">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-md-1 text-center pt-2">+</div>

                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text"> $</span>
                            <input type="number" wire:model="commission_structure_type_fee_flat_combo"
                                class="form-control" placeholder="Enter flat fee amount (e.g., 2000)">
                        </div>
                    </div>
                </div>
            @elseif($commission_structure_type === 'other')
                <input type="text" wire:model="commission_structure_type_fee_other" class="form-control mt-2"
                    placeholder="Enter compensation for the Buyer's Broker Commission Fee (e.g., 3% if the sale price is under $500,000, 2% if over $500,000)">
            @endif
        </div>

    </div>
@endif

<!-- Tenant's Broker Purchase Fee -->
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Interested in Offering a Lease Agreement:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether the Seller is open to leasing the property. If “Yes” is selected, you'll be prompted to enter compensation details.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover mt-2">
        <select wire:model="interested_purchase_fee_type" class="form-control has-icon"
            data-icon="fa-solid fa-file-invoice-dollar">
            <option value="">Select</option>
            <option value="Yes">Yes</option>
            <option value="No">No</option>

        </select>
    </div>
</div>

@if ($interested_purchase_fee_type === 'Yes')

    <!-- Tenant's Broker Purchase Fee -->
    <div class="form-group mb-4">
        <label class="fw-bold d-flex align-items-center">
            Seller's Broker Leasing Fee:
            @if (in_array($property_type, ['Residential', 'Income']))
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Choose how the Seller's Broker will be compensated if the property is leased. Options include a percentage of the rent due each rental period, a percentage of the gross lease value, a percentage of the first month's rent, a flat fee, or “Other” to define a custom payment structure. Then enter the appropriate amount based on your selection.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            @endif
            @if (in_array($property_type, ['Commercial', 'Business']))
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Choose how the Seller's Broker will be compensated if the property is leased. Options include a percentage of the net aggregate rent, a percentage of the gross rent, a percentage of the month's rent, a flat fee, or select “Other” to define a custom payment structure. Then enter the appropriate amount based on your selection.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            @endif
        </label>

        <div class="input-cover mt-2">
            <select wire:model="seller_leasing_fee_type" class="form-control has-icon"
                data-icon="fa-solid fa-file-invoice-dollar">
                <option value="">Select</option>

                @if (in_array($property_type, ['Residential', 'Income', 'Vacant Land']))
                    <option value="Percentage of the Rent Due Each Rental Period">Percentage of the Rent Due Each
                        Rental
                        Period</option>
                    <option value="Percentage of the Gross Lease Value">Percentage of the Gross Lease Value</option>
                    <option value="Percentage of the First Month's Rent">Percentage of the First Month's Rent</option>
                    <option value="Flat Fee">Flat Fee</option>
                    <option value="other">Other</option>
                    {{-- <option value="Flat Fee + Percentage of the Gross Lease Value">Flat Fee + Percentage of the Gross Lease Value</option> --}}
                @elseif (in_array($property_type, ['Commercial', 'Business']))
                    <option value="Percentage of Net Aggregate Rent">Percentage of Net Aggregate Rent
                    <option value="Percentage of Gross Rent">Percentage of Gross Rent </option>
                    </option>
                    <option value="Percentage of Month's Rent">Percentage of Month's Rent</option>

                    <option value="Flat Fee">Flat Fee
                    </option>
                    {{-- <option value="Flat Fee + Percentage of the Net Aggregate Rent">Flat Fee + Percentage of the Net Aggregate Rent</option> --}}
                @endif
                {{-- <option value="other">Other</option> --}}
            </select>
        </div>

        @if ($seller_leasing_fee_type === 'Percentage of the Gross Lease Value')
            <div class="mt-3">
                <div class="input-group">
                    <input type="number" wire:model="seller_leasing_gross" class="form-control"
                        placeholder="Enter percentage of the gross lease value (e.g., 10)">
                    <span class="input-group-text">%</span>
                </div>

                @if (in_array($property_type, ['Commercial', 'Business']))
                    <label class="fw-bold mt-2">Sales Tax:</label>
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Select whether commission amounts include sales tax or exclude sales tax.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                    <div class="input-cover mt-2">
                        <select wire:model="sales_tax_option_gross" class="form-control has-icon"
                            data-icon="fa-solid fa-ruler">
                            <option value="">Select</option>
                            <option value="including">Including Sales Tax</option>
                            <option value="excluding">Excluding Sales Tax</option>
                        </select>
                    </div>
                @endif
            </div>
        @elseif($seller_leasing_fee_type === 'Percentage of the Rent Due Each Rental Period')
            <div class="mt-3">
                <div class="input-group">
                    <input type="number" wire:model="seller_leasing_gross_rental" class="form-control"
                        placeholder="Enter percentage of the rent due each rental period (e.g., 10)">
                    <span class="input-group-text">%</span>
                </div>
            </div>
        @elseif($seller_leasing_fee_type === 'Percentage of the First Month\'s Rent')
            <div class="mt-3">
                <div class="input-group">
                    <input type="number" wire:model="seller_leasing_gross_month_rent" class="form-control"
                        placeholder="Enter percentage of month's rent (e.g., 100)">
                    <span class="input-group-text">%</span>
                </div>
            </div>
            @if (in_array($property_type, ['Commercial', 'Business']))
                <div class="mb-3">
                    <label class="fw-bold mt-2">Sales Tax:</label>
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Select whether commission amounts include sales tax or exclude sales tax.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                    <div class="input-cover mt-2">
                        <select wire:model="seller_leasing_gross_sales_tax_first_month" class="form-control has-icon"
                            data-icon="fa-solid fa-ruler">
                            <option value="">Select</option>
                            <option value="including">Including Sales Tax</option>
                            <option value="excluding">Excluding Sales Tax</option>
                        </select>
                    </div>
                </div>
                <div class="input-group mt-2">
                    <label class="form-label">Number of Months:</label>
                    <div class="input-group">
                        <span class="input-group-text">#</span>
                        <input type="number" wire:model="seller_leasing_gross_no_of_months" class="form-control"
                            placeholder="Enter number of months (e.g., 1)">
                    </div>
                </div>
            @endif
        @elseif($seller_leasing_fee_type === 'Percentage of Each Rental Period')
            <div class="mt-3">
                <div class="input-group"> <input type="number" wire:model="seller_leasing_each_rental"
                        class="form-control" placeholder="Enter percentage of each rental period (e.g., 10)">
                    <span class="input-group-text">%</span>
                </div>
            </div>
        @elseif($seller_leasing_fee_type === 'Percentage of Month\'s Rent')
            {{-- Commercial/Business Month's Rent option --}}
            <div class="mt-3">
                <div class="input-group">
                    <input type="number" wire:model="seller_leasing_gross_month_rent" class="form-control"
                        placeholder="Enter percentage of month's rent (e.g., 100)">
                    <span class="input-group-text">%</span>
                </div>
            </div>
            @if (in_array($property_type, ['Commercial', 'Business']))
                <div class="mb-3">
                    <label class="fw-bold mt-2">Sales Tax:</label>
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Select whether commission amounts include sales tax or exclude sales tax.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                    <div class="input-cover mt-2">
                        <select wire:model="seller_leasing_gross_sales_tax_first_month" class="form-control has-icon"
                            data-icon="fa-solid fa-ruler">
                            <option value="">Select</option>
                            <option value="including">Including Sales Tax</option>
                            <option value="excluding">Excluding Sales Tax</option>
                        </select>
                    </div>
                </div>
                <div class="input-group mt-2">
                    <label class="form-label">Number of Months:</label>
                    <div class="input-group">
                        <span class="input-group-text">#</span>
                        <input type="number" wire:model="seller_leasing_gross_no_of_months" class="form-control"
                            placeholder="Enter number of months (e.g., 1)">
                    </div>
                </div>
            @endif
        @elseif($seller_leasing_fee_type === 'Flat Fee + Percentage of the Gross Lease Value')
            <div class="row g-2 mb-3">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text"> $</span>
                        <input type="number" wire:model="seller_leasing_gross_flat_combo" class="form-control"
                            placeholder="Enter percentage of the gross lease value (e.g., 10)">
                    </div>
                </div>
                <div class="col-md-1 text-center pt-2">+</div>
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="number" wire:model="seller_leasing_gross_percentage_combo" class="form-control"
                            placeholder="Enter number of months (e.g., 1) ">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
            </div>
        @elseif($seller_leasing_fee_type === 'Flat Fee + Percentage of the Net Aggregate Rent')
            <div class="row g-2 mb-3">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text"> $</span>
                        <input type="number" wire:model="seller_leasing_gross_flat_net_combo" class="form-control"
                            placeholder="Enter flat fee amount (e.g., 1500)">
                    </div>
                </div>
                <div class="col-md-1 text-center pt-2">+</div>
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="number" wire:model="seller_leasing_gross_percentage_net_combo"
                            class="form-control" placeholder="Enter percentage of the net aggregate rent (e.g., 6)">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
            </div>
        @elseif ($seller_leasing_fee_type === 'Percentage of Gross Rent')
            <div class="mt-3">
                <div class="input-group">
                    <input type="number" wire:model="seller_leasing_gross_percentage" class="form-control"
                        placeholder="Enter the percentage of the gross rent (e.g., 6)">
                    <span class="input-group-text">%</span>
                </div>
            </div>
            <div class="mb-3">
                <label class="fw-bold mt-2">Sales Tax:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Select whether commission amounts include sales tax or exclude sales tax.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
                <div class="input-cover mt-2">
                    <select wire:model="seller_leasing_gross_sales_tax_option_gross" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler">
                        <option value="">Select</option>
                        <option value="including">Including Sales Tax</option>
                        <option value="excluding">Excluding Sales Tax</option>
                    </select>
                </div>
            </div>
        @elseif ($seller_leasing_fee_type === 'Flat Fee')
            @if (in_array($property_type, ['Commercial', 'Business']))
                <div class="mb-3">
                    <label class="fw-bold mt-2">Sales Tax:</label>
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Select whether commission amounts include sales tax or exclude sales tax.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                    <div class="input-cover mt-2">
                        <select wire:model="seller_leasing_gross_sales_tax_flat_free_gross" class="form-control has-icon"
                            data-icon="fa-solid fa-ruler">
                            <option value="">Select</option>
                            <option value="including">Including Sales Tax</option>
                            <option value="excluding">Excluding Sales Tax</option>
                        </select>
                    </div>
                </div>
            @endif
            <div class="input-group mt-3">
                <span class="input-group-text"> $</span>
                <input type="text" wire:model="seller_leasing_gross_purchase_fee_flat_amount" class="form-control"
                    placeholder="Enter flat fee amount (e.g., 5000)"
                    data-error-id="seller_leasing_gross_purchase_fee_flat_amount_error"
                    oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            </div>
            <span class="error mt-2" id="seller_leasing_gross_purchase_fee_flat_amount_error"></span>
        @elseif($seller_leasing_fee_type === 'other')
            <input type="text" wire:model="seller_leasing_gross_purchase_fee_other" class="form-control mt-2"
                placeholder="Enter lease fee structure (e.g., 100% of First Month's Rent, or a Tiered Schedule for Multi-Year Leases)">
        @elseif($seller_leasing_fee_type === 'Percentage of Net Aggregate Rent')
            <div class="mt-3">

                <div class="input-group">
                    <input type="text" wire:model="seller_leasing_gross_other" class="form-control"
                        placeholder="Enter percentage of net aggregate rent (e.g., 6)">
                    <span class="input-group-text">%</span>
                </div>
            </div>
        @endif
    </div>
@endif

<div class="form-group mb-2">
    <label class="fw-bold d-flex align-items-center">
        Interested in Offering a Lease-Option Agreement:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether the Seller is open to a lease with option to purchase. If “Yes” is selected, you'll be prompted to enter compensation details.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover mt-2">
        <select wire:model="interested_lease_option_agreement" class="form-control has-icon"
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
        <h5 class="compensation_tab">
            Compensation for Creating the Lease-Option Agreement:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Specify how the Broker will be compensated at the time the lease-option agreement is created. This may include a flat fee or a percentage of the option consideration paid by the party granting the option. This compensation is typically paid upfront and is separate from any commission that may be owed if the purchase option is later exercised.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </h5>

        <div class="form-group mt-2">
            {{-- <label class="fw-bold d-block mb-1">Compensation Amount:</label> --}}

            <div class="input-group">
                <!-- Select for type -->
                <select wire:model="lease_type"  wire:change="setType('lease', $event.target.value)" class="form-select" style="max-width: 100px;">
                    <option value="percent">%</option>
                    <option value="flat">$</option>
                </select>

                @if ($lease_type === 'flat')
                    <span class="input-group-text">$</span>
                @endif

                <!-- Single input -->
                <input type="text" step="any" wire:model.lazy="lease_value" class="form-control"
                    placeholder="{{ $lease_type === 'percent'
                        ? 'Enter percentage of option consideration (e.g., 5)'
                        : 'Enter flat fee amount (e.g., 1500)' }}"
                    data-error-id="lease_value_error"
                    oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">

                @if ($lease_type === 'percent')
                    <span class="input-group-text">%</span>
                @endif
            </div>
                        <span class="error mt-2" id="lease_value_error"></span>

        </div>
    </div>

    <!-- TAB 2 -->
    <div id="tab2" class="tab-content">
        <h5 class="compensation_tab">
            Compensation if Purchase Option is Exercised:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="If the purchase option is exercised, the Broker may be entitled to additional compensation. Enter how the Broker will be compensated at that time, such as a flat fee or a percentage of the total purchase price. Any compensation already received under the lease-option agreement may be credited toward the final amount due, depending on the terms of the agreement.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </h5>

        <div class="form-group mt-2">
            {{-- <label class="fw-bold d-block mb-1">Compensation Amount:</label> --}}

            <div class="input-group">
                <!-- Select for type -->
                <select wire:model="purchase_type"  wire:change="setType('purchase', $event.target.value)" class="form-select" style="max-width: 100px;">
                    <option value="percent">%</option>
                    <option value="flat">$</option>
                </select>

                @if ($purchase_type === 'flat')
                    <span class="input-group-text">$</span>
                @endif

                <!-- Single input -->
                <input type="text" step="any" wire:model.lazy="purchase_value" class="form-control"
                    placeholder="{{ $purchase_type === 'percent'
                        ? 'Enter percentage of the total purchase price (e.g., 6)'
                        : 'Enter flat fee amount (e.g., 5000)' }}"
                    data-error-id="purchase_value_error"
                    oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">

                @if ($purchase_type === 'percent')
                    <span class="input-group-text">%</span>
                @endif
            </div>
                        <span class="error mt-2" id="purchase_value_error"></span>

        </div>

    </div>
@endif
<!-- Protection Period Timeframe -->
<div class="form-group mb-4 mt-3">
    <label class="fw-bold d-flex align-items-center">
        Protection Period Timeframe (Days):
        @if (in_array($property_type, ['Residential', 'Income', 'Vacant Land']))
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the number of days after the Termination Date during which the Broker may still receive compensation if the property is sold or transferred to a party with whom the Seller, Broker, or any licensee communicated during the Listing Period. This protection ends if the property is relisted and sold through another Broker after that period.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        @endif
        @if (in_array($property_type, ['Commercial', 'Business']))
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the number of days after the Termination Date during which the Broker may still receive compensation if the property is sold, leased, or otherwise transferred to a party introduced by the Seller, Broker, or any licensee during the Listing Period. This protection ends if the Seller enters into a bona fide Exclusive Right of Sale agreement with another Broker after the Termination Date and the property is transferred during that new listing period.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        @endif
    </label>

    <div class="input-cover mt-2">
        <input type="number" wire:model="protection_period" class="form-control has-icon"
            data-icon="fa-solid fa-shield-alt" placeholder="Enter protection period in days (e.g., 90)">
    </div>
</div>

<!-- Early Termination Fee -->
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Early Termination Fee:
        @if (in_array($property_type, ['Residential', 'Income', 'Vacant Land']))
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select whether the Seller agrees to pay a cancellation fee if the agreement is conditionally terminated before the end of the Listing Period. If “Yes” is selected, you'll be prompted to enter the fee amount. This fee helps offset marketing costs and may be credited toward a future commission, depending on the Broker's policy. If the property is sold before the original Termination Date to a party introduced during the Listing Period, the Broker may void the termination and enforce the full commission, less the cancellation fee.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        @endif
        @if (in_array($property_type, ['Commercial', 'Business']))
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select whether the Seller agrees to pay a cancellation fee if the agreement is conditionally terminated before the end of the Listing Period. If “Yes” is selected, you'll be prompted to enter the fee amount. This fee helps offset marketing costs and may be credited toward a future commission, depending on the Broker's policy. If the property is sold before the original Termination Date to a party introduced during the Listing Period, the Broker may void the termination and enforce the full commission, less the cancellation fee.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        @endif
    </label>
    <div class="input-cover mt-2">
        <select wire:model="early_termination_fee_option" class="form-control has-icon"
            data-icon="fa-solid fa-exclamation-triangle">
            <option value="">Select</option>
            <option value="yes">Yes</option>
            <option value="no">No</option>
        </select>
    </div>

    @if ($early_termination_fee_option === 'yes')
        <div class="mt-3">
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="text" wire:model="early_termination_fee_amount" class="form-control"
                    placeholder="Enter early termination fee amount (e.g., 1000)"   data-error-id="early_termination_fee_amount_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            </div>

                                    <span class="error mt-2" id="early_termination_fee_amount_error"></span>

        </div>
    @endif
</div>

<!-- Retainer Fee -->
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Retainer Fee:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether the Seller agrees to pay a non-refundable retainer fee to initiate Broker services. If “Yes,” enter the amount. This fee is separate from any commission owed unless otherwise specified.">
            <i class="fa-solid fa-circle-info"></i>

        </span>
    </label>
    <div class="input-cover mt-2">
        <select wire:model="retainer_fee_option" class="form-control has-icon"
            data-icon="fa-solid fa-file-invoice-dollar">
            <option value="">Select</option>
            <option value="yes">Yes</option>
            <option value="no">No</option>
        </select>
    </div>

    @if ($retainer_fee_option === 'yes')
        <div class="mt-3">
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="text" wire:model="retainer_fee_amount" class="form-control"
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
                <select wire:model="retainer_fee_application" class="form-control mt-2">
                    <option value="">Select application method</option>
                    <option value="Applied Toward Final Compensation">Applied Toward Final Compensation</option>
                    <option value="Charged in Addition to Final Compensation">Charged in Addition to Final Compensation
                    </option>
                </select>
                @error('retainer_fee_application')
                    <span class="text-danger small">{{ $message }}</span>
                @enderror
            </div>
        </div>
    @endif
</div>
<div class="form-group mb-4">

    <label class="fw-bold d-flex align-items-center">
        Seller's Broker's Share of Retained Deposits:

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the percentage of any retained deposit the Seller's Broker is entitled to if the Buyer defaults, subject to applicable laws and limits defined in your agreement.">
            <i class="fa-solid fa-circle-info"></i>

        </span>

    </label>
    <div class="input-group">
        <input type="number" wire:model="retained_deposits" class="form-control"
            placeholder="Enter percentage of retained deposit the Broker will receive if Buyer defaults on contract (e.g., 50)">
        <span class="input-group-text">%</span>
    </div>

</div>
<!-- Tenant Agency Agreement Timeframe -->
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Seller Agency Agreement Timeframe:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select how long the Seller's agreement with the Broker will last. Choose from a preset duration or select “Other” to enter a custom timeframe.">
            <i class="fa-solid fa-circle-info"></i>

        </span>
    </label>
    <div class="input-cover mt-2">
        <select wire:model="agency_agreement_timeframe" class="form-control has-icon"
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
            <input type="text" wire:model="agency_agreement_custom" class="form-control"
                placeholder="Enter Seller agency agreement timeframe (e.g., 8 Months)">

        </div>
    @endif
</div>
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Acceptable Brokerage Relationship:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="<ul class='mb-0 ps-3'><li>Select the type of legal relationship the Seller wishes to establish with the Broker.</li><li>This determines the scope of representation provided.</li><li>Real estate laws vary by state, and Brokers may offer different types of agency relationships.</li><li>Both the Broker and Seller must comply with all current local, state, and federal real estate laws and regulations.</li></ul>">
            <i class="fa-solid fa-circle-info"></i>

        </span>
    </label>
    <div class="input-cover mt-2">
        <select wire:model="brokerage_relationship" class="form-control has-icon" data-icon="fa-solid fa-handshake">
            <option value="">Select</option>
            <option value="Transaction Broker Representation">Transaction Broker Representation</option>
            <option value="Single Agent Representation">Single Agent Representation</option>
            <option value="Dual Agency Representation">Dual Agency Representation</option>
            <option value="No Brokerage Relationship">No Brokerage Relationship</option>
        </select>
    </div>
    @error('brokerage_relationship')
        <span class="text-danger small">{{ $message }}</span>
    @enderror

    <!-- Dynamic Description Based on Selection -->
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
                    <li>The Broker must always act in the Seller's best interest.</li>
                    <li>Requires written consent from both the Seller and the Buyer.</li>
                    <li>Requires a Single Agent Notice signed by the Seller.</li>
                </ul>
            @elseif ($brokerage_relationship === 'Dual Agency Representation')
                <h6 class="fw-bold">• Dual Agency Representation:</h6>
                <ul class="mb-2 ps-3">
                    <li>The Broker represents both the Seller and the Buyer in the same transaction.</li>
                    <li>Must remain neutral and may not disclose confidential information from either party.</li>
                    <li>Requires written consent from both the Seller and the Buyer.</li>
                    <li>Not permitted in Alaska, Colorado, Florida, Kansas, Maryland, Oklahoma, Texas, Vermont, and
                        Wyoming.</li>
                </ul>
            @elseif ($brokerage_relationship === 'No Brokerage Relationship')
                <h6 class="fw-bold">• No Brokerage Relationship:</h6>
                <ul class="mb-2 ps-3">
                    <li>The Broker does not represent the Seller and has no fiduciary duties.</li>
                    <li>Still required to act honestly and disclose all known facts that materially affect the
                        property's value.</li>
                    <li>The Seller is responsible for their own due diligence and negotiations.</li>
                </ul>
            @endif
            <div class="alert alert-warning mt-3 p-2 small">
                <strong>⚠️ Legal Notice:</strong> Certain brokerage relationships are not permitted in all states. If
                your selection is not allowed, the Broker will establish a permitted legal alternative. Real estate laws
                change frequently. Both the Broker and Seller are responsible for complying with all current local,
                state, and federal laws.
            </div>
        </div>
    @endif
</div>
<!-- Additional Terms -->
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Additional Terms:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Include any additional or custom compensation terms, conditions, or agreements not covered above.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <textarea wire:model="additional_details_broker" class="form-control mt-2" rows="3"
        placeholder="Enter any additional terms"></textarea>
</div>
