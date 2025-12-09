<!-- Section Heading -->
<h3>Purchasing Terms</h3>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>💵 Enter the Buyer’s maximum budget, preferred closing date, financing details, and any acceptable
                special sale provisions. </strong>
        </div>
    </div>
</div>

<!-- Special Sale Provisions Dropdown -->
<!-- Special Sale Provisions Dropdown -->

<div class="form-group">
    <label class="fw-bold">Acceptable Special Sale Provisions:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select any special sale conditions the Buyer is open to. If the situation isn’t listed, select “Other” and provide details.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>


    <div class="input-cover">
        <select wire:model="sale_provision" id="sale_provision" class="form-control has-icon select2-multiple"
            data-icon="fa-solid fa-screwdriver-wrench input-icon2" multiple>
            @foreach ($buyer_property as $row_pt)
                <option value="{{ $row_pt['name'] }}" title="{{ $row_pt['description'] }}">{{ $row_pt['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2" id="sale_provision_error"></span>
</div>

<!-- Other Special Sale Provision Input -->
@if (in_array('Other', $sale_provision))
    <div class="form-group mt-3">
        <label class="fw-bold">Other Special Sale Provision:</label>
        <div class="input-cover">
            <input type="text" wire:model="sale_provision_other" class="form-control has-icon"
                data-icon="fa-solid fa-screwdriver-wrench"
                placeholder="Enter other special sale provision (e.g., Divorce Sale, Third-Party Approval)">
        </div>
    </div>
@endif

<!-- Assignment Contract Flow -->
@if (in_array('Assignment Contract', $sale_provision))
    <!-- Buyer Under Contract Question -->
    <div class="form-group mt-3">
        <label class="fw-bold">Buyer Open to Purchasing an Assignment Contract:</label>
        <div class="input-cover">
            <select wire:model="sale_provision_assignment" class="form-control has-icon"
                data-icon="fa-solid fa-file-contract">
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </div>
    </div>

    <!-- If Buyer is Under Contract (Yes) -->
    @if ($sale_provision_assignment === 'Yes')
        {{-- <div class="form-group mt-3">
            <label class="fw-bold">Assignment Fee to Broker:</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false" style="height: 100%;">
                        {{ $assignment_fee_type }}
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="#" wire:click.prevent="setAssignmentFeeType('$')">$ (Flat
                            Fee)</a>
                        <a class="dropdown-item" href="#" wire:click.prevent="setAssignmentFeeType('%')">%
                            (Percentage)</a>
                    </div>
                </div>
                <input type="number" wire:model="assignment_fee_amount" class="form-control"
                    placeholder="{{ $assignment_fee_type === '$' ? 'Enter flat fee (e.g., 2500)' : 'Enter percentage of contract assignment value (e.g., 2)' }}">
            </div>
        </div> --}}
        <div class="form-group mt-3">
            <label class="fw-bold">Assignment Fee to Broker:</label>
            <div class="input-group">
                @if ($assignment_fee_type === '$')
                    <!-- Show dropdown button first for $ -->
                    <div class="input-group-prepend">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown"
                            aria-haspopup="true" aria-expanded="false" style="height: 100%;">
                            {{ $assignment_fee_type }}
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="#" wire:click.prevent="setAssignmentFeeType('$')">$
                                (Flat Fee)</a>
                            <a class="dropdown-item" href="#" wire:click.prevent="setAssignmentFeeType('%')">%
                                (Percentage)</a>
                        </div>
                    </div>
                    <input type="number" wire:model="assignment_fee_amount" class="form-control"
                        placeholder="Enter flat fee (e.g., 2500)">
                @else
                    <!-- Show input first for % -->
                    <input type="number" wire:model="assignment_fee_amount" class="form-control"
                        placeholder="Enter percentage (e.g., 2)">
                    <div class="input-group-append">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown"
                            aria-haspopup="true" aria-expanded="false" style="height: 100%;">
                            {{ $assignment_fee_type }}
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="#" wire:click.prevent="setAssignmentFeeType('$')">$
                                (Flat Fee)</a>
                            <a class="dropdown-item" href="#" wire:click.prevent="setAssignmentFeeType('%')">%
                                (Percentage)</a>
                        </div>
                    </div>
                @endif
            </div>
        </div>

    @endif

    <!-- If Buyer is NOT Under Contract (No) -->
    @if ($sale_provision_assignment === 'No')
        <div class="alert alert-warning mt-3 p-2 small">
            <strong>⚠️ Note:</strong> This section is only for Buyers who are open to purchasing an assignment contract.
            If this does not apply, please select a different special sale provision.

        </div>

        {{-- <div class="form-group mt-3">
            <label class="fw-bold">Buyer Looking to Sell Contract:</label>
            <div class="input-cover">
                <select wire:model="buyer_sell_contract" class="form-control has-icon"
                    data-icon="fa-solid fa-handshake">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
            </div>
        </div>

        <!-- If Buyer Wants to Sell Contract -->
        @if ($buyer_sell_contract === 'Yes')
            <div class="form-group mt-3">
                <label class="fw-bold">Assignment Fee to Broker:</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown"
                            aria-haspopup="true" aria-expanded="false" style="height: 100%;">
                            {{ $assignment_fee_type }}
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="#" wire:click.prevent="setAssignmentFeeType('$')">$
                                (Flat Fee)</a>
                            <a class="dropdown-item" href="#" wire:click.prevent="setAssignmentFeeType('%')">%
                                (Percentage)</a>
                        </div>
                    </div>
                    <input type="number" wire:model="assignment_fee_amount" class="form-control"
                        placeholder="{{ $assignment_fee_type === '$' ? 'Enter flat fee (e.g., 2500)' : 'Enter percentage of contract assignment value (e.g., 2)' }}">
                </div>
            </div>
        @endif --}}
    @endif
@endif

<div class="form-group mt-4">
    <label class="fw-bold">Target Closing Date: <span class="text-danger">*</span>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the date by which the Buyer would ideally like to close on a property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>


    <div class="input-cover">
        <select wire:model="target_closing_date" id="target_closing_date" class="form-control has-icon"
            data-icon="fa-regular fa-calendar-days" required>
            <option value="">Select</option>
            <option value="ASAP (Ready Now)">ASAP (Ready Now)</option>
            <option value="Within 1 Month">Within 1 Month</option>
            <option value="Within 2 Months">Within 2 Months</option>
            <option value="Within 3 Months">Within 3 Months</option>
            <option value="Within 4 Months">Within 4 Months</option>
            <option value="Within 5 Months">Within 5 Months</option>
            <option value="Within 6 Months">Within 6 Months</option>
            <option value="Over 6 Months">Over 6 Months</option>
            <option value="Flexible / Open-Ended">Flexible / Open-Ended</option>
        </select>
    </div>
    <span class="error mt-2" id="target_closing_date_error">
        @error('target_closing_date')
            {{ $message }}
        @enderror
    </span>
</div>

<!-- Maximum Budget -->
<div>
    <div class="form-group">
        <label class="fw-bold">Maximum Budget: <span class="text-danger">*</span>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Specify the maximum total amount the Buyer is willing to pay for a property.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>




        <div class="input-cover">
            <span class="input-group-text-seller">$</span>
            <input type="text" wire:model="maximum_budget" class="form-control"
                placeholder="Enter buyer's budget amount (e.g., 450000)" required

                 data-error-id="number_of_unit_error" oninput="validateInput(this)"
                onblur="reformatNumber(this)" onpaste="handlePaste(event)">
        </div>

    </div>
    <span class="error mt-2" id="number_of_unit_error"></span>
</div>

<!-- Offered Financing/Currency -->
<div class="form-group mt-3">
    <label class="fw-bold">Offered Financing/Currency: <span class="text-danger">*</span></label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the Buyer’s intended method(s) of purchase such as cash, conventional loan, seller financing, or alternative forms like cryptocurrency or exchange/trade. ">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    {{-- <div class="input-cover">
        <select wire:model="offered_financing" class="form-control has-icon" data-icon="fa-solid fa-money-bill-wave"
            required>
            <option value="">Select</option>
            @foreach (['Assumable', 'Cash', 'Conventional', 'Cryptocurrency', 'Exchange/Trade', 'FHA', 'Jumbo', 'Lease Option', 'Lease Purchase', 'Non-Fungible Token (NFT)', 'No-Doc', 'Non-QM', 'Seller Financing', 'USDA', 'VA', 'Other'] as $option)
                <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
        </select>
    </div> --}}

    <div class="input-cover">
        <select wire:model="offered_financing" class="form-control has-icon" data-icon="fa-solid fa-money-bill-wave"
            required>
            <option value="">Select</option>

            @foreach ($financing_options as $option)
                <option value="{{ $option['name'] }}" title="{{ $option['description'] }}">
                    {{ $option['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2" id="number_of_unit_error"></span>

</div>

@if ($offered_financing === 'Other')
    <div>
        <div class="form-group">
            <label class="fw-bold">Other Financing/Currency:</label>
            <div class="input-cover">
                <input type="text" wire:model="other_financing" class="form-control has-icon"
                    data-icon="fa-solid fa-money-bill-wave"
                    placeholder="Enter type of financing or currency offered (e.g., Gold Bullion, Stock Transfer, Private Investment Agreement)">
            </div>
        </div>
    </div>
@endif

<!-- Cash Option -->
@if ($offered_financing === 'Cash')
    <div>
        <div class="form-group">
            <label class="fw-bold">Offered Cash Amount:<span class="text-danger">*</span></label>

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the total cash amount the Buyer is offering for the purchase (e.g., 500000).">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <span class="input-group-text-seller">$</span>

                <input type="text" wire:model="cash_budget" class="form-control has-icon"
                    placeholder="Enter the total cash amount the Buyer is offering for the purchase (e.g., 500000)."

                     data-error-id="cash_budget_error" oninput="validateInput(this)"
                onblur="reformatNumber(this)" onpaste="handlePaste(event)
                " required>
            </div>
            <span class="error mt-2" id="cash_budget_error"></span>
        </div>
    </div>
@endif

<!-- Conventional, FHA, Jumbo, VA, No-Doc, Non-QM, USDA -->
@if (in_array($offered_financing, ['Conventional', 'FHA', 'Jumbo', 'VA', 'No-Doc', 'Non-QM', 'USDA']))
    <div class="form-group mt-3">
        <label class="fw-bold">Buyer Pre-Approved for a Loan:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select “Yes” if the Buyer has been pre-approved for a loan. If “No,” no pre-approval has been granted yet.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="pre_approved" class="form-control has-icon" data-icon="fa-solid fa-file-signature">
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </div>
    </div>

    @if ($pre_approved === 'Yes')
        <div class="form-group">
            <label class="fw-bold">Buyer Pre-Approval Amount:<span class="text-danger">*</span></label>

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the amount listed on the Buyer’s pre-approval letter (e.g., 800000).">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <span class="input-group-text-seller">$</span>

                <input type="text" wire:model="pre_approval_amount" class="form-control has-icon"
                    placeholder="Enter pre-approved loan amount (e.g., 800000)"
                     data-error-id="pre_approval_amount_error" oninput="validateInput(this)"
                onblur="reformatNumber(this)" onpaste="handlePaste(event)
                " required>
            </div>
            <span class="error mt-2" id="pre_approval_amount_error"></span>

        </div>
    @endif
@endif

<!-- Seller Financing -->
@if ($offered_financing === 'Seller Financing')
    <div class="form-group">
        <label class="fw-bold">Desired Purchase Price:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the total price the Buyer is offering to purchase the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>

            <input type="text" wire:model="purchase_price" class="form-control has-icon"
                placeholder="Enter total purchase price (e.g., 500000)" required
                data-error-id="purchase_price_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)"
                >
        </div>

                        <span class="error mt-2" id="purchase_price_error"></span>

    </div>

{{-- 
    <div class="form-group mt-3">
        <label class="fw-bold">Desired Down Payment:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the down payment amount the Buyer is offering.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-group">
            @if ($down_payment_type === '$')
                <!-- Show dropdown button first for $ -->
                <div class="input-group-prepend">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false" style="height: 100%;">
                        {{ $down_payment_type }}
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="#" wire:click.prevent="setDownPaymentType('$')">$ (Flat
                            Fee)</a>
                        <a class="dropdown-item" href="#" wire:click.prevent="setDownPaymentType('%')">%
                            (Percentage)</a>
                    </div>
                </div>
                <input type="number" wire:model="down_payment_amount" class="form-control"
                    placeholder="Enter down payment amount (e.g., 100000)">
            @else
                <!-- Show input first for % -->
                <input type="number" wire:model="down_payment_amount" class="form-control"
                    placeholder=
                    "Enter down payment amount (e.g., 20)">
                <div class="input-group-append">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false" style="height: 100%;">
                        {{ $down_payment_type }}
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="#" wire:click.prevent="setDownPaymentType('$')">$ (Flat
                            Fee)</a>
                        <a class="dropdown-item" href="#" wire:click.prevent="setDownPaymentType('%')">%
                            (Percentage)</a>
                    </div>
                </div>
            @endif
        </div>

    </div> --}}


    <div class="form-group mt-3">
              <label class="fw-bold">Desired Down Payment:<span class="text-danger">*</span></label>


           <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the down payment amount the Buyer is offering.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-group">
                <!-- Select for type -->
                <select wire:model="down_payment_type" class="form-select" wire:change="setType('down_payment_type', $event.target.value)" style="max-width: 100px;">
                    <option value="$">$</option>
                    <option value="%">%</option>
                </select>

                <!-- Single input -->
                <input type="text" step="any" wire:model.lazy="down_payment_amount" class="form-control"
                    placeholder="{{ $down_payment_type === '%'
                        ? 'Enter down payment amount (e.g., 20)'
                        : 'Enter down payment amount (e.g., 100000)' }}"
                         data-error-id="down_payment_type_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)" required>

                <!-- Suffix -->
                <span class="input-group-text">
                    {{ $down_payment_type === '$' ? '$' : '%' }}
                </span>


            <span class="error mt-2" id="down_payment_type_error"></span>

            </div>

    </div>




    
    <div class="form-group mt-3">
        <label class="fw-bold">Desired Seller Financing Amount:<span class="text-danger">*</span></label>


           <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the amount the Buyer would like the Seller to finance.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-group">
                <!-- Select for type -->
                <select wire:model="seller_financing_type" class="form-select" wire:change="setType('seller_financing_type', $event.target.value)" style="max-width: 100px;">
                    <option value="$">$</option>
                    <option value="%">%</option>
                </select>

                <!-- Single input -->
                <input type="text" step="any" wire:model.lazy="seller_financing_amount" class="form-control"
                    placeholder="{{ $seller_financing_type === '%'
                        ? 'Enter seller financing amount (e.g., 80)'
                        : 'Enter seller financing amount (e.g., 400000)' }}"
                         data-error-id="seller_financing_amount_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)" required>

                <!-- Suffix -->
                <span class="input-group-text">
                    {{ $seller_financing_type === '$' ? '$' : '%' }}
                </span>


            <span class="error mt-2" id="seller_financing_amount_error"></span>

            </div>

    </div>


    {{-- <div class="form-group mt-3">
        <label class="fw-bold">Desired Seller Financing Amount:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the amount the Buyer would like the Seller to finance.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-group">
            @if ($seller_financing_type === '$')
                <!-- Show dropdown button first for $ -->
                <div class="input-group-prepend">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false" style="height: 100%;">
                        {{ $seller_financing_type }}
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="#" wire:click.prevent="setSellerFinancingType('$')">$
                            (Flat
                            Fee)</a>
                        <a class="dropdown-item" href="#" wire:click.prevent="setSellerFinancingType('%')">%
                            (Percentage)</a>
                    </div>
                </div>
                <input type="number" wire:model="seller_financing_amount" class="form-control"
                    placeholder="Enter seller financing amount (e.g., 400000)">
            @else
                <!-- Show input first for % -->
                <input type="number" wire:model="seller_financing_amount" class="form-control"
                    placeholder="Enter seller financing amount (e.g., 80)">
                <div class="input-group-append">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false" style="height: 100%;">
                        {{ $seller_financing_type }}
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="#" wire:click.prevent="setSellerFinancingType('$')">$
                            (Flat
                            Fee)</a>
                        <a class="dropdown-item" href="#" wire:click.prevent="setSellerFinancingType('%')">%
                            (Percentage)</a>
                    </div>
                </div>
            @endif
        </div>

    </div> --}}

    <div class="form-group">
        <label class="fw-bold">Desired Interest Rate:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the interest rate the Buyer is offering.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="interest_rate" class="form-control has-icon"
                placeholder="Enter interest rate (e.g., 6.5)"
                data-error-id="interest_rate_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)" required>

            <span class="input-group-text-seller">%</span>

        </div>
                    <span class="error mt-2" id="interest_rate_error"></span>

    </div>

    <div class="form-group">
        <label class="fw-bold">Desired Loan Duration:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the term of the loan in years.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="loan_duration" class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days" placeholder="Enter loan duration (e.g., 30 Years)" required>

        </div>
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Prepayment Penalty:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate if the Buyer agrees to a penalty for early payoff and, if so, enter the amount.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="prepayment_penalty" class="form-control has-icon"
                data-icon="fa-solid fa-exclamation-circle" required>
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </div>
    </div>

    @if ($prepayment_penalty === 'Yes')
        <div class="form-group">
            <label class="fw-bold">Prepayment Penalty Amount:</label>
            <div class="input-cover">
                <span class="input-group-text-seller">$</span>

                <input type="text" wire:model="prepayment_penalty_amount" class="form-control has-icon"
                    placeholder="Enter prepayment penalty amount (e.g., 5000)"  data-error-id="prepayment_penalty_amount_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)" >
            </div>
                    <span class="error mt-2" id="prepayment_penalty_amount_error"></span>

        </div>
    @endif
    <div class="form-group mt-3">
        <label class="fw-bold">Balloon Payment:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate if a balloon payment is included and, if so, enter the amount and due date.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="balloon_payment" class="form-control has-icon"
                data-icon="fa-solid fa-money-bill-wave" required>
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </div>
    </div>

    @if ($balloon_payment === 'Yes')
        <div class="form-group">
            <label class="fw-bold">Balloon Payment Amount:</label>
            <div class="input-cover">

                <input type="number" wire:model="balloon_payment_amount" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="Enter balloon payment amount (e.g., 100000)"
                    
                     data-error-id="balloon_payment_amount_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)" 
                >
            </div>

                                <span class="error mt-2" id="balloon_payment_amount_error"></span>

        </div>

        <div class="form-group">
            <label class="fw-bold">Balloon Payment Due Date:</label>
            <div class="input-cover">
                <input type="text" wire:model="balloon_payment_date" class="form-control has-icon"
                    data-icon="fa-regular fa-calendar-days" placeholder="Enter balloon payment date (e.g., 5 Years)">

            </div>
        </div>
    @endif
@endif

<!-- Assumable Financing -->
@if ($offered_financing === 'Assumable')
    <div class="form-group">
        <label class="fw-bold">Offered Assumable Terms:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the terms of the assumable loan being proposed, including remaining balance, interest rate, term type (fixed/variable), and remaining duration.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="assumable_terms" class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days"
                placeholder="Enter assumable terms (e.g., $250,0000 remaining at 4.25% for 20 years) " required>
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Maximum Interest Rate of Assumable Loan:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the maximum interest rate the Buyer is willing to accept for the assumable loan (e.g., 5).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="max_assumable_rate" class="form-control has-icon"
                placeholder="Enter maximum acceptable interest rate (e.g., 5)"
                 data-error-id="max_assumable_rate_error" oninput="validateInput(this)"
                onblur="reformatNumber(this)" onpaste="handlePaste(event)
                " required>
            <span class="input-group-text-seller">%</span>

        </div>
                <span class="error mt-2" id="max_assumable_rate_error"></span>

    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Maximum Monthly Payment (Principal & Interest) for Assumable Loan:<span
                class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the highest monthly principal and interest payment the Buyer is willing to make. Exclude taxes, insurance, and HOA unless included in the mortgage.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>

            <input type="text" wire:model="max_monthly_payment" class="form-control has-icon"
                placeholder="Enter maximum monthly payment (e.g., 2000)"
                 data-error-id="max_monthly_payment_error" oninput="validateInput(this)"
                onblur="reformatNumber(this)" onpaste="handlePaste(event)
                " required>
        </div>
                <span class="error mt-2" id="max_monthly_payment_error"></span>


    </div>

    {{-- <div class="form-group mt-3">
        <label class="fw-bold">Down Payment Buyer Can Afford to Bridge the Gap:</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown"
                    aria-haspopup="true" aria-expanded="false" style="height: 100%;">
                    {{ $gap_payment_type }}
                </button>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="#" wire:click.prevent="setGapPaymentType('$')">$(Flat
                        Fee)</a>
                    <a class="dropdown-item" href="#" wire:click.prevent="setGapPaymentType('%')">%
                        (Percentage)</a>
                </div>
            </div>
            <input type="number" wire:model="gap_payment_amount" class="form-control"
                placeholder="{{ $gap_payment_type === '$' ? 'Enter down payment amount to bridge gap (e.g., 50000)' : 'Enter down payment amount to bridge gap (e.g., 10)' }}">
        </div>
    </div> --}}

    {{-- <div class="form-group mt-3">
        <label class="fw-bold">Down Payment Buyer Can Afford to Bridge the Gap:<span
                class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the amount the Buyer can pay upfront to cover the difference between the purchase price and the loan balance.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-group">
            @if ($gap_payment_type === '$')
                <!-- Show dropdown button first for $ -->
                <div class="input-group-prepend">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false" style="height: 100%;">
                        {{ $gap_payment_type }}
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="#" wire:click.prevent="setGapPaymentType('$')">$ (Flat
                            Fee)</a>
                        <a class="dropdown-item" href="#" wire:click.prevent="setGapPaymentType('%')">%
                            (Percentage)</a>
                    </div>
                </div>
                <input type="text" wire:model="gap_payment_amount" class="form-control"
                    placeholder="Enter down payment amount to bridge gap (e.g., 50000)"
                     data-error-id="gap_payment_amount_error" oninput="validateInput(this)"
                onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            @else
                <!-- Show input first for % -->
                <input type="text" wire:model="gap_payment_amount" class="form-control"
                    placeholder="Enter down payment amount to bridge gap (e.g., 10)"  data-error-id="gap_payment_amount_error" oninput="validateInput(this)"
                onblur="reformatNumber(this)" onpaste="handlePaste(event)
                ">
                <div class="input-group-append">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false" style="height: 100%;">
                        {{ $gap_payment_type }}
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="#" wire:click.prevent="setGapPaymentType('$')">$ (Flat
                            Fee)</a>
                        <a class="dropdown-item" href="#" wire:click.prevent="setGapPaymentType('%')">%
                            (Percentage)</a>
                    </div>
                </div>
            @endif
        </div>

    </div> --}}




       <div class="form-group mt-3">
        <label class="fw-bold">Down Payment Buyer Can Afford to Bridge the Gap:<span
                class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the amount the Buyer can pay upfront to cover the difference between the purchase price and the loan balance.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

     <div class="input-group">
                <!-- Select for type -->
                <select wire:model="gap_payment_type" class="form-select" wire:change="setType('gap_payment_type', $event.target.value)" style="max-width: 100px;">
                    <option value="$">$</option>
                    <option value="%">%</option>
                </select>

                <!-- Single input -->
                <input type="text" step="any" wire:model.lazy="gap_payment_amount" class="form-control"
                    placeholder="{{ $gap_payment_type === '%'
                        ? 'Enter down payment amount to bridge gap (e.g., 10)'
                        : 'Enter down payment amount to bridge gap (e.g., 50000)' }}"
                         data-error-id="gap_payment_amount_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)" required>

                <!-- Suffix -->
                <span class="input-group-text">
                    {{ $gap_payment_type === '%' ? '%' : '$' }}
                </span>


        <span class="error mt-2" id="gap_payment_amount_error"></span>

            </div>

    </div>


@endif
<!-- Exchange/Trade Option -->
@if ($offered_financing === 'Exchange/Trade')
    <div class="form-group mt-3">
        <label class="fw-bold">Acceptable Exchange Item:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the type of item the Buyer is offering (e.g., another home, artwork, boat, jewelry, motorhome, vehicle, or other).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="exchange_item" class="form-control has-icon" data-icon="fa-solid fa-exchange-alt" required>
                <option value="">Select</option>
                <option value="Another Home">Another Home</option>
                <option value="Artwork">Artwork</option>
                <option value="Boat">Boat</option>
                <option value="Jewelry">Jewelry</option>
                <option value="Motorhome">Motorhome</option>
                <option value="Vehicle">Vehicle</option>
                <option value="Other">Other</option>
            </select>
        </div>
    </div>

    @if ($exchange_item === 'Other')
        <div class="form-group">
            <label class="fw-bold">Other Exchange Item:</label>
            <div class="input-cover">
                <input type="text" wire:model="other_exchange_item" class="form-control has-icon"
                    data-icon="fa-solid fa-exchange-alt"
                    placeholder="Enter exchange item (e.g., Private Jet, Yacht, Luxury RV)">
            </div>
        </div>
    @endif

    <div class="form-group">
        <label class="fw-bold">Estimated Value of Exchange/Trade Item:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the estimated fair market value of the trade item.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">

            <span class="input-group-text-seller">$</span>

            <input type="text" wire:model="exchange_item_value"class="form-control has-icon"
                placeholder="Enter estimated item value (e.g., 75000)" required
                
                 data-error-id="exchange_item_value_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)"
                >

        </div>
                <span class="error mt-2" id="exchange_item_value_error"></span>

    </div>

    <div class="form-group">
        <label class="fw-bold">Condition of Exchange/Trade Item:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the condition of the trade item (e.g., new, good, fair).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="exchange_item_condition" class="form-control has-icon"
                data-icon="fa-solid fa-clipboard-check" required>
                <option value="">Select</option>
                <option value="New">New</option>
                <option value="Like New">Like New</option>
                <option value="Excellent">Excellent</option>
                <option value="Very Good">Very Good</option>
                <option value="Good">Good</option>
                <option value="Fair">Fair</option>
                <option value="Repair">Repair</option>
                <option value="Salvage Condition">Salvage Condition</option>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Additional Cash Buyer Will Offer:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the amount of additional cash the Buyer will provide if the trade item’s value is less than the purchase price.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>

            <input type="text" wire:model="additional_cash" class="form-control has-icon"
                placeholder="Enter additional cash offered (e.g., 25000)"
                 data-error-id="additional_cash_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)"
               required >
        </div>
                        <span class="error mt-2" id="additional_cash_error"></span>

    </div>

    <div class="form-group">
        <label class="fw-bold">Value of Exchange/Trade Item Determined:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter how the trade item’s value will be verified (e.g., licensed appraisal, online valuation, mutual agreement).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="value_determination" class="form-control has-icon"
                data-icon="fa-solid fa-exchange-alt"
                placeholder="Enter how the value of the exchange/trade item should be determined (e.g., Licensed Appraisal, Online Valuation, Mutual Agreement)"
                
                 required>
        </div>


    </div>
@endif

<!-- Lease Option -->
@if ($offered_financing === 'Lease Option')
    <div class="form-group">
        <label class="fw-bold">Buyer's Desired Offering Price for Lease Option:<span
                class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the price the Buyer is willing to pay if the purchase option is exercised.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>

            <input type="text" wire:model="lease_option_price" class="form-control has-icon"
                placeholder="Enter offering price for lease option (e.g., 500000)"
                 data-error-id="lease_option_price_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)" required
                >
        </div>

            <span class="error mt-2" id="alease_option_price_error"></span>

    </div>

    <div class="form-group">
        <label class="fw-bold">Specific Terms Proposed for Lease Option:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter any proposed terms for the lease option (e.g., inspections allowed during lease term).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="lease_option_terms" class="form-control has-icon"
                data-icon="fa-solid fa-file-alt"
                placeholder="Enter any conditions or requirements for the lease option (e.g., Buyer may conduct inspections during lease term, Seller to maintain property)" required>
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Proposed Duration of Lease (Months):<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the number of months the Buyer wishes to lease before having the option to purchase.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="lease_option_duration" class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days"
                placeholder="Enter the proposed lease duration in months (e.g., 6)" required>

        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Monthly Payment Buyer is Offering:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the monthly lease payment the Buyer is offering.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>

            <input type="text" wire:model="lease_option_payment" class="form-control has-icon"
                placeholder="Enter monthly payment amount (e.g., 2500)" required
                 data-error-id="lease_option_payment_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)"
               required >

        </div>
        <span class="error mt-2" id="lease_option_payment_error"></span>

    </div>

    <div class="form-group">
        <label class="fw-bold">Conditions or Requirements for Lease Option:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter any additional requirements or limitations (e.g., option exercisable after 12 months).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="lease_option_conditions" class="form-control has-icon"
                data-icon="fa-solid fa-file-alt"
                placeholder="Enter any conditions or requirements for the lease option (e.g., Buyer may exercise option after 12 months, Property must pass inspection)" required>
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Offered Option Fee:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the non-refundable fee the Buyer is offering for the option, if applicable.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="has_option_fee" class="form-control has-icon"
                data-icon="fa-solid fa-file-invoice-dollar" required>
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </div>
    </div>

    @if ($has_option_fee === 'Yes')
        <div class="form-group">
            <label class="fw-bold">Offered Option Fee:</label>
            <div class="input-cover">

                <span class="input-group-text-seller">$</span>

                <input type="text" wire:model="option_fee_amount" class="form-control has-icon"
                    placeholder="Enter option fee amount (e.g., 15000)"  data-error-id="option_fee_amount_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            </div>
                                    <span class="error mt-2" id="option_fee_amount_error"></span>

        </div>
    @endif
@endif

<!-- Lease Purchase -->
@if ($offered_financing === 'Lease Purchase')

    <div class="alert alert-warning mt-3 p-2 small">
        <strong>Note:</strong> 📌 If this transaction is structured as a Lease-Purchase, the Buyer’s Broker Purchase Fee
        applies upon successful closing of the sale. Under Broker Compensation, use the Lease Fee or Lease-Option Fee
        sections only if there is no guaranteed purchase
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Buyer's Desired Offering Price for Lease Purchase:<span
                class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the price the Buyer is offering if the purchase is completed at the end of the lease term.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">

            <span class="input-group-text-seller">$</span>

            <input type="text" wire:model="lease_purchase_price" class="form-control has-icon"
                placeholder="Enter offering price for lease purchase (e.g., 800000)" required
                 data-error-id="lease_purchase_price_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)"
                >
        </div>

                                <span class="error mt-2" id="lease_purchase_price_error"></span>

    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Specific Terms Proposed for Lease Purchase:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter any proposed terms for the lease purchase (e.g., rent credits apply toward purchase).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">

            <input type="text" wire:model="lease_purchase_terms" class="form-control has-icon"
                data-icon="fa-solid fa-file-alt"
                placeholder="Enter specific terms proposed (e.g., Rent credits apply toward purchase, Option to buy after 12 months)" required>
        </div>
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Proposed Duration of Lease (Months):<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the number of months the Buyer wishes to lease before purchasing.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="lease_purchase_duration"class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days"
                placeholder="Enter the proposed lease duration in months (e.g., 6)" required>

        </div>
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Monthly Payment Buyer is Offering:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the monthly lease amount the Buyer is offering.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>

            <input type="text" wire:model="lease_purchase_payment" class="form-control has-icon"
                placeholder="Enter monthly payment amount (e.g., 5000)" required
                
                 data-error-id="lease_purchase_payment_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)"
                >
        </div>

                                <span class="error mt-2" id="lease_purchase_payment_error"></span>

    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Conditions or Requirements for Lease Purchase:<span
                class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter any requirements (e.g., Buyer must secure financing by lease end).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">

            <input type="text" wire:model="lease_purchase_conditions" class="form-control has-icon"
                data-icon="fa-solid fa-file-alt"
                placeholder="Enter any conditions or requirements (e.g., Property must appraise at agreed value, Seller to cover closing costs)" required>
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Offered Option Fee:</label>
        <div class="input-cover">
            <select wire:model="lease_purchase_option_fee" class="form-control has-icon"
                data-icon="fa-solid fa-file-invoice-dollar">
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </div>
    </div>

    @if ($lease_purchase_option_fee === 'Yes')
        <div class="form-group">
            <label class="fw-bold">Offered Option Fee:</label>
            <div class="input-cover">
                <span class="input-group-text-seller">$</span>

                <input type="number" wire:model="lease_purchase_option_fee_amount" class="form-control has-icon"
                    placeholder="Enter option fee amount (e.g., 15000)">
            </div>
        </div>
    @endif
@endif

<!-- Cryptocurrency Option -->
@if ($offered_financing === 'Cryptocurrency')
    <div class="form-group">
        <label class="fw-bold">Offered Cryptocurrency:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the type of cryptocurrency the Buyer is offering (e.g., Bitcoin, Ethereum).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="cryptocurrency_type" class="form-control has-icon"
                data-icon="fa-solid fa-money-bill-wave"
                placeholder="Enter type of cryptocurrency (e.g., Bitcoin, Ethereum)" 
               
                required>
        </div>


        
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Percentage of Purchase Price to be Paid with Cryptocurrency:<span
                class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the percentage of the total purchase price to be paid in cryptocurrency.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="crypto_percentage" class="form-control has-icon"
                placeholder="Enter percentage to be paid with cryptocurrency (e.g., 50)"  
                data-error-id="crypto_percentage_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)"
                 required>
            <span class="input-group-text-seller">%</span>
        </div>

                <span class="error mt-2" id="crypto_percentage_error"></span>

    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Percentage of Purchase Price to be Paid with Cash:<span
                class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the percentage to be paid in cash. The two percentages should total 100%">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="cash_percentage_crypto" class="form-control has-icon"
                placeholder="Enter percentage to be paid with cash (e.g., 50)"
                 data-error-id="cash_percentage_crypto_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)"
                 required>

            <span class="input-group-text-seller">%</span>
        </div>
                <span class="error mt-2" id="cash_percentage_crypto_error"></span>

    </div>
@endif

<!-- NFT Option -->
@if ($offered_financing === 'Non-Fungible Token (NFT)')
    <div class="form-group mt-3">
        <label class="fw-bold">Offered Non-Fungible Token (NFT):<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the type of NFT the Buyer is offering (e.g., tokenized real estate, digital artwork).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="nft_description" class="form-control has-icon"
                data-icon="fa-solid fa-money-bill-wave"
                placeholder="Enter NFT type (e.g., Tokenized Real Estate, Digital Artwork)" required>
        </div>
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Percentage of Purchase Price to be Paid with NFT:<span
                class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the percentage of the purchase price to be paid in NFTs.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="nft_percentage" class="form-control has-icon"
                placeholder="Enter percentage to be paid with NFT (e.g., 40)" required
                
                 data-error-id="nft_percentage_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)"
                
                >
            <span class="input-group-text-seller">%</span>

        </div>
                        <span class="error mt-2" id="nft_percentage_error"></span>

    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Percentage of Purchase Price to be Paid with Cash:<span
                class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the percentage to be paid in cash. The two percentages should total 100%.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="cash_percentage_nft" class="form-control has-icon"
                placeholder="Enter percentage to be paid with cash (e.g., 60)" required
                
                data-error-id="cryptocurrency_type_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)"
                >
            <span class="input-group-text-seller">%</span>

        </div>
                        <span class="error mt-2" id="cryptocurrency_type_error"></span>

    </div>
@endif
