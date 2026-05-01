@php
    // Ensure offered_financing is always an array to prevent in_array() errors
    $offered_financing = is_array($offered_financing ?? []) ? ($offered_financing ?? []) : (array)($offered_financing ?? []);
    $sale_provision = is_array($sale_provision ?? []) ? ($sale_provision ?? []) : (array)($sale_provision ?? []);

    // Occupant type options
    $occupant_types_seller = [['name' => 'Owner'], ['name' => 'Tenant'], ['name' => 'Vacant']];

    // Special sale provision options
    $seller_property = [
        ['name' => 'Assignment Contract', 'description' => 'The Seller is assigning their contractual rights to another Buyer (commonly used in wholesaling).'],
        ['name' => 'Auction', 'description' => 'The property will be sold through a public bidding process.'],
        ['name' => 'Bank Owned/REO', 'description' => 'The property has been foreclosed on and is now owned by the bank (Real Estate Owned).'],
        ['name' => 'Government Owned', 'description' => 'The property is owned by a government entity (e.g., HUD, VA).'],
        ['name' => 'Probate Listing', 'description' => "The property is part of a deceased owner's estate and requires probate court approval to sell."],
        ['name' => 'Short Sale', 'description' => 'The Seller owes more on the mortgage than the property\'s value and is seeking lender approval to sell for less.'],
        ['name' => 'None', 'description' => 'No special sale provisions apply — standard sale.'],
        ['name' => 'Other', 'description' => 'A special provision not listed; user should specify details.'],
    ];

    // Offered financing/currency options
    $financing_options_seller = [
        ['name' => 'Assumable', 'description' => 'Allows an existing mortgage to be assumed by a Buyer, subject to lender approval.'],
        ['name' => 'Cash', 'description' => 'Purchase is completed without financing, with the full price paid in cash.'],
        ['name' => 'Conventional', 'description' => 'Uses a traditional mortgage that meets standard underwriting guidelines.'],
        ['name' => 'FHA', 'description' => 'Uses a loan backed by the Federal Housing Administration.'],
        ['name' => 'Jumbo', 'description' => 'Uses a loan that exceeds conforming loan limits.'],
        ['name' => 'VA', 'description' => 'Uses a VA-backed loan available to eligible veterans and active-duty service members.'],
        ['name' => 'No-Doc', 'description' => 'Uses a loan requiring limited or no income documentation.'],
        ['name' => 'Non-QM', 'description' => 'Uses a Non-Qualified Mortgage that allows alternative income verification methods.'],
        ['name' => 'USDA', 'description' => 'Uses a USDA-backed loan for eligible rural properties and qualifying buyers.'],
        ['name' => 'Cryptocurrency', 'description' => 'Uses digital currency (e.g., Bitcoin or Ethereum) as full or partial consideration.'],
        ['name' => 'Exchange/Trade', 'description' => 'Includes another asset as part of the purchase consideration in a trade.'],
        ['name' => 'Lease Option', 'description' => 'Allows the property to be leased with an option to purchase later under pre-agreed terms.'],
        ['name' => 'Lease Purchase', 'description' => 'Allows the property to be leased now with a commitment to purchase later.'],
        ['name' => 'Non-Fungible Token (NFT)', 'description' => 'Uses a verified digital asset as full or partial consideration, subject to Seller approval.'],
        ['name' => 'Seller Financing', 'description' => 'Purchase price is financed in whole or in part directly by the Seller.'],
        ['name' => 'Other', 'description' => 'Uses an alternative financing or consideration method not listed above.'],
    ];
@endphp
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

    <div class="input-cover" wire:ignore>
        <i class="input-icon fa-solid fa-screwdriver-wrench input-icon2"></i>
        <select id="sale_provision" class="form-control has-icon select2-multiple"
            data-icon="fa-solid fa-screwdriver-wrench input-icon2" multiple required>
            @foreach ($seller_property as $row_pt)
                <option value="{{ $row_pt['name'] }}" title="{{ $row_pt['description'] }}" {{ in_array($row_pt['name'], $sale_provision ?? []) ? 'selected' : '' }}>
                    {{ $row_pt['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2" id="sale_provision_error"></span>
</div>

<!-- Other Special Sale Provision Input -->
<div id="seller-provision-other-section" wire:ignore.self style="display: {{ in_array('Other', $sale_provision ?? []) ? 'block' : 'none' }}">
    <div class="form-group mt-3">
        {{-- <label class="fw-bold">Other Special Sale Provision:</label> --}}
        <div class="input-cover">
            <input type="text" wire:model="sale_provision_other" class="form-control has-icon"
                data-icon="fa-solid fa-screwdriver-wrench"
                placeholder="Enter special sale provision (e.g., Divorce Sale, Third-Party Approval)">
        </div>
    </div>
</div>

<!-- Assignment Contract Flow -->
<div id="seller-provision-assignment-section" wire:ignore.self style="display: {{ in_array('Assignment Contract', $sale_provision ?? []) ? 'block' : 'none' }}">
    <!-- Buyer Under Contract Question -->
    <div class="form-group mt-3">
        <label class="fw-bold">Seller Under Contract for Assignment:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="The Seller is assigning their contractual rights to another Buyer (commonly used in wholesaling). If the Seller is under contract to purchase a property and intends to assign their purchase rights to another Buyer, they are considered the Seller of that contract. If the Seller is looking to purchase an assignment contract, they are considered the Buyer of that contract. In that case, please switch to a Buyer listing instead.">
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

    <!-- If Seller is Under Contract (Yes) -->
    @if ($sale_provision_assignment === 'Yes')
        <div class="form-group mt-3">
            <label class="fw-bold">Assignment Contract Fee to Broker:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Select how the assignment fee will be structured - either as a flat dollar amount or as a percentage of the contract assignment value.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover mt-2">
                <select wire:model="assignment_fee_type" class="form-control has-icon"
                    data-icon="fa-solid fa-file-invoice-dollar">
                    <option value="">Select</option>
                    <option value="$">Flat Fee</option>
                    <option value="%">Percentage of Contract Assignment Value</option>
                </select>
            </div>

            <!-- Dynamic Inputs Based on Selection -->
            <div class="mt-3">
                @if ($assignment_fee_type === '$')
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="text" wire:model="assignment_fee_amount" class="form-control"
                            placeholder="Enter flat fee amount (e.g., 2500)"
                            data-error-id="assignment_fee_amount_error"
                            oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
                    </div>
                    <span class="error mt-2" id="assignment_fee_amount_error"></span>
                @elseif($assignment_fee_type === '%')
                    <div class="input-group">
                        <input type="number" wire:model="assignment_fee_amount" class="form-control"
                            placeholder="Enter percentage of contract assignment value (e.g., 3)">
                        <span class="input-group-text">%</span>
                    </div>
                @endif
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
</div>

<div class="form-group mt-4">
    <label class="fw-bold">Target Closing Timeframe:<span class="text-danger">*</span></label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the Seller’s preferred closing timeframe. This helps Buyers and their Agents understand the Seller’s desired timing and evaluate whether it can be met.">
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
            title="Select who currently occupies the property. If Tenant or Owner is selected, enter the Occupied Until date. If Vacant is selected, no date is required.">
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

<!-- Desired Sale Price / Bidding Period Pricing -->
@if ($auction_type === 'Bidding Period')
<div class="financing-section-header mt-4 mb-3 pb-2 border-bottom">
    <h5 class="fw-bold text-primary mb-0">
        <i class="fa-solid fa-gavel me-2"></i>Bidding Period Pricing
    </h5>
</div>
<div class="form-group">
    <label class="fw-bold">Desired Sale Price:<span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the amount the Seller would like to receive for the property.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <span class="input-group-text-seller">$</span>
        <input type="text" wire:model="maximum_budget" id="seller_desired_sale_price" class="form-control"
            placeholder="Enter desired sale price (e.g., 500000)" data-error-id="maximum_budget_error"
            oninput="validateInput(this);" onblur="reformatNumber(this)" onpaste="handlePaste(event)" required>
    </div>
    <span class="error mt-2" id="maximum_budget_error"></span>
</div>
<div class="form-group mt-3">
    <label class="fw-bold">Starting Price / Opening Bid:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the minimum price at which bidding will open for this property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <span class="input-group-text-seller">$</span>
        <input type="text" wire:model="starting_price" class="form-control"
            placeholder="Enter opening bid amount (e.g., 400000)"
            oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
    </div>
</div>
<div class="form-group mt-3">
    <label class="fw-bold">Reserve Price:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the minimum price the Seller will accept. The property will not be sold below this amount. Reserve Price is optional.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <span class="input-group-text-seller">$</span>
        <input type="text" wire:model="reserve_price" class="form-control"
            placeholder="Enter reserve price (e.g., 450000) — optional"
            oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
    </div>
</div>
<div class="form-group mt-3">
    <label class="fw-bold">Buy Now Price:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the price at which a Buyer can immediately purchase the property and end the bidding period. Defaults to Desired Sale Price if left unchanged.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <span class="input-group-text-seller">$</span>
        <input type="text" wire:model="buy_now_price" id="seller_buy_now_price" class="form-control"
            placeholder="Enter buy now price (e.g., 500000)"
            oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
    </div>
</div>
@else
<div>
    <div class="form-group">
        <label class="fw-bold">Desired Sale Price:<span class="text-danger">*</span></label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the amount the Seller would like to receive for the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">

            <span class="input-group-text-seller">$</span>

            <input type="text" wire:model="maximum_budget" class="form-control"
                placeholder="Enter desired sale price (e.g., 500000)" data-error-id="maximum_budget_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)" required>
        </div>
        <span class="error mt-2" id="maximum_budget_error"></span>
    </div>
</div>
@endif

<!-- Offered Financing/Currency -->
<div class="form-group mt-3">
    <label class="fw-bold">Offered Financing/Currency:</label>

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

    <div class="input-cover" wire:ignore wire:key="offered-financing-cover">
        <i class="input-icon fa-solid fa-money-bill-wave input-icon2"></i>
        <select id="offered_financing" class="form-control has-icon select2-multiple"
            multiple>
            @foreach ($financing_options_seller as $option)
                <option value="{{ $option['name'] }}" title="{{ $option['description'] }}" {{ in_array($option['name'], $offered_financing ?? []) ? 'selected' : '' }}>
                    {{ $option['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2" id="offered_financing_error"></span>

</div>
<div id="seller-financing-other-section" wire:ignore.self style="display: {{ in_array('Other', $offered_financing ?? []) ? 'block' : 'none' }}">
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
</div>

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


<div id="seller-financing-assumable-section" wire:ignore.self style="display: {{ in_array('Assumable', $offered_financing ?? []) ? 'block' : 'none' }}">
    <div class="financing-section-header mt-4 mb-3 pb-2 border-bottom">
        <h5 class="fw-bold text-primary mb-0">
            <i class="fa-solid fa-arrow-right-arrow-left me-2"></i>Assumable
        </h5>
    </div>
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

    <!-- Type of Loan -->
    <div class="form-group mt-3">
        <label class="fw-bold">Type of Loan:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select the loan type associated with the existing assumable loan.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <select wire:model="assumable_loan_type" class="form-control has-icon"
                data-icon="fa-solid fa-file-contract">
                <option value="">Select</option>
                <option value="FHA">FHA</option>
                <option value="VA">VA</option>
                <option value="USDA">USDA</option>
            </select>
        </div>
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Interest Rate of Assumable Loan:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the current interest rate on the loan (e.g., 5).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-group">
            <input type="text" wire:model="max_assumable_rate" class="form-control"
                placeholder="Enter interest rate (e.g., 5)" data-error-id="max_assumable_rate_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            <span class="input-group-text">%</span>
        </div>
        <span class="error mt-2" id="max_assumable_rate_error"></span>
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Monthly Payment (Principal & Interest) for Assumable Loan:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the monthly principal and interest payment. Exclude taxes, insurance, and HOA unless included in the mortgage.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>
            <input type="text" wire:model="max_monthly_payment" class="form-control has-icon"
                placeholder="Enter monthly payment (e.g., 2000)" data-error-id="max_monthly_payment_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
        </div>
        <span class="error mt-2" id="max_monthly_payment_error"></span>
    </div>

    <!-- Monthly Escrow / Impounds -->
    <div class="form-group mt-3">
        <label class="fw-bold">Monthly Escrow / Impounds: (Not Assumable, Informational Only)
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the Seller's current monthly escrow amount for property taxes and homeowners insurance. Buyer's escrow will be recalculated after closing and may differ significantly. This field is provided for reference only and is not part of the assumable loan terms.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>
            <input type="text" wire:model="assumable_monthly_escrow" class="form-control has-icon"
                placeholder="Enter monthly escrow amount for taxes/insurance (e.g., 450)"
                data-error-id="assumable_monthly_escrow_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
        </div>
        <span class="error mt-2" id="assumable_monthly_escrow_error"></span>
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Outstanding Balance on Existing Loan:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the remaining balance the Buyer would assume.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>
            <input type="text" wire:model="outstanding_balance" class="form-control has-icon"
                placeholder="Enter outstanding balance on existing loan (e.g., 100000)"
                data-error-id="outstanding_balance_error" oninput="validateInput(this)" onblur="reformatNumber(this)"
                onpaste="handlePaste(event)">
        </div>
        <span class="error mt-2" id="outstanding_balance_error"></span>
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Down Payment to Cover the Gap Between Price and Loan:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the amount the Buyer would need to pay to cover the difference between the purchase price and the loan amount.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-group">
            <select wire:model="gap_payment_type" class="form-select" style="max-width: 100px;">
                <option value="flat">$</option>
                <option value="percent">%</option>
            </select>
            <input type="text" step="any" wire:model.lazy="gap_payment_amount" class="form-control"
                placeholder="{{ ($gap_payment_type ?? 'flat') === 'percent'
                    ? 'Enter down payment percentage (e.g., 20)'
                    : 'Enter down payment amount (e.g., 50000)' }}"
                data-error-id="gap_payment_amount_error" oninput="validateInput(this)"
                onblur="reformatNumber(this)" onpaste="handlePaste(event)">
        </div>
        <span class="error mt-2" id="gap_payment_amount_error"></span>
    </div>

    <!-- Loan Term Remaining -->
    <div class="form-group mt-3">
        <label class="fw-bold">Loan Term Remaining:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the remaining loan term. This helps Buyers understand how long payments will continue before the loan is fully paid off.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <input type="text" wire:model="assumable_loan_term_remaining" class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days"
                placeholder="Enter remaining loan term (e.g., 25 Years)">
        </div>
    </div>

    <!-- Date Loan Originated -->
    <div class="form-group mt-3">
        <label class="fw-bold">Date Loan Originated:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the date the original loan was originated. This helps Buyers understand the loan's current position within its amortization schedule.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <input type="text" wire:model="assumable_loan_origination_date" class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days"
                placeholder="Enter original loan start date (e.g., Jan 2020)">
        </div>
    </div>

    <!-- Loan Servicer / Lender -->
    <div class="form-group mt-3">
        <label class="fw-bold">Loan Servicer / Lender:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the name of the company currently servicing the loan. Buyers will work with this servicer to complete the loan assumption process.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <input type="text" wire:model="assumable_loan_servicer" class="form-control has-icon"
                data-icon="fa-solid fa-building-columns"
                placeholder="Enter loan servicer or lender name (e.g., Mr. Cooper, Wells Fargo)">
        </div>
    </div>

    <!-- Assumption Fee -->
    <div class="form-group mt-3">
        <label class="fw-bold">Assumption Fee:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Some lenders charge a processing or transfer fee for assumable loans. Enter the amount, if applicable.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-group">
            <select wire:model="assumable_fee_type" class="form-select" style="max-width: 80px;">
                <option value="$">$</option>
                <option value="%">%</option>
            </select>
            @if (($assumable_fee_type ?? '$') === '$')
                <span class="input-group-text">$</span>
            @endif
            <input type="text" wire:model="assumable_fee_amount" class="form-control"
                placeholder="{{ ($assumable_fee_type ?? '$') === '%' ? 'Enter assumption fee percentage (e.g., 1)' : 'Enter assumption fee amount (e.g., 1,000)' }}"
                data-error-id="assumable_fee_amount_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            @if (($assumable_fee_type ?? '$') === '%')
                <span class="input-group-text">%</span>
            @endif
        </div>
        <span class="error mt-2" id="assumable_fee_amount_error"></span>
    </div>

    <!-- Occupancy Requirement -->
    <div class="form-group mt-3">
        <label class="fw-bold">Occupancy Requirement:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select whether the loan requires the Buyer to occupy the property as their primary residence (common for FHA and VA loans).">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <select wire:model="assumable_occupancy_requirement" class="form-control has-icon"
                data-icon="fa-solid fa-house-user">
                <option value="">Select</option>
                <option value="Primary Residence Required for 1 Year (FHA)">Primary Residence Required for 1 Year (FHA)</option>
                <option value="Primary Residence – Intend to Occupy within 60 Days (VA)">Primary Residence – Intend to Occupy within 60 Days (VA)</option>
                <option value="No Occupancy Restriction">No Occupancy Restriction</option>
                <option value="Other">Other</option>
            </select>
        </div>
    </div>

    @if (($assumable_occupancy_requirement ?? '') === 'Other')
    <div class="form-group mt-2">
        <div class="input-cover">
            <input type="text" wire:model="assumable_occupancy_other" class="form-control has-icon"
                data-icon="fa-solid fa-house-user"
                placeholder="Enter occupancy requirement (e.g., Must occupy within 90 days of closing)">
        </div>
    </div>
    @endif
</div>
<!-- Cryptocurrency Option -->

<div id="seller-financing-crypto-section" wire:ignore.self style="display: {{ in_array('Cryptocurrency', $offered_financing ?? []) ? 'block' : 'none' }}">
    <div class="financing-section-header mt-4 mb-3 pb-2 border-bottom">
        <h5 class="fw-bold text-primary mb-0">
            <i class="fa-brands fa-bitcoin me-2"></i>Cryptocurrency
        </h5>
    </div>
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
            <input type="text" wire:model="crypto_percentage" class="form-control has-icon percentage-value-set"
                placeholder="Enter percentage of price paid with crypto (e.g., 50)"
                data-error-id="crypto_percentage_error" oninput="validateInput(this)" onblur="reformatNumber(this)"
                onpaste="handlePaste(event)">

            <span class="input-group-text-seller">%</span>

        </div>

        <span class="error mt-2" id="crypto_percentage_error"></span>

    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Percentage of Purchase Price to be Paid with Cash:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the portion of the purchase price, as a percentage, to be paid in cash or traditional currency. The two percentages should total 100%.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-group">
            <input type="text" wire:model="cash_percentage_crypto" class="form-control"
                placeholder="Enter percentage to be paid with cash (e.g., 50)"
                data-error-id="cash_percentage_crypto_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            <span class="input-group-text">%</span>
        </div>
        <span class="error mt-2" id="cash_percentage_crypto_error"></span>
    </div>

    <!-- Exchange / Conversion Method -->
    <div class="form-group mt-3">
        <label class="fw-bold">Exchange / Conversion Method:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Specify how the cryptocurrency will be converted to U.S. dollars at closing. Most transactions use the spot exchange rate at a set time (e.g., date of transfer or settlement).">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <input type="text" wire:model="crypto_exchange_method" class="form-control has-icon"
                data-icon="fa-solid fa-right-left"
                placeholder="Enter how crypto will be valued (e.g., Spot price at closing, Coinbase exchange rate)">
        </div>
    </div>

    <!-- Custodian / Wallet for Transfer -->
    <div class="form-group mt-3">
        <label class="fw-bold">Custodian / Wallet for Transfer:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the wallet, exchange, platform, or escrow service where cryptocurrency will be transferred. Examples include Coinbase, Binance, an escrow wallet address, or a crypto title/escrow provider such as Propy Title. This ensures both parties agree on the transfer method.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <input type="text" wire:model="crypto_custodian_wallet" class="form-control has-icon"
                data-icon="fa-solid fa-wallet"
                placeholder="Enter wallet, exchange, or escrow service (e.g., Coinbase, Escrow Wallet, Propy Title)">
        </div>
    </div>

    <!-- Transaction Fees Responsibility -->
    <div class="form-group mt-3">
        <label class="fw-bold">Transaction Fees Responsibility:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select who will be responsible for blockchain transaction fees (miner/gas fees) associated with the crypto transfer.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <select wire:model="crypto_transaction_fees" class="form-control has-icon"
                data-icon="fa-solid fa-coins">
                <option value="">Select</option>
                <option value="Buyer">Buyer</option>
                <option value="Seller">Seller</option>
                <option value="Split">Split</option>
            </select>
        </div>
    </div>

    <!-- Timing of Transfer -->
    <div class="form-group mt-3">
        <label class="fw-bold">Timing of Transfer:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select when the cryptocurrency transfer will occur. Timing is important due to price volatility.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <select wire:model="crypto_transfer_timing" class="form-control has-icon"
                data-icon="fa-solid fa-clock">
                <option value="">Select</option>
                <option value="At Contract Signing">At Contract Signing</option>
                <option value="At Closing">At Closing</option>
                <option value="Other">Other</option>
            </select>
        </div>
    </div>

    @if (($crypto_transfer_timing ?? '') === 'Other')
    <div class="form-group mt-2">
        <div class="input-cover">
            <input type="text" wire:model="crypto_transfer_timing_other" class="form-control has-icon"
                data-icon="fa-solid fa-clock"
                placeholder="Enter timing of transfer (e.g., within 48 hours of contract acceptance, partial transfer at inspection period)">
        </div>
    </div>
    @endif
</div>
<!-- Exchange/Trade Option -->

<div id="seller-financing-exchange-section" wire:ignore.self style="display: {{ in_array('Exchange/Trade', $offered_financing ?? []) ? 'block' : 'none' }}">
    <div class="financing-section-header mt-4 mb-3 pb-2 border-bottom">
        <h5 class="fw-bold text-primary mb-0">
            <i class="fa-solid fa-right-left me-2"></i>Exchange/Trade
        </h5>
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Acceptable Exchange Item:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type of item the Seller is willing to accept (e.g., another home, artwork, boat, jewelry, motorhome, vehicle, or other).">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        @php
            $eiArr = is_array($exchange_item) ? $exchange_item : (is_string($exchange_item) && trim($exchange_item) !== '' ? (json_decode($exchange_item, true) ?? [$exchange_item]) : []);
        @endphp
        <div class="input-cover" wire:ignore>
            <select id="exchange_item" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-right-left" data-selected='@json($eiArr)' multiple>
                @foreach (['Another Home', 'Artwork', 'Boat', 'Jewelry', 'Motorhome', 'Vehicle', 'Other'] as $eiOpt)
                    <option value="{{ $eiOpt }}" {{ in_array($eiOpt, $eiArr) ? 'selected' : '' }}>{{ $eiOpt }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if (is_array($exchange_item) ? in_array('Other', $exchange_item) : $exchange_item === 'Other')
        <div class="form-group">
            <div class="input-cover">
                <input type="text" wire:model="other_exchange_item" class="form-control has-icon"
                    data-icon="fa-solid fa-right-left"
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

            <input type="text" wire:model="exchange_item_value"class="form-control has-icon"
                placeholder="Enter estimated item value (e.g., 75000)" data-error-id="exchange_item_value_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
        </div>

        <span class="error mt-2" id="exchange_item_value_error"></span>

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

            <input type="text" wire:model="additional_cash" class="form-control has-icon"
                placeholder="Enter additional cash required (e.g., 25000)" data-error-id="additional_cash_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
        </div>

        <span class="error mt-2" id="additional_cash_error"></span>

    </div>

    <div class="form-group">
        <label class="fw-bold">Value of Exchange/Trade Item Determined:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Describe how the value of the exchange or trade item will be determined.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="text" wire:model="value_determination" class="form-control has-icon"
                data-icon="fa-solid fa-right-left"
                placeholder="Enter how the value of the exchange/trade item should be determined (e.g., Licensed Appraisal, Online Valuation, Mutual Agreement)">
        </div>
    </div>

    <!-- Transfer Method / Logistics -->
    <div class="form-group mt-3">
        <label class="fw-bold">Transfer Method / Logistics:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Specify the method for transferring ownership of the exchange/trade item (e.g., title transfer for a vehicle, bill of sale for equipment, delivery at closing).">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <input type="text" wire:model="exchange_transfer_method" class="form-control has-icon"
                data-icon="fa-solid fa-truck"
                placeholder="Enter how the exchange/trade item will be delivered or transferred (e.g., Title transfer, Bill of Sale, Delivery at closing)">
        </div>
    </div>

    <!-- Liens / Encumbrances Disclosure -->
    <div class="form-group mt-3">
        <label class="fw-bold">Liens / Encumbrances Disclosure:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Indicate whether the exchange or trade item has any liens or encumbrances that would need to be resolved before closing.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <select wire:model="exchange_liens" class="form-control has-icon"
                data-icon="fa-solid fa-file-contract">
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </div>
    </div>

    @if (($exchange_liens ?? '') === 'Yes')
    <div class="form-group mt-2">
        <div class="input-cover">
            <input type="text" wire:model="exchange_liens_details" class="form-control has-icon"
                data-icon="fa-solid fa-file-contract"
                placeholder="Enter lien/encumbrance details (e.g., Auto loan balance, UCC filing)">
        </div>
    </div>
    @endif

    <!-- Inspection / Verification Rights -->
    <div class="form-group mt-3">
        <label class="fw-bold">Inspection / Verification Rights:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select whether the party receiving the exchange or trade item may inspect or verify the item's condition or value before closing.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <select wire:model="exchange_inspection_rights" class="form-control has-icon"
                data-icon="fa-solid fa-search">
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </div>
    </div>
</div>

<!-- Lease Option -->

<div id="seller-financing-leaseoption-section" wire:ignore.self style="display: {{ in_array('Lease Option', $offered_financing ?? []) ? 'block' : 'none' }}">
    <div class="financing-section-header mt-4 mb-3 pb-2 border-bottom">
        <h5 class="fw-bold text-primary mb-0">
            <i class="fa-solid fa-key me-2"></i>Lease Option
        </h5>
    </div>
    <!-- 1. Seller's Desired Offering Price for Lease Option -->
    <div class="form-group">
        <label class="fw-bold">Seller's Desired Offering Price for Lease Option:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the price the Seller is willing to accept if the Buyer exercises the option to purchase.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>
            <input type="text" wire:model="lease_option_price" class="form-control has-icon"
                placeholder="Enter offering price for lease option (e.g., 500000)"
                data-error-id="lease_option_price_error" oninput="validateInput(this)" onblur="reformatNumber(this)"
                onpaste="handlePaste(event)">
        </div>
        <span class="error mt-2" id="lease_option_price_error"></span>
    </div>

    <!-- 2. Monthly Payment the Seller Will Accept -->
    <div class="form-group mt-3">
        <label class="fw-bold">Monthly Payment the Seller Will Accept:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the monthly lease payment during the lease term before the purchase option may be exercised.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>
            <input type="text" wire:model="lease_option_payment" class="form-control has-icon"
                placeholder="Enter monthly payment amount (e.g., 2500)" data-error-id="lease_option_payment_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
        </div>
        <span class="error mt-2" id="lease_option_payment_error"></span>
    </div>

    <!-- 3. Proposed Duration of Lease (Months) -->
    <div class="form-group mt-3">
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

    <!-- 4. Offered Option Fee -->
    <div class="form-group mt-3">
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

    @if (($has_option_fee ?? '') === 'Yes')
        <div class="form-group mt-2">
            <div class="input-cover">
                <span class="input-group-text-seller">$</span>
                <input type="text" wire:model="option_fee_amount" class="form-control has-icon"
                    placeholder="Enter option fee amount (e.g., 15000)" data-error-id="option_fee_amount_error"
                    oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            </div>
            <span class="error mt-2" id="option_fee_amount_error"></span>
        </div>
    @endif

    <!-- 5. Option Fee Credit Toward Purchase Price -->
    <div class="form-group mt-3">
        <label class="fw-bold">Option Fee Credit Toward Purchase Price:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select whether the option fee (or part of it) will be credited toward the purchase price if the option is exercised.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <select wire:model="lease_option_fee_credit" class="form-control has-icon"
                data-icon="fa-solid fa-hand-holding-usd">
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
                <option value="Partial">Partial</option>
            </select>
        </div>
    </div>

    @if (($lease_option_fee_credit ?? '') === 'Partial')
    <div class="form-group mt-2">
        <label class="fw-bold">Percentage of Option Fee Credited Toward Purchase Price:</label>
        <div class="input-group">
            <input type="text" wire:model="lease_option_fee_credit_percentage" class="form-control"
                placeholder="Enter percentage of option fee credited (e.g., 50)"
                data-error-id="lease_option_fee_credit_percentage_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            <span class="input-group-text">%</span>
        </div>
        <span class="error mt-2" id="lease_option_fee_credit_percentage_error"></span>
    </div>
    @endif

    <!-- 6. Conditions or Requirements for Lease Option -->
    <div class="form-group mt-3">
        <label class="fw-bold">Conditions or Requirements for Lease Option:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Specify any additional requirements or limitations (e.g., option exercisable after 12 months).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="lease_option_conditions" class="form-control has-icon"
                data-icon="fa-solid fa-circle-exclamation"
                placeholder="Enter any conditions or requirements for the lease option (e.g., Option may be exercised after 12 months)">
        </div>
    </div>

    <!-- 7. Specific Terms Proposed for Lease Option -->
    <div class="form-group mt-3">
        <label class="fw-bold">Specific Terms Proposed for Lease Option:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Describe any proposed terms for the lease option (e.g., inspections allowed during lease term).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="lease_option_terms" class="form-control has-icon"
                data-icon="fa-solid fa-clipboard-list"
                placeholder="Enter specific terms for the lease option (e.g., Buyer may conduct inspections during lease term)">
        </div>
    </div>

    <!-- 8. Maintenance / Repair Responsibility -->
    <div class="form-group mt-3">
        <label class="fw-bold">Maintenance / Repair Responsibility:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select who is responsible for property maintenance and repairs during the lease-option term.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <select wire:model="lease_option_maintenance" class="form-control has-icon"
                data-icon="fa-solid fa-screwdriver-wrench">
                <option value="">Select</option>
                <option value="Seller">Seller</option>
                <option value="Tenant-Buyer">Tenant-Buyer</option>
                <option value="Shared">Shared</option>
            </select>
        </div>
    </div>

    <!-- 9. Extension Terms -->
    <div class="form-group mt-3">
        <label class="fw-bold">Extension Terms:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter whether the lease option may be extended, and under what terms or costs.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <input type="text" wire:model="lease_option_extension_terms" class="form-control has-icon"
                data-icon="fa-solid fa-calendar-plus"
                placeholder="Enter extension terms (e.g., Tenant-Buyer may extend for 6 months with additional $5,000 fee)">
        </div>
    </div>
</div>

<!-- Lease Purchase -->

<div id="seller-financing-leasepurchase-section" wire:ignore.self style="display: {{ in_array('Lease Purchase', $offered_financing ?? []) ? 'block' : 'none' }}">
    <div class="financing-section-header mt-4 mb-3 pb-2 border-bottom">
        <h5 class="fw-bold text-primary mb-0">
            <i class="fa-solid fa-file-signature me-2"></i>Lease Purchase
        </h5>
    </div>
    <div class="alert alert-warning mt-3 p-2 small">
        📌 If this transaction is structured as a Lease-Purchase, the Seller’s Broker Purchase Fee applies upon
        successful closing of the sale. Under Broker Compensation, use the Lease Fee or Lease-Option Fee sections only
        if there is no guaranteed purchase.
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Seller's Desired Offering Price for Lease Purchase:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the price the Seller is willing to accept if the Buyer completes the purchase at the end of the lease term.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <span class="input-group-text-seller">$</span>

            <input type="text" wire:model="lease_purchase_price" class="form-control has-icon"
                placeholder="Enter offering price for lease purchase (e.g., 800000)"
                data-error-id="lease_purchase_price_error" oninput="validateInput(this)"
                onblur="reformatNumber(this)" onpaste="handlePaste(event)">
        </div>

        <span class="error mt-2" id="lease_purchase_price_error"></span>

    </div>

    <!-- 2. Monthly Payment the Seller Will Accept -->
    <div class="form-group mt-3">
        <label class="fw-bold">Monthly Payment the Seller Will Accept:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the monthly lease payment during the lease-purchase term before the purchase is completed.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>
            <input type="text" wire:model="lease_purchase_payment" class="form-control has-icon"
                placeholder="Enter monthly payment amount (e.g., 5000)" data-error-id="lease_purchase_payment_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
        </div>
        <span class="error mt-2" id="lease_purchase_payment_error"></span>
    </div>

    <!-- 3. Proposed Duration of Lease (Months) -->
    <div class="form-group mt-3">
        <label class="fw-bold">Proposed Duration of Lease (Months):</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the number of months the lease will last before the Buyer is expected to purchase.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="lease_purchase_duration" class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days" placeholder="Enter lease duration in months (e.g., 6)">
        </div>
    </div>

    <!-- 4. Rent Credit Toward Purchase Price -->
    <div class="form-group mt-3">
        <label class="fw-bold">Rent Credit Toward Purchase Price:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select whether any portion of the monthly rent will be credited toward the purchase price, and if yes, enter the amount.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <select wire:model="lease_purchase_rent_credit" class="form-control has-icon"
                data-icon="fa-solid fa-hand-holding-usd">
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
                <option value="Partial">Partial</option>
            </select>
        </div>
    </div>

    @if (($lease_purchase_rent_credit ?? '') === 'Yes' || ($lease_purchase_rent_credit ?? '') === 'Partial')
    <div class="form-group mt-2">
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>
            <input type="text" wire:model="lease_purchase_rent_credit_amount" class="form-control has-icon"
                placeholder="Enter rent credit dollar amount (e.g., 500)"
                data-error-id="lease_purchase_rent_credit_amount_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
        </div>
        <span class="error mt-2" id="lease_purchase_rent_credit_amount_error"></span>
    </div>
    @endif

    <!-- 5. Non-Refundable Deposit / Purchase Deposit -->
    <div class="form-group mt-3">
        <label class="fw-bold">Non-Refundable Deposit / Purchase Deposit:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the deposit amount required upfront for the lease purchase. This is typically non-refundable but may be applied to the purchase price.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>
            <input type="text" wire:model="lease_purchase_deposit" class="form-control has-icon"
                placeholder="Enter deposit amount (e.g., 10000)"
                data-error-id="lease_purchase_deposit_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
        </div>
        <span class="error mt-2" id="lease_purchase_deposit_error"></span>
    </div>

    <!-- 6. Conditions or Requirements for Lease Purchase -->
    <div class="form-group mt-3">
        <label class="fw-bold">Conditions or Requirements for Lease Purchase:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Include any requirements (e.g., Buyer must secure financing by lease end).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input wire:model="lease_purchase_conditions" class="form-control has-icon"
                data-icon="fa-solid fa-circle-exclamation"
                placeholder="Enter any conditions or requirements for the lease purchase (e.g., Buyer must secure financing by end of lease term)">
        </div>
    </div>

    <!-- 7. Specific Terms Proposed for Lease Purchase -->
    <div class="form-group mt-3">
        <label class="fw-bold">Specific Terms Proposed for Lease Purchase:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="List any proposed terms for the lease purchase (e.g., rent credits apply toward purchase).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input wire:model="lease_purchase_terms" class="form-control has-icon" data-icon="fa-solid fa-clipboard-list"
                placeholder="Enter specific terms proposed for the lease purchase (e.g., Rent credits apply toward purchase)">
        </div>
    </div>

    <!-- 8. Maintenance / Repair Responsibility -->
    <div class="form-group mt-3">
        <label class="fw-bold">Maintenance / Repair Responsibility:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select who is responsible for property maintenance and repairs during the lease-purchase term.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <select wire:model="lease_purchase_maintenance" class="form-control has-icon"
                data-icon="fa-solid fa-screwdriver-wrench">
                <option value="">Select</option>
                <option value="Seller">Seller</option>
                <option value="Tenant-Buyer">Tenant-Buyer</option>
                <option value="Shared">Shared</option>
            </select>
        </div>
    </div>

    <!-- 9. Extension Terms -->
    <div class="form-group mt-3">
        <label class="fw-bold">Extension Terms:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter whether the lease purchase may be extended, and under what terms or costs.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <input type="text" wire:model="lease_purchase_extension_terms" class="form-control has-icon"
                data-icon="fa-solid fa-calendar-plus"
                placeholder="Enter extension terms (e.g., Buyer may extend lease purchase for 6 months with additional $5,000 deposit)">
        </div>
    </div>
</div>


<!-- NFT Option -->

<div id="seller-financing-nft-section" wire:ignore.self style="display: {{ in_array('Non-Fungible Token (NFT)', $offered_financing ?? []) ? 'block' : 'none' }}">
    <div class="financing-section-header mt-4 mb-3 pb-2 border-bottom">
        <h5 class="fw-bold text-primary mb-0">
            <i class="fa-solid fa-image me-2"></i>Non-Fungible Token (NFT)
        </h5>
    </div>
    <div class="form-group mt-3">
        <label class="fw-bold">Acceptable Non-Fungible Token (NFT):</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the type of NFT the Seller is willing to accept (e.g., Tokenized Real Estate, Digital Artwork).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="nft_description" class="form-control has-icon"
                data-icon="fa-solid fa-money-bill-wave"
                placeholder="Enter the type of NFT the Seller is willing to accept (e.g., Tokenized Real Estate, Digital Artwork)">
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

    <!-- NFT Valuation Method -->
    <div class="form-group mt-3">
        <label class="fw-bold">NFT Valuation Method:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Specify how the value of the NFT will be calculated at closing to avoid disputes over fluctuating market prices.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <input type="text" wire:model="nft_valuation_method" class="form-control has-icon"
                data-icon="fa-solid fa-chart-line"
                placeholder="Enter how NFT value will be determined (e.g., Floor price on OpenSea, Independent appraisal, Mutual agreement)">
        </div>
    </div>

    <!-- NFT Transfer Method -->
    <div class="form-group mt-3">
        <label class="fw-bold">NFT Transfer Method:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the platform, wallet, or escrow service where the NFT will be transferred. Examples include a crypto wallet (e.g., MetaMask), a marketplace (e.g., OpenSea), or a crypto title/escrow provider (e.g., Propy Title). This ensures both parties agree on the transfer method and can verify ownership.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <input type="text" wire:model="nft_transfer_method" class="form-control has-icon"
                data-icon="fa-solid fa-wallet"
                placeholder="Enter wallet, marketplace, or escrow service for transfer (e.g., MetaMask, OpenSea, Propy Title, Escrow Smart Contract)">
        </div>
    </div>

    <!-- Transaction Fees Responsibility (Gas Fees) -->
    <div class="form-group mt-3">
        <label class="fw-bold">Transaction Fees Responsibility (Gas Fees):
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select who will be responsible for blockchain transaction (gas) fees associated with transferring the NFT.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <select wire:model="nft_gas_fees" class="form-control has-icon"
                data-icon="fa-solid fa-gas-pump">
                <option value="">Select</option>
                <option value="Buyer">Buyer</option>
                <option value="Seller">Seller</option>
                <option value="Split">Split</option>
            </select>
        </div>
    </div>
</div>
<!-- Seller Financing -->

<div id="seller-financing-sellerfinancing-section" wire:ignore.self style="display: {{ in_array('Seller Financing', $offered_financing ?? []) ? 'block' : 'none' }}">
    <div class="financing-section-header mt-4 mb-3 pb-2 border-bottom">
        <h5 class="fw-bold text-primary mb-0">
            <i class="fa-solid fa-handshake me-2"></i>Seller Financing
        </h5>
    </div>

    <div class="form-group">
        <label class="fw-bold"> Purchase Price:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the total price the Seller is seeking for the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>

            <input type="text" wire:model="purchase_price" class="form-control has-icon"
                placeholder="Enter total purchase price (e.g., 500000)" data-error-id="purchase_price_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
        </div>
        <span class="error mt-2" id="purchase_price_error"></span>

    </div>

    <div class="form-group mt-2">
        <label class="fw-bold"> Down Payment:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the minimum down payment amount the Seller will accept from the Buyer.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-group">
            <!-- Select for type -->
            <select wire:model="down_payment_type" class="form-select" style="max-width: 100px;">
                <option value="%">%</option>
                <option value="$">$</option>
            </select>

            <!-- Single input -->
            <input type="text" step="any" wire:model.lazy="down_payment_amount" class="form-control"
                placeholder="{{ $down_payment_type === '%'
                    ? 'Enter down payment amount (e.g., 20)'
                    : 'Enter down payment amount (e.g., 100000)' }}"
                data-error-id="down_payment_amount_error" oninput="validateInput(this)" onblur="reformatNumber(this)"
                onpaste="handlePaste(event)">

            <!-- Suffix (only show % when percentage is selected) -->
            @if($down_payment_type === '%')
            <span class="input-group-text">%</span>
            @endif
        </div>
        <span class="error mt-2" id="down_payment_amount_error"></span>

    </div>

    <div class="form-group mt-2">
        <label class="fw-bold"> Seller Financing:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the portion of the purchase price the Seller is willing to finance for the Buyer.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-group">
            <!-- Select for type -->
            <select wire:model="seller_financing_type" class="form-select" style="max-width: 100px;">
                <option value="%">%</option>
                <option value="$">$</option>
            </select>

            <!-- Single input -->
            <input type="text" step="any" wire:model.lazy="seller_down_payment_amount" class="form-control"
                placeholder="{{ $seller_financing_type === '%'
                    ? 'Enter seller financing amount (e.g., 80)'
                    : 'Enter seller financing amount (e.g., 400000)' }}"
                data-error-id="seller_down_payment_amount_error" oninput="validateInput(this)"
                onblur="reformatNumber(this)" onpaste="handlePaste(event)">

            <!-- Suffix (only show % when percentage is selected) -->
            @if($seller_financing_type === '%')
            <span class="input-group-text">%</span>
            @endif
        </div>
        <span class="error mt-2" id="seller_down_payment_amount_error"></span>

    </div>

    <div class="form-group">
        <label class="fw-bold"> Interest Rate:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the interest rate the Seller will charge on the seller-financed amount.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="number" wire:model="interest_rate" class="form-control has-icon percentage-value-set"
                placeholder="Enter interest rate (e.g., 6.5)">
            <span class="input-group-text-seller">%</span>

        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold"> Loan Duration (Years):</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the term of the loan in years.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="text" wire:model="loan_duration" class="form-control has-icon"
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

                <input type="text" wire:model="prepayment_penalty_amount" class="form-control has-icon"
                    placeholder="Enter prepayment penalty amount (e.g., 5000)"
                    data-error-id="prepayment_penalty_amount_error" oninput="validateInput(this)"
                    onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            </div>

            <span class="error mt-2" id="prepayment_penalty_amount_error"></span>

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
        <div class="form-group mt-2">
            <div class="input-cover">
                <span class="input-group-text-seller">$</span>

                <input type="text" wire:model="balloon_payment_amount" class="form-control has-icon"
                    placeholder="Enter balloon payment amount (e.g., 100000)"
                    data-error-id="balloon_payment_amount_error" oninput="validateInput(this)"
                    onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            </div>

            <span class="error mt-2" id="balloon_payment_amount_error"></span>

        </div>

        <div class="form-group mt-3">
            <label class="fw-bold">Balloon Payment Due Date:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter the date when the remaining loan balance must be paid in full if the financing includes a balloon payment.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <input type="text" wire:model="balloon_payment_date" class="form-control has-icon"
                    data-icon="fa-regular fa-calendar-days" placeholder="Enter balloon payment date (e.g., 5 Years)">

            </div>
        </div>
    @endif

    <!-- Amortization Type -->
    <div class="form-group mt-3">
        <label class="fw-bold">Amortization Type:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select whether payments will fully amortize the loan over the term, be interest-only, or follow another structure.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <select wire:model="seller_amortization_type" class="form-control has-icon"
                data-icon="fa-solid fa-chart-line">
                <option value="">Select</option>
                <option value="Fully Amortizing">Fully Amortizing</option>
                <option value="Interest-Only">Interest-Only</option>
                <option value="Other">Other</option>
            </select>
        </div>
    </div>

    @if (($seller_amortization_type ?? '') === 'Other')
    <div class="form-group mt-2">
        <div class="input-cover">
            <input type="text" wire:model="seller_amortization_other" class="form-control has-icon"
                data-icon="fa-solid fa-chart-line"
                placeholder="Enter custom amortization type (e.g., Hybrid, Graduated Payments, Step-Up Structure)">
        </div>
    </div>
    @endif

    <!-- Payment Frequency -->
    <div class="form-group mt-3">
        <label class="fw-bold">Payment Frequency:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select how often payments will be made to the Seller.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <select wire:model="seller_payment_frequency" class="form-control has-icon"
                data-icon="fa-solid fa-calendar-check">
                <option value="">Select</option>
                <option value="Monthly">Monthly</option>
                <option value="Bi-Weekly">Bi-Weekly</option>
                <option value="Quarterly">Quarterly</option>
                <option value="Annually">Annually</option>
                <option value="Other">Other</option>
            </select>
        </div>
    </div>

    @if (($seller_payment_frequency ?? '') === 'Other')
    <div class="form-group mt-2">
        <div class="input-cover">
            <input type="text" wire:model="seller_payment_frequency_other" class="form-control has-icon"
                data-icon="fa-solid fa-calendar-check"
                placeholder="Enter custom payment schedule (e.g., Semi-Annual, Lump Sum at Harvest)">
        </div>
    </div>
    @endif

    <!-- Late Payment Fee -->
    <div class="form-group mt-3">
        <label class="fw-bold">Late Payment Fee:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the late payment fee amount and when it applies.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <input type="text" wire:model="seller_late_fee_amount" class="form-control has-icon"
                data-icon="fa-solid fa-clock"
                placeholder="Enter late fee and when it applies (e.g., $100 after 10 days late, or 5% of payment after 15 days)">
        </div>
    </div>
</div>


{{-- ============================================================
     NEW SELLER PURCHASE TERMS (19 fields)
     ============================================================ --}}

<div class="financing-section-header mt-5 mb-3 pb-2 border-bottom">
    <h5 class="fw-bold text-primary mb-0">
        <i class="fa-solid fa-file-contract me-2"></i>Seller's Purchase Terms
    </h5>
</div>

{{-- 1. Initial Deposit Requested --}}
<div class="form-group mt-3">
    <label class="fw-bold">Initial Deposit Requested:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the earnest money deposit amount the Seller expects from the Buyer at contract signing.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <span class="input-group-text-seller">$</span>
        <input type="text" wire:model="initial_deposit_requested" class="form-control"
            placeholder="Enter initial deposit amount (e.g., 5000)"
            data-error-id="initial_deposit_requested_error"
            oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
    </div>
    <span class="error mt-2" id="initial_deposit_requested_error"></span>
</div>

{{-- 2. Initial Deposit Timeframe --}}
<div class="form-group mt-3">
    <label class="fw-bold">Initial Deposit Timeframe:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select how many days after contract execution the initial deposit must be received.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="initial_deposit_timeframe" class="form-control has-icon"
            data-icon="fa-regular fa-calendar-days">
            <option value="">Select</option>
            <option value="Within 1 Day">Within 1 Day</option>
            <option value="Within 3 Days">Within 3 Days</option>
            <option value="Within 5 Days">Within 5 Days</option>
            <option value="Within 7 Days">Within 7 Days</option>
            <option value="Within 10 Days">Within 10 Days</option>
            <option value="Within 14 Days">Within 14 Days</option>
            <option value="At Closing">At Closing</option>
            <option value="Other">Other</option>
        </select>
    </div>
</div>
@if ($initial_deposit_timeframe === 'Other')
<div class="form-group">
    <div class="input-cover">
        <input type="text" wire:model="initial_deposit_timeframe_other" class="form-control has-icon"
            data-icon="fa-regular fa-calendar-days"
            placeholder="Enter initial deposit timeframe (e.g., Within 21 Days)">
    </div>
</div>
@endif

{{-- 3. Additional Deposit Requested --}}
<div class="form-group mt-3">
    <label class="fw-bold">Additional Deposit Requested:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter any secondary deposit amount required after the inspection or financing contingency period.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <span class="input-group-text-seller">$</span>
        <input type="text" wire:model="additional_deposit_requested" class="form-control"
            placeholder="Enter additional deposit amount (e.g., 10000)"
            data-error-id="additional_deposit_requested_error"
            oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
    </div>
    <span class="error mt-2" id="additional_deposit_requested_error"></span>
</div>

{{-- 4. Additional Deposit Timeframe --}}
<div class="form-group mt-3">
    <label class="fw-bold">Additional Deposit Timeframe:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select how many days after the triggering event the additional deposit must be received.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="additional_deposit_timeframe" class="form-control has-icon"
            data-icon="fa-regular fa-calendar-days">
            <option value="">Select</option>
            <option value="Within 1 Day">Within 1 Day</option>
            <option value="Within 3 Days">Within 3 Days</option>
            <option value="Within 5 Days">Within 5 Days</option>
            <option value="Within 7 Days">Within 7 Days</option>
            <option value="Within 10 Days">Within 10 Days</option>
            <option value="Within 14 Days">Within 14 Days</option>
            <option value="At Closing">At Closing</option>
            <option value="Other">Other</option>
        </select>
    </div>
</div>
@if ($additional_deposit_timeframe === 'Other')
<div class="form-group">
    <div class="input-cover">
        <input type="text" wire:model="additional_deposit_timeframe_other" class="form-control has-icon"
            data-icon="fa-regular fa-calendar-days"
            placeholder="Enter additional deposit timeframe (e.g., Within 21 Days)">
    </div>
</div>
@endif

{{-- 5. Escrow Agent Preference --}}
<div class="form-group mt-3">
    <label class="fw-bold">Escrow Agent Preference:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the Seller's preferred escrow company, attorney, or title agent to hold deposits and manage closing.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <input type="text" wire:model="escrow_agent_preference" class="form-control has-icon"
            data-icon="fa-solid fa-building-columns"
            placeholder="Enter preferred escrow agent or company name (e.g., First American Title, Local Attorney)">
    </div>
</div>

{{-- 6. Preferred Inspection Period (Days) --}}
<div class="form-group mt-3">
    <label class="fw-bold">Preferred Inspection Period (Days):
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the number of days the Seller is willing to allow for the Buyer's inspection contingency period.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <input type="number" wire:model="preferred_inspection_period" class="form-control has-icon"
            data-icon="fa-solid fa-magnifying-glass"
            placeholder="Enter number of inspection days (e.g., 10)" min="0">
    </div>
</div>

{{-- 7. Appraisal Contingency Preference --}}
<div class="form-group mt-3">
    <label class="fw-bold">Appraisal Contingency Preference:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether the Seller requires, will accept, or prefers the Buyer to waive the appraisal contingency.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="appraisal_contingency_preference" class="form-control has-icon"
            data-icon="fa-solid fa-scale-balanced">
            <option value="">Select</option>
            <option value="Required">Required</option>
            <option value="Preferred Waived">Preferred Waived</option>
            <option value="Negotiable">Negotiable</option>
            <option value="Not Applicable">Not Applicable</option>
        </select>
    </div>
</div>

{{-- 8. Financing Contingency Preference --}}
<div class="form-group mt-3">
    <label class="fw-bold">Financing Contingency Preference:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether the Seller requires, will accept, or prefers the Buyer to waive the financing contingency.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="financing_contingency_preference" class="form-control has-icon"
            data-icon="fa-solid fa-file-invoice-dollar">
            <option value="">Select</option>
            <option value="Required">Required</option>
            <option value="Preferred Waived">Preferred Waived</option>
            <option value="Negotiable">Negotiable</option>
            <option value="Not Applicable">Not Applicable</option>
        </select>
    </div>
</div>

{{-- 9. Sale of Buyer's Property Contingency --}}
<div class="form-group mt-3">
    <label class="fw-bold">Sale of Buyer's Property Contingency:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether the Seller will accept an offer contingent on the Buyer selling their current property first.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="sale_of_buyer_property_contingency" class="form-control has-icon"
            data-icon="fa-solid fa-house-circle-check">
            <option value="">Select</option>
            <option value="Accepted">Accepted</option>
            <option value="Not Accepted">Not Accepted</option>
            <option value="Negotiable">Negotiable</option>
        </select>
    </div>
</div>

{{-- 10. Seller Contribution / Credit Offered --}}
<div class="form-group mt-3" id="seller-contribution-credit-wrapper">
    <label class="fw-bold">Seller Contribution / Credit Offered:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether the Seller is willing to offer a credit toward the Buyer's closing costs or other expenses.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="seller_contribution_credit_offered" id="seller_contribution_credit_offered"
            class="form-control has-icon" data-icon="fa-solid fa-hand-holding-dollar"
            onchange="document.getElementById('seller-contribution-amount-details-section').style.display = (this.value === 'Yes') ? 'block' : 'none'">
            <option value="">Select</option>
            <option value="Yes">Yes</option>
            <option value="No">No</option>
        </select>
    </div>
</div>

{{-- 11. Seller Contribution Amount / Details (conditional) --}}
<div id="seller-contribution-amount-details-section" wire:ignore.self
    style="display: {{ ($seller_contribution_credit_offered ?? '') === 'Yes' ? 'block' : 'none' }}">
    <div class="form-group mt-2">
        <div class="input-cover">
            <input type="text" wire:model="seller_contribution_amount_details" class="form-control has-icon"
                data-icon="fa-solid fa-hand-holding-dollar"
                placeholder="Enter contribution amount and details (e.g., $5,000 toward buyer closing costs)">
        </div>
    </div>
</div>

{{-- 12. Possession Preference --}}
<div class="form-group mt-3" id="seller-possession-preference-wrapper">
    <label class="fw-bold">Possession Preference:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select when the Seller prefers to transfer possession of the property to the Buyer.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="possession_preference" id="possession_preference"
            class="form-control has-icon" data-icon="fa-solid fa-key"
            onchange="document.getElementById('seller-possession-details-section').style.display = (this.value === 'Seller Rent Back' || this.value === 'Other') ? 'block' : 'none'">
            <option value="">Select</option>
            <option value="At Closing">At Closing</option>
            <option value="Day After Closing">Day After Closing</option>
            <option value="Seller Rent Back">Seller Rent Back</option>
            <option value="Negotiable">Negotiable</option>
            <option value="Other">Other</option>
        </select>
    </div>
</div>

{{-- 13. Possession Details (conditional) --}}
<div id="seller-possession-details-section" wire:ignore.self
    style="display: {{ in_array($possession_preference ?? '', ['Seller Rent Back', 'Other']) ? 'block' : 'none' }}">
    <div class="form-group mt-2">
        <div class="input-cover">
            <input type="text" wire:model="possession_details" class="form-control has-icon"
                data-icon="fa-solid fa-key"
                placeholder="Enter possession details (e.g., Seller requests 30-day rent back at market rate)">
        </div>
    </div>
</div>

{{-- 14. Included Personal Property --}}
<div class="form-group mt-3">
    <label class="fw-bold">Included Personal Property:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="List any personal property (furniture, appliances, fixtures) the Seller intends to include in the sale.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <input type="text" wire:model="included_personal_property" class="form-control has-icon"
            style="padding-left:40px;"
            data-icon="fa-solid fa-couch"
            placeholder="List items included in the sale (e.g., Refrigerator, washer/dryer, dining room chandelier)">
    </div>
</div>

{{-- 15. Excluded Items --}}
<div class="form-group mt-3">
    <label class="fw-bold">Excluded Items:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="List any fixtures or personal property the Seller intends to remove and exclude from the sale.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <input type="text" wire:model="excluded_items" class="form-control has-icon"
            style="padding-left:40px;"
            data-icon="fa-solid fa-ban"
            placeholder="List items excluded from the sale (e.g., Antique light fixture in dining room, detached storage shed)">
    </div>
</div>

{{-- 16. Home Warranty Offered --}}
<div class="form-group mt-3" id="seller-home-warranty-wrapper">
    <label class="fw-bold">Home Warranty Offered:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether the Seller is willing to provide a home warranty policy for the Buyer.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="home_warranty_offered" id="home_warranty_offered"
            class="form-control has-icon" data-icon="fa-solid fa-shield-halved"
            onchange="document.getElementById('seller-home-warranty-amount-details-section').style.display = (this.value === 'Yes') ? 'block' : 'none'">
            <option value="">Select</option>
            <option value="Yes">Yes</option>
            <option value="No">No</option>
        </select>
    </div>
</div>

{{-- 17. Home Warranty Amount / Details (conditional) --}}
<div id="seller-home-warranty-amount-details-section" wire:ignore.self
    style="display: {{ ($home_warranty_offered ?? '') === 'Yes' ? 'block' : 'none' }}">
    <div class="form-group mt-2">
        <div class="input-cover">
            <input type="text" wire:model="home_warranty_amount_details" class="form-control has-icon"
                data-icon="fa-solid fa-shield-halved"
                placeholder="Enter warranty amount and details (e.g., $500 one-year home warranty through American Home Shield)">
        </div>
    </div>
</div>

{{-- 18. Additional HOA / Association Notes --}}
<div class="form-group mt-3">
    <label class="fw-bold">Additional HOA / Association Notes:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter any supplemental HOA or association information not captured in the structured Tax, Legal, HOA &amp; Disclosures tab — such as special transfer fees, pending rule changes, or other notes the Buyer should be aware of.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <input type="text" wire:model="hoa_condo_association_terms" class="form-control has-icon"
            data-icon="fa-solid fa-building"
            placeholder="Enter any additional HOA notes (e.g., $200 transfer fee, pending special assessment, new rules effective Jan 2026)">
    </div>
</div>

{{-- 19. Additional Seller Sale Terms --}}
<div class="form-group mt-3">
    <label class="fw-bold">Additional Seller Sale Terms:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter any additional terms or conditions the Seller requires that are not covered by the fields above.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <textarea wire:model="additional_seller_sale_terms" class="form-control has-icon" rows="2"
            style="padding-left:40px;"
            data-icon="fa-solid fa-file-lines"
            placeholder="Enter any additional sale terms or special conditions the Seller requires"></textarea>
    </div>
</div>

