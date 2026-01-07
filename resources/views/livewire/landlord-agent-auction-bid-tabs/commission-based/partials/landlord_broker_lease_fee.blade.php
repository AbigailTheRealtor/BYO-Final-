
    <div class="form-group mb-4">
        <label class="fw-bold d-flex align-items-center">
            Landlord’s Broker Lease Fee:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Choose how the Landlord’s Broker will be compensated if the property is leased. Options include: a percentage of the rent due each rental period, a percentage of the gross lease value, a percentage of the first month’s rent, a flat fee, or “Other” to define a custom payment structure. Then, enter the appropriate amount based on your selection.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover mt-2">
            <select wire:model="purchase_fee_type" class="form-control has-icon"
                data-icon="fa-solid fa-file-invoice-dollar">
                <option value="">Select</option>
                <option value="Percentage of the Rent Due Each Rental Period">Percentage of the Rent Due Each Rental Period</option>
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
                        <input type="text" wire:model="purchase_fee_flat" class="form-control"
                            placeholder="Enter flat fee amount (e.g., 5000)"
                             data-error-id="purchase_fee_flat_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">



                    <!--
                       <select wire:model="purchase_fee_flat_type" wire:change="setType('purchase_fee_flat', $event.target.value)" class="form-select" style="max-width: 100px;">
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
                title="Choose how the Landlord’s Broker will be compensated if the property is leased. Options include: a percentage of the net aggregate rent, a percentage of the gross rent, a percentage of one month’s rent, a flat fee, or select “Other” to define a custom payment structure. Then, enter the appropriate amount based on your selection.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

        <div class="input-cover mt-2">
            <select wire:model="purchase_fee_type" class="form-control has-icon"
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
                        <input type="number" wire:model="purchase_fee_net_aggregate" class="form-control"
                            placeholder="Enter percentage of the net aggregate rent (e.g., 5)">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
            @elseif ($purchase_fee_type === 'Percentage of the Gross Rent')
                <div class="form-group">
                    {{-- <label class="fw-bold">Percentage of Gross Rent</label> --}}
                    <div class="input-group">
                        <input type="number" wire:model="purchase_fee_gross_rent" class="form-control"
                            placeholder="Enter percentage of the gross rent (e.g., 5)">
                        <span class="input-group-text">%</span>
                    </div>
                    <label class="fw-bold mt-2">Sales Tax:</label>
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true" title="Select whether commission amounts include sales tax or exclude sales tax.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>

                    <div class="input-cover mt-2">

                        <select wire:model="sales_tax_option_gross"class="form-control has-icon"
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
                        <input type="number" wire:model="purchase_fee_monthly_percentage" class="form-control"
                            placeholder="Enter percentage of month’s rent (e.g., 100)">
                        <span class="input-group-text">%</span>
                    </div>

                    <label class="fw-bold mt-3">Number of Months:</label>
                    <div class="input-group mt-1">
                        <span class="input-group-text">#</span>
                        <input type="number" wire:model="purchase_fee_months" class="form-control"
                            placeholder="Enter number of months (e.g., 1)">
                    </div>
                    <label class="fw-bold mt-3">Sales Tax:</label>
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Select whether commission amounts include sales tax or exclude sales tax.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                    <div class="input-cover mt-2">

                        <select wire:model="sales_tax_option_monthly" class="form-control has-icon"
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
                        <input type="text" wire:model="purchase_fee_flat_commercial" class="form-control"
                            placeholder="Enter flat fee amount (e.g., 3000)"
                             data-error-id="purchase_fee_flat_commercial_error"
                    oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
                    </div>
                    <span class="error mt-2" id="purchase_fee_flat_commercial_error"></span>

                    <label class="fw-bold mt-3">Sales Tax:</label>
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Select whether commission amounts include sales tax or exclude sales tax.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                    <div class="input-cover mt-2">

                        <select wire:model="sales_tax_option_flat" class="form-control has-icon"
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
                        <input type="number" wire:model="purchase_fee_purchase_price" class="form-control"
                            placeholder="Enter percentage of total purchase price (e.g., 2)">
                        <span class="input-group-text">%</span>
                    </div>
                </div>



            @elseif ($purchase_fee_type === 'other')
                <div class="input-group">
                    <input type="text" wire:model="purchase_fee_other_commercial" class="form-control"
                        placeholder="Enter lease fee structure (e.g., 100% of First Month's Rent, or a Tiered Schedule for Multi-Year Leases)">
                    {{-- <span class="input-group-text">%</span> --}}
                </div>
            @endif
        </div>
    </div>