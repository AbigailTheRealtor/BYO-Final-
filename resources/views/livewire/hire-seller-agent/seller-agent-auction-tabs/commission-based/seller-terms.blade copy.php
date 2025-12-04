<!-- Section Heading -->
<h3> Sale Terms </h3>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>💰 Enter the desired sale price, preferred closing date, accepted financing or currency types, and
                any applicable special sale provisions.
            </strong>
        </div>
    </div>
</div>

<div class="form-group">
    <label class="fw-bold"> Special Sale Provision: <span class="text-danger">*</span></label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select any special sale conditions that apply to the property. If the applicable sale provision isn’t listed, select “Other” to describe it.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    <div class="input-cover">
        <select wire:model="sale_provision" id="sale_provision" class="form-control has-icon select2-multiple"
            data-icon="fa-solid fa-screwdriver-wrench input-icon2" multiple required>
            @foreach ($seller_property as $row_pt)
                <option value="{{ $row_pt['name'] }}" title="{{ $row_pt['description'] }}">
                    {{ $row_pt['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2" id="sale_provision_error"></span>
</div>

<!-- Other Special Sale Provision Input -->
@if (in_array('Other', $sale_provision))
    <div class="form-group mt-3">
        {{-- <label class="fw-bold">Other Special Sale Provision:</label> --}}
        <div class="input-cover">
            <input type="text" wire:model="sale_provision_other" class="form-control has-icon"
                data-icon="fa-solid fa-screwdriver-wrench"
                placeholder="Enter special sale provision (e.g., Divorce Sale, Third-Party Approval)">
        </div>
    </div>
@endif

<!-- Assignment Contract Flow -->
@if (in_array('Assignment Contract', $sale_provision))
    <!-- Buyer Under Contract Question -->
    <div class="form-group mt-3">
        <label class="fw-bold">Seller Under Contract for Assignment:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="The Seller is assigning their contractual rights to another Buyer (commonly used in wholesaling).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
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
                        placeholder="Enter percentage of contract assignment value (e.g., 2)">
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
        </div> --}}





            <div class="form-group mt-2">
            <label class="fw-bold">Assignment Fee to Broker:</label>

            <div class="input-group">
                <!-- Select for type -->
                <select wire:model="assignment_fee_type" class="form-select" style="max-width: 100px;">
                    <option value="percent">%</option>
                    <option value="flat">$</option>
                </select>

                <!-- Single input -->
                <input type="number" step="any" wire:model.lazy="assignment_fee_amount" class="form-control"
                    placeholder="{{ $assignment_fee_type === 'percent'
                        ? 'Enter percentage of contract assignment value (e.g., 2)'
                        : 'Enter flat fee (e.g., 2500)' }}">

                <!-- Suffix -->
                <span class="input-group-text">
                    {{ $assignment_fee_type === 'percent' ? '%' : '$' }}
                </span>
            </div>

        </div>
    @endif

    <!-- If Buyer is NOT Under Contract (No) -->
    @if ($sale_provision_assignment === 'No')
        <div class="alert alert-warning mt-3 p-2 small">
            <strong>⚠️ Note: </strong>If the Seller is under contract to purchase a property and intends to assign their
            purchase rights to another Buyer, they are considered the Seller of that contract.

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
        </div> --}}

        <!-- If Buyer Wants to Sell Contract -->
        {{-- @if ($buyer_sell_contract === 'Yes')
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
    <label class="fw-bold">Target Closing Date:<span class="text-danger">*</span></label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the Seller’s ideal closing timeframe.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

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
@if ($property_type != 'Vacant Land')

    <div class="form-group">
        <label class="fw-bold">Occupant Type:<span class="text-danger">*</span></label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate whether the property is currently occupied by the Owner, a Tenant, or is Vacant.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="occupant_status" class="form-control has-icon"
                data-icon="fa-solid fa-screwdriver-wrench" required>
                <option value="">Select</option>
                @foreach ($occupant_types_seller as $row_pt)
                    <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
                @endforeach
            </select>
        </div>
    </div>
@endif
@if ($occupant_status === 'Tenant')
    <div class="form-group">
        <label class="fw-bold">Occupied Until:</label>
        <div class="input-cover">
            <input type="date" wire:model="occupant_tenant" class="form-control has-icon"
                data-icon="fa-regular fa-clock" placeholder="Enter other Occupied until">
        </div>
    </div>
@endif

<!-- Desired Sale Price -->
<div>
    <div class="form-group">
        <label class="fw-bold">Desired Sale Price:<span class="text-danger">*</span></label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the amount the Seller would like to receive for the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">

            <span class="input-group-text-seller">$</span>

            <input type="number" wire:model="maximum_budget" class="form-control has-icon"
                placeholder="Enter desired sale price (e.g., 500000)" required>
        </div>
        <span class="error mt-2" id="number_of_unit_error"></span>
    </div>
</div>

<!-- Offered Financing/Currency -->
<div class="form-group mt-3">
    <label class="fw-bold">Offered Financing/Currency: <span class="text-danger">*</span></label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the types of financing or currency the Seller is open to accepting — such as cash, conventional loans, seller financing, lease options, or alternative methods like cryptocurrency or exchange/trade.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    {{-- <div class="input-cover">
        <select wire:model="offered_financing" id="offered_financing" class="form-control has-icon select2-multiple"
            data-icon="fa-solid fa-money-bill-wave input-icon2" multiple required>
            @foreach (['Assumable', 'Cash', 'Conventional', 'Cryptocurrency', 'Exchange/Trade', 'FHA', 'Jumbo', 'Lease Option', 'Lease Purchase', 'Non-Fungible Token (NFT)', 'No-Doc', 'Non-QM', 'Seller Financing', 'USDA', 'VA', 'Other'] as $option)
                <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2" id="offered_financing_error"></span> --}}

    <div class="input-cover">
        <select wire:model="offered_financing" id="offered_financing" class="form-control has-icon select2-multiple"
            data-icon="fa-solid fa-money-bill-wave input-icon2" multiple required>
            @foreach ($financing_options_seller as $option)
                <option value="{{ $option['name'] }}" title="{{ $option['description'] }}">
                    {{ $option['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2" id="offered_financing_error"></span>

</div>
@if (in_array('Other', $offered_financing))
    {{-- @if ($offered_financing === 'Other') --}}
    <div>
        <div class="form-group">
            {{-- <label class="fw-bold">Other Financing/Currency:</label> --}}
            <div class="input-cover">
                <input type="text" wire:model="other_financing" class="form-control has-icon"
                    data-icon="fa-solid fa-money-bill-wave"
                    placeholder="Enter type of financing or currency offered (e.g., Gold Bullion, Stock Transfer, Private Investment Agreement)">
            </div>
        </div>
    </div>
@endif

<!-- Cash Option -->
{{-- @if (in_array('Cash', $offered_financing))

    <div>
        <div class="form-group">
            <label class="fw-bold">Buyer's Budget:</label>
            <div class="input-cover">
                <input type="number" wire:model="cash_budget" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="Enter buyer’s budget amount (e.g., $450,000)">
            </div>
            <span class="error mt-2" id="number_of_unit_error"></span>
        </div>
    </div>
@endif --}}

<!-- Conventional, FHA, Jumbo, VA, No-Doc, Non-QM, USDA -->
{{-- @if (in_array($offered_financing, ['Conventional', 'FHA', 'Jumbo', 'VA', 'No-Doc', 'Non-QM', 'USDA'])) --}}

{{-- @if (in_array('Conventional', $offered_financing) || in_array('FHA', $offered_financing) || in_array('Jumbo', $offered_financing) || in_array('VA', $offered_financing) || in_array('No-Doc', $offered_financing) || in_array('Non-QM', $offered_financing) || in_array('USDA', $offered_financing))

    <div class="form-group mt-3">
        <label class="fw-bold">Buyer Pre-Approved for a Loan:</label>
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
            <label class="fw-bold">Buyer Pre-Approval Amount:</label>
            <div class="input-cover">
                <input type="number" wire:model="pre_approval_amount" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="Enter pre-approved loan amount (e.g., $400,000)">
            </div>
        </div>
    @endif
@endif --}}

<!-- Seller Financing -->

@if (in_array('Seller Financing', $offered_financing))

    <div class="form-group">
        <label class="fw-bold"> Purchase Price:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the purchase price being offered.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>

            <input type="number" wire:model="purchase_price" class="form-control has-icon"
                placeholder="Enter total purchase price (e.g., 500000)">
        </div>
    </div>

    {{-- <div class="form-group mt-3">
        <label class="fw-bold"> Down Payment:</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown"
                    aria-haspopup="true" aria-expanded="false" style="height: 100%;">
                    {{ $down_payment_type }}
                </button>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="#"
                        wire:click.prevent="setDownPaymentType('%')">%(Percentage)</a>
                    <a class="dropdown-item" href="#" wire:click.prevent="setDownPaymentType('$')">$(Flat
                        Fee)</a>

                </div>
            </div>
            <input type="number" wire:model="down_payment_amount" class="form-control"
                placeholder="{{ $down_payment_type === '%' ? 'Enter down payment amount (e.g., 20)' : 'Enter down payment amount (e.g., 100000)' }}">
        </div>
    </div> --}}

    <div class="form-group mt-3">
        <label class="fw-bold"> Down Payment:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the down payment amount required from the Buyer.">
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
                        <a class="dropdown-item" href="#" wire:click.prevent="setDownPaymentType('$')">$
                            (Flat
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
                    placeholder="Enter down payment amount (e.g., 20)">
                <div class="input-group-append">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false" style="height: 100%;">
                        {{ $down_payment_type }}
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="#" wire:click.prevent="setDownPaymentType('$')">$
                            (Flat
                            Fee)</a>
                        <a class="dropdown-item" href="#" wire:click.prevent="setDownPaymentType('%')">%
                            (Percentage)</a>
                    </div>
                </div>
            @endif
        </div>

    </div>

    <div class="form-group mt-3">
        <label class="fw-bold"> Down Payment:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the down payment amount required from the Buyer.">
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
                <input type="number" wire:model="seller_down_payment_amount" class="form-control"
                    placeholder="Enter seller financing amount (e.g., 400000)">
            @else
                <!-- Show input first for % -->
                <input type="number" wire:model="seller_down_payment_amount" class="form-control"
                    placeholder="Enter seller financing percentage (e.g., 80)">
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

    </div>
    <div class="alert alert-warning mt-3 p-2 small">
        <strong> Note: </strong> Select $ or % to switch between entering a dollar amount or a percentage.
    </div>

    <div class="form-group">
        <label class="fw-bold"> Interest Rate:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the interest rate charged on the financed amount.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="number" wire:model="interest_rate" class="form-control has-icon percentage-value-set"
                placeholder="Enter interest rate (e.g., 6.5)">
            <span class="input-group-text-seller">%</span>

        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold"> Loan Duration:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the term of the loan in years.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="number" wire:model="loan_duration" class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days" placeholder="Enter loan duration in years (e.g., 30)">
        </div>
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Prepayment Penalty:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate if a penalty applies for early payoff and, if so, enter the amount.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="prepayment_penalty" class="form-control has-icon"
                data-icon="fa-solid fa-exclamation-circle">
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

                <input type="number" wire:model="prepayment_penalty_amount" class="form-control has-icon"
                    placeholder="Enter prepayment penalty amount (e.g., 5000)">
            </div>
        </div>
    @endif

    <div class="form-group mt-3">
        <label class="fw-bold">Balloon Payment:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate if a balloon payment is required and, if so, enter the amount and due date.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="balloon_payment" class="form-control has-icon"
                data-icon="fa-solid fa-money-bill-wave">
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
                <span class="input-group-text-seller">$</span>

                <input type="number" wire:model="balloon_payment_amount" class="form-control has-icon"
                    placeholder="Enter balloon payment amount (e.g., 100000)">
            </div>
        </div>

        <div class="form-group">
            <label class="fw-bold">Balloon Payment Due Date:</label>
            <div class="input-cover">
                <input type="text" wire:model="balloon_payment_date" class="form-control has-icon"
                    data-icon="fa-regular fa-calendar-days" placeholder="Enter ballon payment date (e.g., 5 Years)">

            </div>
        </div>
    @endif

@endif

@if (in_array('Assumable', $offered_financing))
    <div class="form-group">
        <label class="fw-bold">Offered Assumable Terms:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the loan terms being offered for assumption, including remaining balance, interest rate, term type (fixed/variable), and remaining duration.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="text" wire:model="assumable_terms" class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days"
                placeholder="Enter assumable terms (e.g., $250,000 remaining at 4.25% fixed for 20 years)">
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold"> Interest Rate of Assumable Loan:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the current interest rate on the loan (e.g., 5).">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="number" wire:model="max_assumable_rate" class="form-control has-icon percentage-value-set"
                placeholder="Enter interest rate (e.g., 5)">
            <span class="input-group-text-seller">%</span>

        </div>
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold"> Monthly Payment (Principal & Interest) for Assumable Loan:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the monthly principal and interest payment. Exclude taxes, insurance, and HOA unless included in the mortgage.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <span class="input-group-text-seller">$</span>

            <input type="number" wire:model="max_monthly_payment" class="form-control has-icon"
                placeholder="Enter monthly payment (e.g., 2000)">
        </div>
    </div>
    <div class="form-group mt-3">
        <label class="fw-bold"> Outstanding Balance on Existing Loan:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the remaining balance the Buyer would assume.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <span class="input-group-text-seller">$</span>

            <input type="number" wire:model="outstanding_balance" class="form-control has-icon"
                placeholder="Enter outstanding balance on existing loan  (e.g., 100000)">
        </div>
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Down Payment to Cover the Gap Between Price and Loan:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the amount the Buyer must pay upfront to cover the difference between the asking price and the loan balance.">
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
                        <a class="dropdown-item" href="#" wire:click.prevent="setGapPaymentType('$')">$
                            (Flat
                            Fee)</a>
                        <a class="dropdown-item" href="#" wire:click.prevent="setGapPaymentType('%')">%
                            (Percentage)</a>
                    </div>
                </div>
                <input type="number" wire:model="seller_financing_amount" class="form-control"
                    placeholder="Enter the down payment needed to cover the difference between the asking price and the assumable loan balance (e.g., 50000)">
            @else
                <!-- Show input first for % -->
                <input type="number" wire:model="seller_financing_amount" class="form-control"
                    placeholder="Enter the down payment needed to cover the difference between the asking price and the assumable loan balance (e.g., 50000)">
                <div class="input-group-append">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false" style="height: 100%;">
                        {{ $gap_payment_type }}
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="#" wire:click.prevent="setGapPaymentType('$')">$
                            (Flat
                            Fee)</a>
                        <a class="dropdown-item" href="#" wire:click.prevent="setGapPaymentType('%')">%
                            (Percentage)</a>
                    </div>
                </div>
            @endif
        </div>

    </div>

@endif
<!-- Exchange/Trade Option -->

@if (in_array('Exchange/Trade', $offered_financing))

    <div class="form-group mt-3">
        <label class="fw-bold">Acceptable Exchange Item:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type of item the Seller is willing to accept (e.g., another home, artwork, boat, jewelry, motorhome, vehicle, or other).">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="exchange_item" class="form-control has-icon" data-icon="fa-solid fa-exchange-alt">
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
            <label class="fw-bold"> Exchange Item:</label>

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Buyer is using an alternative financing method not listed above. Specify in the additional details field.">
                <i class="fa-solid fa-circle-info"></i>
            </span>

            <div class="input-cover">
                <input type="text" wire:model="other_exchange_item" class="form-control has-icon"
                    data-icon="fa-solid fa-exchange-alt"
                    placeholder="Enter exchange item (e.g., Private Jet, Yacht, Luxury RV)">
            </div>
        </div>
    @endif

    <div class="form-group">
        <label class="fw-bold">Estimated Value of Exchange/Trade Item:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the estimated fair market value of the trade item.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <span class="input-group-text-seller">$</span>

            <input type="number" wire:model="exchange_item_value"class="form-control has-icon"
                placeholder="Enter estimated item value (e.g., 75000)">
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Acceptable Condition of Exchange/Trade Item:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the minimum acceptable condition of the trade item (e.g., new, good, fair).">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="exchange_item_condition" class="form-control has-icon"
                data-icon="fa-solid fa-clipboard-check">
                <option value="">Select</option>
                <option value="New">New</option>
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
        <label class="fw-bold">Additional Cash Seller Will Require:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the amount of additional cash the Seller expects if the trade item’s value is less than the asking price.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <span class="input-group-text-seller">$</span>

            <input type="number" wire:model="additional_cash" class="form-control has-icon"
                placeholder="Enter additional cash required (e.g., 25000)">
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Value of Exchange/Trade Item Determined:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Explain how the trade item’s value will be verified (e.g., licensed appraisal, online valuation, mutual agreement).">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="text" wire:model="value_determination" class="form-control has-icon"
                data-icon="fa-solid fa-exchange-alt"
                placeholder="Enter how the value of the exchange/trade item should be determined (e.g., Licensed Appraisal, Online Valuation, Mutual Agreement)">
        </div>
    </div>
@endif

<!-- Lease Option -->

@if (in_array('Lease Option', $offered_financing))

    <div class="form-group">
        <label class="fw-bold">Seller's Desired Offering Price for Lease Option:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the price the Seller is willing to accept if the Buyer exercises the option to purchase.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <span class="input-group-text-seller">$</span>

            <input type="number" wire:model="lease_option_price" class="form-control has-icon"
                placeholder="Enter offering price for lease option (e.g., 500000)">
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Specific Terms Proposed for Lease Option:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Describe any proposed terms for the lease option (e.g., inspections allowed during lease term).">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="text" wire:model="lease_option_terms" class="form-control has-icon"
                data-icon="fas fa-clipboard-list"
                placeholder="Enter any conditions or requirements for the lease option (e.g., Buyer may conduct inspections during lease term, Seller to maintain property)">
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Proposed Duration of Lease (Months):</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the number of months the lease will last before the purchase option may be exercised.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="number" wire:model="lease_option_duration" class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days" placeholder="Enter lease duration in months (e.g., 12)">

        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Monthly Payment the Seller Will Accept:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the monthly lease payment the Seller will accept.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <span class="input-group-text-seller">$</span>

            <input type="number" wire:model="lease_option_payment" class="form-control has-icon"
                placeholder="Enter monthly payment amount (e.g., 2500)">
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Conditions or Requirements for Lease Option:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Specify any additional requirements or limitations (e.g., option exercisable after 12 months).">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="text" wire:model="lease_option_conditions" class="form-control has-icon"
                data-icon="fa-solid fa-circle-exclamation"
                placeholder="Enter any conditions or requirements for the lease option (e.g., Option may be exercised after 12 months, Non-refundable option fee due at signing, Buyer responsible for all maintenance during lease term)">
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Offered Option Fee:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate if a non-refundable option fee is required, and if so, enter the fee amount.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="has_option_fee" class="form-control has-icon"
                data-icon="fa-solid fa-file-invoice-dollar">
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </div>
    </div>

    {{-- @if ($has_option_fee === 'Yes')
        <div class="form-group">
            <label class="fw-bold">Offered Option Fee:</label>
            <div class="input-cover">

                <span class="input-group-text-seller">$</span>

                <input type="number" wire:model="option_fee_amount" class="form-control has-icon"
                    placeholder="Enter option fee amount (e.g., 10000)">
            </div>
        </div>
    @endif --}}
@endif

<!-- Lease Purchase -->

@if (in_array('Lease Purchase', $offered_financing))
    <div class="alert alert-warning mt-3 p-2 small">
        📌 If this transaction is structured as a Lease-Purchase, the Seller’s Broker Purchase Fee applies upon
        successful closing of the sale. Under Broker Compensation, use the Lease Fee or Lease-Option Fee sections only
        if there is no guaranteed purchase.
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Seller's Desired Offering Price for Lease Purchase:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the price the Seller is offering if the Buyer completes the purchase at the end of the lease term.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <span class="input-group-text-seller">$</span>

            <input type="number" wire:model="lease_purchase_price" class="form-control has-icon"
                placeholder="Enter offering price for lease purchase (e.g., 800000)">
        </div>
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Specific Terms Proposed for Lease Purchase:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="List any proposed terms for the lease purchase (e.g., rent credits apply toward purchase).">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input wire:model="lease_purchase_terms" class="form-control has-icon" data-icon="fas fa-clipboard-list"
                placeholder="Enter specific terms proposed for the lease purchase (e.g., Rent credits apply toward purchase, Option to buy after 12 months)">
            </input>
        </div>
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Proposed Duration of Lease (Months):</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the number of months the lease will last before the Buyer is expected to purchase.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="number" wire:model="lease_purchase_duration"class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days" placeholder="Enter lease duration in months (e.g., 6)">

        </div>
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Monthly Payment the Seller Will Accept:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the monthly lease amount the Seller will accept.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <span class="input-group-text-seller">$</span>

            <input type="number" wire:model="lease_purchase_payment" class="form-control has-icon"
                placeholder="Enter monthly payment amount (e.g., 5000)">
        </div>
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Conditions or Requirements for Lease Purchase:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Include any requirements (e.g., Buyer must secure financing by lease end).">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input wire:model="lease_purchase_conditions" class="form-control has-icon"
                data-icon="fa-solid fa-circle-exclamation"
                placeholder="Enter any conditions or requirements for the lease purchase (e.g., Buyer must secure financing by end of lease term, Non-refundable deposit required at lease signing)"></input>
        </div>
    </div>

    {{-- <div class="form-group">
        <label class="fw-bold">Offered Option Fee:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate if a non-refundable option fee is required, and if so, enter the fee amount.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

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
    @endif --}}
@endif

<!-- Cryptocurrency Option -->

@if (in_array('Cryptocurrency', $offered_financing))
    <div class="form-group">
        <label class="fw-bold">Acceptable Cryptocurrency:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the type(s) of cryptocurrency the Seller is willing to accept (e.g., Bitcoin, Ethereum).">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="text" wire:model="cryptocurrency_type" class="form-control has-icon"
                data-icon="fa-solid fa-money-bill-wave"
                placeholder="Enter type of cryptocurrency (e.g., Bitcoin, Ethereum)">
        </div>
    </div>
    <div class="form-group mt-3">
        <label class="fw-bold">Percentage of Purchase Price to be Paid with Cryptocurrency:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the portion of the purchase price, as a percentage, that the Buyer is expected to pay in cryptocurrency.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="number" wire:model="crypto_percentage" class="form-control has-icon percentage-value-set"
                placeholder="Enter percentage of price paid with crypto (e.g., 50)">

            <span class="input-group-text-seller">%</span>

        </div>
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Percentage of Purchase Price to be Paid with Cash:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the portion of the purchase price, as a percentage, to be paid in cash or traditional currency. The two percentages should total 100%.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="number" wire:model="cash_percentage_crypto" class="form-control has-icon percentage-value-set"
                placeholder="Percentage of purchase price to be Paid with cash (e.g., 50)">

            <span class="input-group-text-seller">%</span>

        </div>
    </div>
@endif

<!-- NFT Option -->

@if (in_array('Non-Fungible Token (NFT)', $offered_financing))
    <div class="form-group mt-3">
        <label class="fw-bold">Acceptable Non-Fungible Token (NFT):</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the type of NFT the Seller is willing to accept (e.g., tokenized real estate, digital artwork).">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="text" wire:model="nft_description" class="form-control has-icon"
                data-icon="fa-solid fa-money-bill-wave"
                placeholder="Enter the type of NFT the Seller is willing to accept (e.g., tokenized real estate, digital artwork).">
        </div>
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Percentage of Purchase Price Seller Will Accept as NFT:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the percentage of the purchase price the Seller will accept in NFTs.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="number" wire:model="nft_percentage" class="form-control has-icon percentage-value-set"
                placeholder="Enter percentage of purchase price acceptable as NFT (e.g., 40)">
            <span class="input-group-text-seller">%</span>

        </div>
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Percentage of Purchase Price Seller Will Accept as Cash:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the percentage to be paid in cash. The two percentages should total 100%.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="number" wire:model="cash_percentage_nft" class="form-control has-icon percentage-value-set"
                placeholder="Enter percentage of purchase price acceptable as cash (e.g., 60)">
            <span class="input-group-text-seller">%</span>

        </div>
    </div>
@endif
