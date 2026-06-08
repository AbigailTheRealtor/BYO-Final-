<style>

    /* Ensure input-cover properly contains absolutely positioned icons */
    #offered_financing_wrapper {
        position: relative !important;
        overflow: visible;
    }
</style>
@php
    // Ensure multiselect fields are always arrays to prevent in_array() errors
    // Handles: arrays (pass through), JSON strings (decode), plain strings (wrap in array), null/empty (default to [])
    if (!is_array($offered_financing ?? null)) {
        if (is_string($offered_financing) && !empty($offered_financing)) {
            $decoded = json_decode($offered_financing, true);
            $offered_financing = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [$offered_financing];
        } else {
            $offered_financing = [];
        }
    }
    if (!is_array($sale_provision ?? null)) {
        if (is_string($sale_provision) && !empty($sale_provision)) {
            $decoded = json_decode($sale_provision, true);
            $sale_provision = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [$sale_provision];
        } else {
            $sale_provision = [];
        }
    }
@endphp
<!-- Section Heading -->
<h3>Purchasing Terms</h3>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>💵 Enter the Buyer’s maximum budget, preferred closing date, financing details, acceptable special sale provisions, earnest money, contingencies, seller contributions, possession preference, home warranty, and additional purchase terms. </strong>
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


    <div class="input-cover" wire:ignore>
        <i class="input-icon fa-solid fa-screwdriver-wrench input-icon2"></i>
        <select id="sale_provision" class="form-control has-icon select2-multiple" data-placeholder="Select" multiple>
            <option value=""></option>
            @foreach ($seller_property as $row_pt)
                <option value="{{ $row_pt['name'] }}" title="{{ $row_pt['description'] }}" {{ in_array($row_pt['name'], $sale_provision ?? []) ? 'selected' : '' }}>{{ $row_pt['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2" id="sale_provision_error"></span>
</div>

<!-- Other Special Sale Provision Input -->
<div class="form-group mt-3 sale_provision_other_wrapper" style="{{ (is_array($sale_provision) && in_array('Other', $sale_provision)) ? '' : 'display:none;' }}">
    {{-- Label removed to match Seller flow --}}
    <div class="input-cover">
        <input type="text" wire:model="sale_provision_other" class="form-control has-icon"
            data-icon="fa-solid fa-screwdriver-wrench"
            placeholder="Enter special sale provision (e.g., Divorce Sale, Third-Party Approval)">
    </div>
</div>

<!-- Assignment Contract Flow -->
<div class="assignment-contract-section" x-data="{ visible: {{ (is_array($this->sale_provision) && in_array('Assignment Contract', $this->sale_provision)) ? 'true' : 'false' }} }" x-show="visible" x-on:update-assignment-visibility.window="visible = $event.detail.visible">
    <!-- Buyer Under Contract Question -->
    <div class="form-group mt-3">
        <label class="fw-bold">Buyer Open to Purchasing an Assignment Contract:</label>
        <div class="input-cover">
            <select wire:model.live="sale_provision_assignment" class="form-control has-icon"
                data-icon="fa-solid fa-file-contract">
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </div>
    </div>

    <!-- If Buyer is Under Contract (Yes) -->
    @if ($sale_provision_assignment === 'Yes')
        <div class="form-group mt-3">
            <label class="fw-bold">Assignment Fee to Broker:
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
            <strong>⚠️ Note:</strong> This section is only for Buyers who are open to purchasing an assignment contract.
            If this does not apply, please select a different special sale provision.
        </div>
    @endif
</div>

<div class="form-group mt-4">
    <label class="fw-bold">Target Closing Timeframe: <span class="text-danger">*</span>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the Buyer's preferred closing timeframe. This helps Sellers and their Agents understand the Buyer's desired timing and evaluate whether it aligns with the Seller's expectations.">
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

    <div class="input-cover" id="offered_financing_wrapper" wire:ignore>
        <i class="input-icon fa-solid fa-money-bill-wave input-icon2"></i>
        <select id="offered_financing" class="form-control has-icon select2-multiple" data-placeholder="Select" multiple required>
            <option value=""></option>
            @foreach ($financing_options as $option)
                <option value="{{ $option['name'] }}" title="{{ $option['description'] }}"
                    {{ in_array($option['name'], $offered_financing ?? []) ? 'selected' : '' }}>
                    {{ $option['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2" id="offered_financing_error"></span>

</div>

<div class="other_financing_wrapper" style="{{ (is_array($offered_financing) && in_array('Other', $offered_financing)) ? '' : 'display:none;' }}">
    <div class="form-group">
        <div class="input-cover">
            <input type="text" wire:model="other_financing" class="form-control has-icon"
                data-icon="fa-solid fa-money-bill-wave"
                placeholder="Enter type of financing or currency offered (e.g., Gold Bullion, Stock Transfer, Private Investment Agreement)">
        </div>
    </div>
</div>

<!-- Cash Option - No additional fields needed per requirements -->

<!-- Assumable Financing -->
<div class="financing-assumable-section" x-data="{ visible: {{ (is_array($this->offered_financing) && in_array('Assumable', $this->offered_financing)) ? 'true' : 'false' }}, showDetails: {{ $assumable_interest === 'Yes' ? 'true' : 'false' }} }" x-show="visible" x-on:update-financing-visibility.window="if($event.detail.type === 'Assumable') visible = $event.detail.visible"
>
    <div class="financing-section-header mt-4 mb-3 pb-2 border-bottom">
        <h5 class="fw-bold text-primary mb-0">
            <i class="fa-solid fa-arrow-right-arrow-left me-2"></i>Assumable
        </h5>
    </div>

    <div class="form-group">
        <label class="fw-bold">Interested in Assumable Financing?
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select Yes if you are open to assuming the seller's existing loan. FHA, VA, and USDA loans are the most common assumable loan types, subject to lender approval.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover">
            <select wire:model="assumable_interest" x-on:change="showDetails = ($event.target.value === 'Yes')" class="form-control has-icon" data-icon="fa-solid fa-circle-question">
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </div>
    </div>

    <div x-show="showDetails" x-cloak>
        <div class="form-group mt-3">
            <label class="fw-bold">Maximum Interest Rate You Would Accept:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter the highest interest rate you are willing to accept on an assumable loan (e.g., 5). Leave blank if no limit.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-group">
                <input type="text" wire:model="assumable_max_interest_rate" class="form-control"
                    placeholder="Enter maximum acceptable rate (e.g., 5)"
                    data-error-id="assumable_max_interest_rate_error" oninput="validateInput(this)"
                    onblur="reformatNumber(this)" onpaste="handlePaste(event)">
                <span class="input-group-text">%</span>
            </div>
            <span class="error mt-2" id="assumable_max_interest_rate_error"></span>
        </div>

        <div class="form-group mt-3">
            <label class="fw-bold">Maximum Monthly Payment (P&amp;I) You Would Accept:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter the highest monthly principal and interest payment you are willing to take on. Leave blank if no limit.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <span class="input-group-text-seller">$</span>
                <input type="text" wire:model="assumable_max_monthly_payment" class="form-control has-icon"
                    placeholder="Enter maximum monthly payment (e.g., 2000)"
                    data-error-id="assumable_max_monthly_payment_error" oninput="validateInput(this)"
                    onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            </div>
            <span class="error mt-2" id="assumable_max_monthly_payment_error"></span>
        </div>

        <div class="form-group mt-3">
            <label class="fw-bold">Cash Available to Bridge the Gap:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter the amount of cash you can pay upfront to cover the difference between the purchase price and the assumable loan balance. Leave blank if unknown.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <span class="input-group-text-seller">$</span>
                <input type="text" wire:model="assumable_bridge_gap_cash" class="form-control has-icon"
                    placeholder="Enter bridge gap cash amount (e.g., 50000)"
                    data-error-id="assumable_bridge_gap_cash_error" oninput="validateInput(this)"
                    onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            </div>
            <span class="error mt-2" id="assumable_bridge_gap_cash_error"></span>
        </div>
    </div>

</div>

<!-- Traditional Loan Types - Show header for each selected type -->
@php
    $selectedTraditionalLoans = array_filter(['Conventional', 'FHA', 'Jumbo', 'VA', 'No-Doc', 'Non-QM', 'USDA'], fn($type) => in_array($type, $offered_financing));
@endphp
<div class="financing-traditional-section" x-data="{ visible: {{ (is_array($this->offered_financing) && (count(array_intersect(['Conventional', 'FHA', 'Jumbo', 'VA', 'No-Doc', 'Non-QM', 'USDA'], $this->offered_financing)) > 0)) ? 'true' : 'false' }} }" x-show="visible" x-on:update-financing-visibility.window="if($event.detail.type === 'Traditional') visible = $event.detail.visible">
    <div class="financing-section-header mt-4 mb-3 pb-2 border-bottom">
        <h5 id="traditional-loan-label-h5" class="fw-bold text-primary mb-0">
            <i class="fa-solid fa-file-invoice-dollar me-2"></i>{{ implode(' / ', $selectedTraditionalLoans) }}
        </h5>
    </div>
    <div class="form-group mt-3">
        <label class="fw-bold">Buyer Pre-Approved for a Loan:</label>

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
            <label class="fw-bold">Buyer Pre-Approval Amount:</label>

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the amount listed on the Buyer’s pre-approval letter (e.g., 800000).">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <span class="input-group-text-seller">$</span>

                <input type="text" wire:model="pre_approval_amount" class="form-control has-icon"
                    placeholder="Enter pre-approved loan amount (e.g., 800000)"
                     data-error-id="pre_approval_amount_error" oninput="validateInput(this)"
                onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            </div>
            <span class="error mt-2" id="pre_approval_amount_error"></span>

        </div>
    @endif
</div>
<!-- Cryptocurrency Option -->
<div class="financing-cryptocurrency-section" wire:key="cryptocurrency-section" x-data="{ visible: {{ (is_array($this->offered_financing) && in_array('Cryptocurrency', $this->offered_financing)) ? 'true' : 'false' }} }" x-show="visible" x-on:update-financing-visibility.window="if($event.detail.type === 'Cryptocurrency') visible = $event.detail.visible">
    <div class="financing-section-header mt-4 mb-3 pb-2 border-bottom">
        <h5 class="fw-bold text-primary mb-0">
            <i class="fa-brands fa-bitcoin me-2"></i>Cryptocurrency
        </h5>
    </div>
    <div class="form-group">
        <label class="fw-bold">Offered Cryptocurrency:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the type of cryptocurrency the Buyer is offering (e.g., Bitcoin, Ethereum).">
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
            title="Enter the percentage of the total purchase price to be paid in cryptocurrency.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-group">
            <input type="text" wire:model="crypto_percentage" class="form-control"
                placeholder="Enter percentage to be paid with cryptocurrency (e.g., 50)"
                data-error-id="crypto_percentage_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            <span class="input-group-text">%</span>
        </div>
        <span class="error mt-2" id="crypto_percentage_error"></span>

    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Percentage of Purchase Price to be Paid with Cash:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the percentage to be paid in cash. The two percentages should total 100%">
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
                data-icon="fa-solid fa-money-bill-wave">
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
                data-icon="fa-regular fa-calendar-days">
                <option value="">Select</option>
                <option value="At Contract Signing">At Contract Signing</option>
                <option value="At Closing">At Closing</option>
                <option value="Other">Other</option>
            </select>
        </div>
    </div>

    @if ($crypto_transfer_timing === 'Other')
    <div class="form-group mt-2">
        <div class="input-cover">
            <input type="text" wire:model="crypto_transfer_timing_other" class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days"
                placeholder="Enter timing of transfer (e.g., Within 48 hours of contract acceptance, Partial transfer at inspection period)">
        </div>
    </div>
    @endif
</div>
<!-- Exchange/Trade Option -->
<div class="financing-exchange-trade-section" wire:key="exchange-trade-section" x-data="{ visible: {{ (is_array($this->offered_financing) && in_array('Exchange/Trade', $this->offered_financing)) ? 'true' : 'false' }} }" x-show="visible" x-on:update-financing-visibility.window="if($event.detail.type === 'Exchange/Trade') visible = $event.detail.visible">
    <div class="financing-section-header mt-4 mb-3 pb-2 border-bottom">
        <h5 class="fw-bold text-primary mb-0">
            <i class="fa-solid fa-right-left me-2"></i>Exchange/Trade
        </h5>
    </div>
    <div class="form-group mt-3">
        <label class="fw-bold">Acceptable Exchange Item:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the type of item the Buyer is offering (e.g., another home, artwork, boat, jewelry, motorhome, vehicle, or other).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="exchange_item" class="form-control has-icon" data-icon="fa-solid fa-right-left">
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

    @if ($exchange_item === 'Other' || (is_array($exchange_item) && in_array('Other', $exchange_item)))
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
                placeholder="Enter estimated item value (e.g., 75000)"
                
                 data-error-id="exchange_item_value_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)"
                >

        </div>
                <span class="error mt-2" id="exchange_item_value_error"></span>

    </div>

    <div class="form-group">
        <label class="fw-bold">Condition of Exchange/Trade Item:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the condition of the trade item (e.g., new, good, fair).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="exchange_item_condition" class="form-control has-icon"
                data-icon="fa-solid fa-clipboard-check">
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
        <label class="fw-bold">Additional Cash Buyer Will Offer:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the amount of additional cash the Buyer will provide if the trade item’s value is less than the purchase price.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>

            <input type="text" wire:model="additional_cash" class="form-control has-icon"
                placeholder="Enter additional cash offered (e.g., 25000)"
                 data-error-id="additional_cash_error"
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
<div class="financing-lease-option-section" x-data="{ visible: {{ (is_array($this->offered_financing) && in_array('Lease Option', $this->offered_financing)) ? 'true' : 'false' }} }" x-show="visible" x-on:update-financing-visibility.window="if($event.detail.type === 'Lease Option') visible = $event.detail.visible">
    <div class="financing-section-header mt-4 mb-3 pb-2 border-bottom">
        <h5 class="fw-bold text-primary mb-0">
            <i class="fa-solid fa-key me-2"></i>Lease Option
        </h5>
    </div>
    <!-- 1. Buyer's Desired Offering Price for Lease Option -->
    <div class="form-group">
        <label class="fw-bold">Buyer's Desired Offering Price for Lease Option:
                </label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the price the Buyer is willing to pay if the purchase option is exercised.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>
            <input type="text" wire:model="lease_option_price" class="form-control has-icon"
                placeholder="Enter offering price for lease option (e.g., 500000)"
                data-error-id="lease_option_price_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
        </div>
        <span class="error mt-2" id="lease_option_price_error"></span>
    </div>

    <!-- 2. Monthly Payment Buyer is Offering -->
    <div class="form-group mt-3">
        <label class="fw-bold">Monthly Payment Buyer is Offering:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the monthly lease payment during the lease term before the purchase option may be exercised.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>
            <input type="text" wire:model="lease_option_payment" class="form-control has-icon"
                placeholder="Enter monthly payment amount (e.g., 2500)"
                data-error-id="lease_option_payment_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
        </div>
        <span class="error mt-2" id="lease_option_payment_error"></span>
    </div>

    <!-- 3. Proposed Duration of Lease (Months) -->
    <div class="form-group mt-3">
        <label class="fw-bold">Proposed Duration of Lease (Months):</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the number of months the Buyer wishes to lease before having the option to purchase.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="lease_option_duration" class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days"
                placeholder="Enter the proposed lease duration in months (e.g., 6)">
        </div>
    </div>

    <!-- 4. Offered Option Fee -->
    <div class="form-group mt-3">
        <label class="fw-bold">Offered Option Fee:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate whether the Buyer is offering a non-refundable option fee, and if so, enter the amount.">
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

    @if ($has_option_fee === 'Yes')
        <div class="form-group mt-2">
            <div class="input-cover">
                <span class="input-group-text-seller">$</span>
                <input type="text" wire:model="option_fee_amount" class="form-control has-icon"
                    placeholder="Enter option fee amount (e.g., 15000)"
                    data-error-id="option_fee_amount_error"
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
                data-icon="fa-solid fa-money-check-alt">
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
            <input type="number" wire:model="lease_option_fee_credit_percentage" class="form-control"
                placeholder="Enter percentage of option fee credited (e.g., 50)">
            <span class="input-group-text">%</span>
        </div>
    </div>
    @endif

    <!-- 6. Conditions or Requirements for Lease Option -->
    <div class="form-group mt-3">
        <label class="fw-bold">Conditions or Requirements for Lease Option:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter any additional requirements or limitations (e.g., option exercisable after 12 months).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="lease_option_conditions" class="form-control has-icon"
                data-icon="fa-solid fa-file-lines"
                placeholder="Enter any conditions or requirements for the lease option (e.g., Buyer may exercise option after 12 months, Property must pass inspection)">
        </div>
    </div>

    <!-- 7. Specific Terms Proposed for Lease Option -->
    <div class="form-group mt-3">
        <label class="fw-bold">Specific Terms Proposed for Lease Option:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter any proposed terms for the lease option (e.g., inspections allowed during lease term).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="lease_option_terms" class="form-control has-icon"
                data-icon="fa-solid fa-file-lines"
                placeholder="Enter any conditions or requirements for the lease option (e.g., Buyer may conduct inspections during lease term, Seller to maintain property)">
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
<div class="financing-lease-purchase-section" x-data="{ visible: {{ (is_array($this->offered_financing) && in_array('Lease Purchase', $this->offered_financing)) ? 'true' : 'false' }} }" x-show="visible" x-on:update-financing-visibility.window="if($event.detail.type === 'Lease Purchase') visible = $event.detail.visible">
    <div class="financing-section-header mt-4 mb-3 pb-2 border-bottom">
        <h5 class="fw-bold text-primary mb-0">
            <i class="fa-solid fa-file-signature me-2"></i>Lease Purchase
        </h5>
    </div>

    <div class="alert alert-warning mt-3 p-2 small">
        <strong>Note:</strong> 📌 If this transaction is structured as a Lease-Purchase, the Buyer's Broker Purchase Fee
        applies upon successful closing of the sale. Under Broker Compensation, use the Lease Fee or Lease-Option Fee
        sections only if there is no guaranteed purchase
    </div>

    <!-- 1. Buyer's Desired Offering Price for Lease Purchase -->
    <div class="form-group mt-3">
        <label class="fw-bold">Buyer's Desired Offering Price for Lease Purchase:
                </label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the price the Buyer is offering if the purchase is completed at the end of the lease term.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>
            <input type="text" wire:model="lease_purchase_price" class="form-control has-icon"
                placeholder="Enter offering price for lease purchase (e.g., 800000)"
                data-error-id="lease_purchase_price_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
        </div>
        <span class="error mt-2" id="lease_purchase_price_error"></span>
    </div>

    <!-- 2. Monthly Payment Buyer is Offering -->
    <div class="form-group mt-3">
        <label class="fw-bold">Monthly Payment Buyer is Offering:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the monthly lease payment during the lease-purchase term before the purchase is completed.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>
            <input type="text" wire:model="lease_purchase_payment" class="form-control has-icon"
                placeholder="Enter monthly payment amount (e.g., 5000)"
                data-error-id="lease_purchase_payment_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
        </div>
        <span class="error mt-2" id="lease_purchase_payment_error"></span>
    </div>

    <!-- 3. Proposed Duration of Lease (Months) -->
    <div class="form-group mt-3">
        <label class="fw-bold">Proposed Duration of Lease (Months):</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the number of months the Buyer wishes to lease before purchasing.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="lease_purchase_duration" class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days"
                placeholder="Enter the proposed lease duration in months (e.g., 6)">
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
            title="Enter any requirements (e.g., Buyer must secure financing by lease end).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="lease_purchase_conditions" class="form-control has-icon"
                data-icon="fa-solid fa-file-lines"
                placeholder="Enter any conditions or requirements (e.g., Property must appraise at agreed value, Seller to cover closing costs)">
        </div>
    </div>

    <!-- 7. Specific Terms Proposed for Lease Purchase -->
    <div class="form-group mt-3">
        <label class="fw-bold">Specific Terms Proposed for Lease Purchase:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter any proposed terms for the lease purchase (e.g., rent credits apply toward purchase).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="lease_purchase_terms" class="form-control has-icon"
                data-icon="fa-solid fa-file-lines"
                placeholder="Enter specific terms proposed (e.g., Rent credits apply toward purchase, Option to buy after 12 months)">
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
                placeholder="Enter extension terms (e.g., Lease may be extended for 6 months with adjusted purchase price)">
        </div>
    </div>
</div>

<!-- NFT Option -->
<div class="financing-nft-section" wire:key="nft-section" x-data="{ visible: {{ (is_array($this->offered_financing) && in_array('Non-Fungible Token (NFT)', $this->offered_financing)) ? 'true' : 'false' }} }" x-show="visible" x-on:update-financing-visibility.window="if($event.detail.type === 'Non-Fungible Token (NFT)') visible = $event.detail.visible">
    <div class="financing-section-header mt-4 mb-3 pb-2 border-bottom">
        <h5 class="fw-bold text-primary mb-0">
            <i class="fa-solid fa-image me-2"></i>Non-Fungible Token (NFT)
        </h5>
    </div>
    <div class="form-group mt-3">
        <label class="fw-bold">Offered Non-Fungible Token (NFT):</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the type of NFT the Buyer is offering (e.g., tokenized real estate, digital artwork).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="nft_description" class="form-control has-icon"
                data-icon="fa-solid fa-money-bill-wave"
                placeholder="Enter NFT type (e.g., Tokenized Real Estate, Digital Artwork)">
        </div>
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Percentage of Purchase Price to be Paid with NFT:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the percentage of the purchase price to be paid in NFTs.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-group">
            <input type="text" wire:model="nft_percentage" class="form-control"
                placeholder="Enter percentage to be paid with NFT (e.g., 40)"
                data-error-id="nft_percentage_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            <span class="input-group-text">%</span>
        </div>
        <span class="error mt-2" id="nft_percentage_error"></span>

    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Percentage of Purchase Price to be Paid with Cash:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the percentage to be paid in cash. The two percentages should total 100%.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-group">
            <input type="text" wire:model="cash_percentage_nft" class="form-control"
                placeholder="Enter percentage to be paid with cash (e.g., 60)"
                data-error-id="cash_percentage_nft_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            <span class="input-group-text">%</span>
        </div>
        <span class="error mt-2" id="cash_percentage_nft_error"></span>

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
<div class="financing-seller-section" wire:key="seller-financing-section" x-data="{ visible: {{ (is_array($this->offered_financing) && in_array('Seller Financing', $this->offered_financing)) ? 'true' : 'false' }} }" x-show="visible" x-on:update-financing-visibility.window="if($event.detail.type === 'Seller Financing') visible = $event.detail.visible">
    <div class="financing-section-header mt-4 mb-3 pb-2 border-bottom">
        <h5 class="fw-bold text-primary mb-0">
            <i class="fa-solid fa-handshake me-2"></i>Seller Financing
        </h5>
    </div>
    <div class="form-group">
        <label class="fw-bold">Desired Purchase Price:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the purchase price the Buyer is willing to offer for a property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>

            <input type="text" wire:model="purchase_price" class="form-control has-icon"
                placeholder="Enter total purchase price (e.g., 500000)"
                data-error-id="purchase_price_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)"
                >
        </div>

                        <span class="error mt-2" id="purchase_price_error"></span>

    </div>

{{-- 
    <div class="form-group mt-3">
        <label class="fw-bold">Desired Down Payment:</label>

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
              <label class="fw-bold">Desired Down Payment:</label>


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
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            </div>
            <span class="error mt-2" id="down_payment_type_error"></span>

    </div>




    
    <div class="form-group mt-3">
        <label class="fw-bold">Desired Seller Financing Amount:</label>


           <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the amount the Buyer is requesting the Seller to finance toward the purchase price.">
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
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            </div>
            <span class="error mt-2" id="seller_financing_amount_error"></span>

    </div>


    {{-- <div class="form-group mt-3">
        <label class="fw-bold">Desired Seller Financing Amount:</label>

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
        <label class="fw-bold">Desired Interest Rate:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the interest rate the Buyer is requesting for the seller-financed amount.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-group">
            <input type="text" wire:model="interest_rate" class="form-control"
                placeholder="Enter interest rate (e.g., 6.5)"
                data-error-id="interest_rate_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            <span class="input-group-text">%</span>
        </div>
        <span class="error mt-2" id="interest_rate_error"></span>

    </div>

    <div class="form-group">
        <label class="fw-bold">Desired Loan Duration:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the term of the loan in years.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model.defer="loan_duration" class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days" placeholder="Enter loan duration (e.g., 30 Years)">

        </div>
    </div>

    <div class="form-group mt-3">
        <label class="fw-bold">Prepayment Penalty:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate if the Buyer agrees to a penalty for early payoff and, if so, enter the amount.">
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
                    placeholder="Enter prepayment penalty amount (e.g., 5000)"  data-error-id="prepayment_penalty_amount_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)" >
            </div>
                    <span class="error mt-2" id="prepayment_penalty_amount_error"></span>

        </div>
    @endif
    <div class="form-group mt-3">
        <label class="fw-bold">Balloon Payment:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate if a balloon payment is included and, if so, enter the amount and due date.">
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
                <input type="text" wire:model="balloon_payment_amount" class="form-control has-icon"
                    placeholder="Enter balloon payment amount (e.g., 100000)"
                    data-error-id="balloon_payment_amount_error"
                    oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
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
