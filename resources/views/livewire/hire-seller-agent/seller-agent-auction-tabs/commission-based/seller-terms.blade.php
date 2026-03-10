@php
    // Ensure offered_financing is always an array to prevent in_array() errors
    $offered_financing = is_array($offered_financing ?? []) ? ($offered_financing ?? []) : (array)($offered_financing ?? []);
    $sale_provision = is_array($sale_provision ?? []) ? ($sale_provision ?? []) : (array)($sale_provision ?? []);
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
        <select wire:model="sale_provision" id="sale_provision" class="form-control has-icon select2-multiple"
            data-icon="fa-solid fa-screwdriver-wrench input-icon2" multiple required>
            @foreach ($seller_property as $row_pt)
                <option value="{{ $row_pt['name'] }}" title="{{ $row_pt['description'] }}"
                    {{ in_array($row_pt['name'], $sale_provision) ? 'selected' : '' }}>
                    {{ $row_pt['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2" id="sale_provision_error"></span>
</div>

<!-- Other Special Sale Provision Input -->
<div x-data="{ visible: {{ in_array('Other', $sale_provision) ? 'true' : 'false' }} }" x-show="visible"
    x-on:update-provision-visibility.window="if($event.detail.type === 'Other') visible = $event.detail.visible">
    <div class="form-group mt-3">
        <div class="input-cover">
            <input type="text" wire:model="sale_provision_other" class="form-control has-icon"
                data-icon="fa-solid fa-screwdriver-wrench"
                placeholder="Enter special sale provision (e.g., Divorce Sale, Third-Party Approval)">
        </div>
    </div>
</div>

<!-- Assignment Contract Flow -->
<div x-data="{ visible: {{ in_array('Assignment Contract', $sale_provision) ? 'true' : 'false' }} }" x-show="visible"
    x-on:update-provision-visibility.window="if($event.detail.type === 'Assignment Contract') visible = $event.detail.visible">
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

            <input type="text" wire:model="maximum_budget" class="form-control has-icon"
                placeholder="Enter desired sale price (e.g., 500000)" data-error-id="maximum_budget_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)" required>
        </div>
        <span class="error mt-2" id="maximum_budget_error"></span>
    </div>
</div>

<!-- Offered Financing/Currency -->
<div class="form-group mt-3">
    <label class="fw-bold">Offered Financing/Currency: <span class="text-danger">*</span></label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the types of financing or currency the Seller is open to accepting — such as cash, conventional loans, seller financing, lease options, or alternative methods like cryptocurrency or exchange/trade.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    <div class="input-cover" wire:ignore>
        <select wire:model="offered_financing" id="offered_financing" class="form-control has-icon select2-multiple"
            data-icon="fa-solid fa-money-bill-wave input-icon2" multiple required>
            @foreach ($financing_options_seller as $option)
                <option value="{{ $option['name'] }}" title="{{ $option['description'] }}"
                    {{ in_array($option['name'], $offered_financing) ? 'selected' : '' }}>
                    {{ $option['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2" id="offered_financing_error"></span>

</div>

<div x-data="{ visible: {{ in_array('Other', $offered_financing) ? 'true' : 'false' }} }" x-show="visible"
    x-on:update-financing-visibility.window="if($event.detail.type === 'Other') visible = $event.detail.visible">
    <div class="form-group">
        <div class="input-cover">
            <input type="text" wire:model="other_financing" class="form-control has-icon"
                data-icon="fa-solid fa-money-bill-wave"
                placeholder="Enter type of financing or currency offered (e.g., Gold Bullion, Stock Transfer, Private Investment Agreement)">
        </div>
    </div>
</div>

<!-- Assumable -->
<div x-data="{ visible: {{ in_array('Assumable', $offered_financing) ? 'true' : 'false' }} }" x-show="visible"
    x-on:update-financing-visibility.window="if($event.detail.type === 'Assumable') visible = $event.detail.visible">
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
</div>

<!-- Cryptocurrency -->
<div x-data="{ visible: {{ in_array('Cryptocurrency', $offered_financing) ? 'true' : 'false' }} }" x-show="visible"
    x-on:update-financing-visibility.window="if($event.detail.type === 'Cryptocurrency') visible = $event.detail.visible">
    <div class="financing-section-header mt-4 mb-3 pb-2 border-bottom">
        <h5 class="fw-bold text-primary mb-0">
            <i class="fa-brands fa-bitcoin me-2"></i>Cryptocurrency
        </h5>
    </div>
</div>

<!-- Exchange/Trade -->
<div x-data="{ visible: {{ in_array('Exchange/Trade', $offered_financing) ? 'true' : 'false' }} }" x-show="visible"
    x-on:update-financing-visibility.window="if($event.detail.type === 'Exchange/Trade') visible = $event.detail.visible">
    <div class="financing-section-header mt-4 mb-3 pb-2 border-bottom">
        <h5 class="fw-bold text-primary mb-0">
            <i class="fa-solid fa-exchange-alt me-2"></i>Exchange/Trade
        </h5>
    </div>
</div>

<!-- Lease Option -->
<div x-data="{ visible: {{ in_array('Lease Option', $offered_financing) ? 'true' : 'false' }} }" x-show="visible"
    x-on:update-financing-visibility.window="if($event.detail.type === 'Lease Option') visible = $event.detail.visible">
    <div class="financing-section-header mt-4 mb-3 pb-2 border-bottom">
        <h5 class="fw-bold text-primary mb-0">
            <i class="fa-solid fa-key me-2"></i>Lease Option
        </h5>
    </div>
</div>

<!-- Lease Purchase -->
<div x-data="{ visible: {{ in_array('Lease Purchase', $offered_financing) ? 'true' : 'false' }} }" x-show="visible"
    x-on:update-financing-visibility.window="if($event.detail.type === 'Lease Purchase') visible = $event.detail.visible">
    <div class="financing-section-header mt-4 mb-3 pb-2 border-bottom">
        <h5 class="fw-bold text-primary mb-0">
            <i class="fa-solid fa-file-signature me-2"></i>Lease Purchase
        </h5>
    </div>
</div>

<!-- NFT -->
<div x-data="{ visible: {{ in_array('Non-Fungible Token (NFT)', $offered_financing) ? 'true' : 'false' }} }"
    x-show="visible"
    x-on:update-financing-visibility.window="if($event.detail.type === 'Non-Fungible Token (NFT)') visible = $event.detail.visible">
    <div class="financing-section-header mt-4 mb-3 pb-2 border-bottom">
        <h5 class="fw-bold text-primary mb-0">
            <i class="fa-solid fa-image me-2"></i>Non-Fungible Token (NFT)
        </h5>
    </div>
</div>

<!-- Seller Financing -->
<div x-data="{ visible: {{ in_array('Seller Financing', $offered_financing) ? 'true' : 'false' }} }" x-show="visible"
    x-on:update-financing-visibility.window="if($event.detail.type === 'Seller Financing') visible = $event.detail.visible">
    <div class="financing-section-header mt-4 mb-3 pb-2 border-bottom">
        <h5 class="fw-bold text-primary mb-0">
            <i class="fa-solid fa-handshake me-2"></i>Seller Financing
        </h5>
    </div>
</div>
