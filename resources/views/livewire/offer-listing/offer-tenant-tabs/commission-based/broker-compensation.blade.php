@php
$safeKey = function(...$parts) {
    return implode('-', array_map(function($p) {
        if (!is_scalar($p) || $p === '' || $p === null) return 'none';
        return preg_replace('/[^a-z0-9\-]/', '', strtolower((string)$p));
    }, $parts));
};
@endphp
<h3>Broker Compensation & Agency Agreement Terms</h3>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>📝 Complete the compensation terms that apply. All fields are optional. If left blank, Agents may
                propose terms as part of their bid. Commission is typically paid upon lease execution or Tenant move-in.
            </strong>
        </div>
    </div>
</div>

<!-- Tenant's Broker Commission Structure -->
<div class="form-group mb-4">
    <label class="fw-bold ">
        Tenant's Broker Commission Structure:

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select how the Tenant's Broker will be compensated—either paid directly by the Tenant (out-of-pocket) or requested from the Landlord as part of the offer. If the Landlord does not agree to pay, the Tenant remains responsible for the commission under the brokerage agreement.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover mt-2">
        <select wire:model="commission_structure" class="form-control has-icon"
            data-icon="fa-solid fa-file-invoice-dollar">
            <option value="">Select</option>
            <option value="Out-of-Pocket Payment">Tenant Pays Out-of-Pocket</option>
            <option value="Included in Offer">Requested From Landlord in the Offer</option>
        </select>
    </div>
    @error('commission_structure')
        <span class="text-danger small">{{ $message }}</span>
    @enderror
</div>

<!-- Tenant's Broker Lease Fee -->
<div class="form-group mb-4">
    <label class="fw-bold ">
        Tenant's Broker Lease Fee:

        @if ($property_type === 'Residential Property')
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Choose how the Tenant’s Broker will be compensated if a property is leased. Options include a flat fee, a percentage of monthly rent, a percentage of the gross lease value, a combination of flat fee and percentage, or select “Other” to define a custom structure. Then enter the appropriate amount(s) based on your selection.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        @endif
        @if ($property_type === 'Commercial Property')
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Choose how the Tenant’s Broker will be compensated if the property is leased. Options include a flat fee, a percentage of the net aggregate rent, a combination of flat fee and percentage, or select “Other” to define a custom structure. Then, enter the appropriate amount(s) based on your selection.">
                <i class="fa-solid fa-circle-info"></i>

            </span>
        @endif

    </label>

    <div class="input-cover mt-2">
        <select wire:model="lease_fee_type" class="form-control has-icon" data-icon="fa-solid fa-file-invoice-dollar">
            <option value="">Select</option>
            <option value="Flat Fee">Flat Fee</option>
            @if ($property_type === 'Residential Property')
                <option value="Percentage of Monthly Rent">Percentage of Monthly Rent</option>
                <option value="Percentage of the Gross Lease Value">Percentage of the Gross Lease Value</option>
                <option value="Flat Fee + Percentage of the Gross Lease Value">Flat Fee + Percentage of the Gross Lease
                    Value</option>
            @elseif ($property_type === 'Commercial Property')
                <option value="Percentage of the Net Aggregate Rent">Percentage of the Net Aggregate Rent</option>
                <option value="Flat Fee + Percentage of the Net Aggregate Rent">Flat Fee + Percentage of the Net
                    Aggregate Rent</option>
            @endif
            <option value="other">Other</option>
        </select>
    </div>

    <!-- Dynamic Inputs Based on Selection -->
    <div class="mt-3" wire:key="{{ $safeKey('lease-fee-inputs', $lease_fee_type) }}">
        @if ($lease_fee_type === 'Flat Fee')
            <div class="input-group" wire:key="{{ $safeKey('lease-fee-flat') }}">
                <span class="input-group-text">$</span>
                <input type="text" wire:model.lazy="lease_fee_flat" class="form-control"
                    placeholder="Enter flat fee amount (e.g., 5000)"
                    data-error-id="lease_fee_flat_error" oninput="formatWithCommas(this)" onblur="formatWithCommas(this)"
                    onpaste="handlePaste(event)">
                <span class="error mt-2" id="lease_fee_flat_error"></span>
            </div>
        @elseif($lease_fee_type === 'Percentage of the Gross Lease Value')
            <div class="row g-2" wire:key="{{ $safeKey('lease-fee-gross-lease') }}">
                <div class="col-md-12">
                    <div class="input-group">
                        <input type="number" wire:model="lease_fee_percentage" class="form-control"
                            placeholder="Enter percentage of the gross lease value (e.g., 10)">
                        <span class="input-group-text">%</span>
                    </div>
                </div>

            </div>
        @elseif($lease_fee_type === 'Percentage of Monthly Rent')
            <div class="row g-2" wire:key="{{ $safeKey('lease-fee-monthly-rent') }}">
                <div class="col-md-12">
                    <div class="input-group">
                        <input type="number" wire:model="lease_fee_percentage_monthly_rent" class="form-control"
                            placeholder="Enter percentage of monthly rent (e.g., 100)">
                        <span class="input-group-text">%</span>
                    </div>
                </div>

            </div>
        @elseif($lease_fee_type === 'Flat Fee + Percentage of the Gross Lease Value')
            <div class="row g-2" wire:key="{{ $safeKey('lease-fee-flat-gross-combo') }}">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="text" wire:model.lazy="lease_fee_flat_combo" class="form-control"
                            placeholder="Enter flat fee amount (e.g., 1000)"
                            oninput="formatWithCommas(this)" onblur="formatWithCommas(this)" onpaste="handlePaste(event)">
                    </div>
                </div>
                <div class="col-md-1 text-center pt-2">+</div>
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="number" wire:model="lease_fee_percentage_combo" class="form-control"
                            placeholder="Enter percentage of the gross lease value (e.g., 10)">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
            </div>
        @elseif($lease_fee_type === 'Percentage of the Net Aggregate Rent')
            <div class="input-group" wire:key="{{ $safeKey('lease-fee-net-aggregate') }}">
                <input type="number" wire:model="lease_fee_percentage_net" class="form-control"
                    placeholder="Enter percentage of the net aggregate rent (e.g., 6)">
                <span class="input-group-text">%</span>
            </div>
        @elseif($lease_fee_type === 'Flat Fee + Percentage of the Net Aggregate Rent')
            <div class="row g-2" wire:key="{{ $safeKey('lease-fee-flat-net-combo') }}">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="text" wire:model.lazy="lease_fee_flat_combo_net" class="form-control"
                            placeholder="Enter flat fee amount (e.g., 1500)"
                            oninput="formatWithCommas(this)" onblur="formatWithCommas(this)" onpaste="handlePaste(event)">
                    </div>
                </div>
                <div class="col-md-1 text-center pt-2">+</div>
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="number" wire:model="lease_fee_percentage_combo_net" class="form-control"
                            placeholder="Enter percentage of the net aggregate rent (e.g., 6)">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
            </div>
        @elseif($lease_fee_type === 'other')
            <input type="text" wire:model="lease_fee_other" class="form-control mt-2"
                placeholder="Enter the total lease fee amount and payment structure for the Tenant’s Broker (e.g., $1,500 upfront, $2,000 at lease execution)">
        @endif
    </div>
    @error('lease_fee_*')
        <span class="text-danger small">{{ $message }}</span>
    @enderror
</div>

@if(empty($isTenantOfferListing))
@if ($property_type === 'Residential Property')
    <!-- Payment Timing for Broker Fees -->
    <div class="form-group mb-4">
        <label class="fw-bold d-flex align-items-center">
            Payment Timing for Broker Fees:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select when the Broker's fee will be paid. Options include: deducting from rent collected, payment after lease execution, payment after the rent due date, or &quot;Other&quot; to define a custom arrangement.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

        <div class="input-cover mt-2">
            <select wire:model="broker_fee_timing" wire:key="broker-fee-timing-select" class="form-control has-icon" data-icon="fa-solid fa-clock">
                <option value="">Select</option>
                <option value="Deducted from Rent Collected">Deducted from Rent Collected</option>
                <option value="Paid Within Calendar Days After Executed Lease">Paid Within Calendar Days After Executed Lease</option>
                <option value="Paid Within Calendar Days of Tenant Rent Payment">Paid Within Calendar Days of Tenant Rent Payment</option>
                <option value="other">Other</option>
            </select>
        </div>

        <div class="mt-3" wire:key="{{ $safeKey('broker-fee-timing-inputs', $broker_fee_timing) }}">
            @if ($broker_fee_timing === 'Deducted from Rent Collected')
                <div class="input-group" wire:key="{{ $safeKey('broker-fee-deducted') }}">
                    <span class="input-group-text">#</span>
                    <input type="number" wire:model.lazy="broker_fee_days_from_rent" class="form-control"
                        placeholder="Enter number of calendar days (e.g., 5)">
                </div>
            @elseif ($broker_fee_timing === 'Paid Within Calendar Days After Executed Lease')
                <div class="input-group">
                    <span class="input-group-text">#</span>
                    <input type="number" wire:model.lazy="broker_fee_days_after_lease" class="form-control"
                        placeholder="Enter number of calendar days (e.g., 5)">
                </div>
            @elseif ($broker_fee_timing === 'Paid Within Calendar Days of Tenant Rent Payment')
                <div class="input-group">
                    <span class="input-group-text">#</span>
                    <input type="number" wire:model.lazy="broker_fee_days_after_rent" class="form-control"
                        placeholder="Enter number of calendar days (e.g., 5)">
                </div>
            @elseif ($broker_fee_timing === 'other')
                <div class="input-group">
                    <input type="text" wire:model.lazy="broker_fee_timing_other" class="form-control"
                        placeholder="Enter payment arrangement (e.g., Broker to be paid 50% of commission upon lease execution and 50% upon tenant move-in)">
                </div>
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
                title="Select when the Broker's fee will be paid. Options include: full payment upon execution of the lease, sales contract, or other transfer agreement; 50% upon execution with the remaining 50% due at commencement of the agreement; 50% upon execution with the remaining 50% due upon occupancy of the premises; or &quot;Other&quot; to define a custom arrangement.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

        <div class="input-cover mt-2">
            <select wire:model="broker_fee_timing" wire:key="broker-fee-timing-commercial-select" class="form-control has-icon" data-icon="fa-solid fa-clock">
                <option value="">Select</option>
                <option value="full_execution">Full amount upon execution of lease, sales contract, or other transfer agreement</option>
                <option value="half_execution_half_commencement">50% due upon execution, 50% due upon commencement of agreement</option>
                <option value="half_execution_half_occupancy">50% due upon execution, 50% due upon occupancy of premises</option>
                <option value="other">Other</option>
            </select>
        </div>

        @if ($broker_fee_timing === 'other')
            <div class="mt-3" wire:key="{{ $safeKey('broker-fee-timing-other-input', $broker_fee_timing) }}">
                <div class="input-group">
                    <input type="text" wire:model.lazy="broker_fee_timing_other" class="form-control"
                        placeholder="Enter payment arrangement (e.g., Broker to be paid 25% upon lease execution, 25% upon tenant move-in, and 50% upon first month's rent payment)">
                </div>
            </div>
        @endif
    </div>
@endif
@endif {{-- empty($isTenantOfferListing) --}}

@if(empty($isTenantOfferListing))
<!-- Tenant's Broker Purchase Fee -->
<div class="form-group mb-4">
    <label class="fw-bold ">
        Interested in Purchasing a Property:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate whether the Tenant is also interested in purchasing a property.  If “Yes” is selected, you’ll be prompted to enter compensation details.">
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
    <div class="form-group mb-4" wire:key="{{ $safeKey('purchase-fee-section', $interested_purchase_fee_type) }}">
        <label class="fw-bold ">
            Tenant's Broker Purchase Fee:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Choose how the Tenant’s Broker will be compensated if the Tenant purchases a property. Options include a flat fee, a percentage of the purchase price, a combination of both, or select “Other” to define a custom structure. Then enter the appropriate amount(s) based on your selection.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover mt-2">
            <select wire:model="purchase_fee_type" class="form-control has-icon"
                data-icon="fa-solid fa-file-invoice-dollar">
                <option value="">Select</option>
                <option value="Flat Fee">Flat Fee</option>
                <option value="Percentage of the Total Purchase Price">Percentage of the Total Purchase Price</option>
                <option value="Percentage of the Total Purchase Price + Flat Fee">Percentage of the Total Purchase
                    Price + Flat Fee</option>
                <option value="other">Other</option>
            </select>
        </div>

        <div class="mt-3">
            @if ($purchase_fee_type === 'Flat Fee')
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="text" wire:model.lazy="purchase_fee_flat" class="form-control"
                        placeholder="Enter flat fee amount (e.g., 5000)"
                        data-error-id="purchase_fee_flat_error" oninput="formatWithCommas(this)"
                        onblur="formatWithCommas(this)" onpaste="handlePaste(event)">
                    <span class="error mt-2" id="purchase_fee_flat_error"></span>
                </div>
            @elseif($purchase_fee_type === 'Percentage of the Total Purchase Price')
                <div class="input-group">
                    <input type="number" wire:model.lazy="purchase_fee_percentage" class="form-control"
                        placeholder="Enter percentage of the total purchase price (e.g., 3)">
                    <span class="input-group-text">%</span>
                </div>
            @elseif($purchase_fee_type === 'Percentage of the Total Purchase Price + Flat Fee')
                <div class="row g-2">
                    <div class="col-md-6">
                        <div class="input-group">
                            <input type="number" wire:model.lazy="purchase_fee_percentage_combo"
                                class="form-control"
                                placeholder="Enter percentage of the total purchase price (e.g., 2)">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-md-1 text-center pt-2">+</div>

                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="text" wire:model.lazy="purchase_fee_flat_combo" class="form-control"
                                placeholder="Enter flat fee amount (e.g., 3000)"
                                oninput="formatWithCommas(this)" onblur="formatWithCommas(this)" onpaste="handlePaste(event)">
                        </div>
                    </div>
                </div>
            @elseif($purchase_fee_type === 'other')
                <input type="text" wire:model.lazy="purchase_fee_other" class="form-control mt-2"
                    placeholder="Enter purchase fee amount (e.g., $1,000 upfront + 2% at closing)">
            @endif
        </div>
        @error('purchase_fee_*')
            <span class="text-danger small">{{ $message }}</span>
        @enderror
    </div>
@endif
@endif {{-- empty($isTenantOfferListing) --}}

@if(empty($isTenantOfferListing))
<div class="form-group mb-2">
    <label class="fw-bold ">
        Interested in a Lease-Option Agreement:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate whether the Tenant is interested in entering a lease with an option to purchase. If “Yes” is selected, you’ll be prompted to enter compensation details.">
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
    @if(empty($isTenantOfferListing)) {{-- Compensation for Creating must not render for Tenant Offer Listing --}}
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
                        : 'Enter flat fee amount (e.g., 1500)' }}"
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
    @endif {{-- empty($isTenantOfferListing): Compensation for Creating --}}

    @if(empty($isTenantOfferListing)) {{-- Compensation if Purchase Option must not render for Tenant Offer Listing --}}
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
                        : 'Enter flat fee amount (e.g., 5000)' }}"
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
    @endif {{-- empty($isTenantOfferListing): Compensation if Purchase Option --}}
@endif
@endif {{-- empty($isTenantOfferListing) --}}

@if($user_type !== 'tenant') {{-- hidden: Protection Period Timeframe --}}
<!-- Protection Period Timeframe -->
<div class="form-group mb-4 mt-3">
    <label class="fw-bold">
        Protection Period Timeframe (Days):
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the number of days after the agreement ends during which the Tenant’s Broker remains entitled to compensation if the Tenant leases, purchases, or otherwise acquires a property introduced during the agreement period.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover mt-2">
        <input type="number" wire:model="protection_period" class="form-control has-icon"
            data-icon="fa-solid fa-shield-halved" placeholder="Enter protection period in days (e.g., 90)">
    </div>
    @error('protection_period')
        <span class="text-danger small">{{ $message }}</span>
    @enderror
</div>
@endif


@if($user_type !== 'tenant') {{-- hidden: Early Termination Fee --}}
<!-- Early Termination Fee -->
<div class="form-group mb-4">
    <label class="fw-bold ">
        Early Termination Fee:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether the Tenant agrees to pay a cancellation fee if the agreement is ended early. If “Yes” is selected, you’ll be prompted to enter the fee amount. This fee may be credited toward a future transaction during the protection period, depending on the Broker’s policy.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover mt-2">
        <select wire:model="early_termination_fee_option" class="form-control has-icon"
            data-icon="fa-solid fa-triangle-exclamation">
            <option value="">Select</option>
            <option value="Yes">Yes</option>
            <option value="No">No</option>
        </select>
    </div>
    @if ($early_termination_fee_option === 'Yes')
        <div class="mt-3" wire:key="{{ $safeKey('early-termination-amount', $early_termination_fee_option) }}">
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="text" wire:model.lazy="early_termination_fee_amount" class="form-control"
                    placeholder="Enter early termination fee amount (e.g., 1000)"
                    oninput="formatWithCommas(this)" onblur="formatWithCommas(this)" onpaste="handlePaste(event)">
            </div>
            @error('early_termination_fee_amount')
                <span class="text-danger small">{{ $message }}</span>
            @enderror
        </div>
    @endif
</div>
@endif

@if($user_type !== 'tenant') {{-- hidden: Retainer Fee --}}
<!-- Retainer Fee -->
<div class="form-group mb-4">
    <label class="fw-bold">
        Retainer Fee:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether the Tenant agrees to pay a non-refundable retainer fee to initiate Broker services. If “Yes,” enter the amount. This fee is separate from any commission owed unless otherwise specified.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover mt-2">
        <select wire:model="retainer_fee_option" class="form-control has-icon"
            data-icon="fa-solid fa-file-invoice-dollar">
            <option value="">Select</option>
            <option value="Yes">Yes</option>
            <option value="No">No</option>
        </select>
    </div>

    @if ($retainer_fee_option === 'Yes')
        <div class="mt-3" wire:key="{{ $safeKey('retainer-fee-details', $retainer_fee_option) }}">
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="text" wire:model.lazy="retainer_fee_amount" class="form-control"
                    placeholder="Enter retainer fee amount (e.g., 500)"
                    oninput="formatWithCommas(this)" onblur="formatWithCommas(this)" onpaste="handlePaste(event)">
            </div>
            @error('retainer_fee_amount')
                <span class="text-danger small">{{ $message }}</span>
            @enderror

            <div class="mt-3">
                <label class="fw-bold">
                    Retainer Fee Application:
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Select whether the retainer fee will be applied toward the final commission or charged in addition to it.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                </label>

                <div class="input-cover mt-2">
                    <select wire:model="retainer_fee_application" class="form-control has-icon"
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


@if($user_type !== 'tenant') {{-- hidden: Tenant Agency Agreement Timeframe --}}
<!-- Tenant Agency Agreement Timeframe -->
<div class="form-group mb-4">
    <label class="fw-bold">
        Tenant Agency Agreement Timeframe:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select how long the agreement between the Tenant and the Broker will remain in effect. Choose from preset durations or select “Other” to enter a custom timeframe.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover mt-2">
        <select wire:model="agency_agreement_timeframe" class="form-control has-icon"
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
        <div class="mt-3" wire:key="{{ $safeKey('agency-agreement-custom', $agency_agreement_timeframe) }}">
            <input type="text" wire:model="agency_agreement_custom" class="form-control"
                placeholder="Enter Tenant agency agreement timeframe (e.g., 8 Months)">
            @error('agency_agreement_custom')
                <span class="text-danger small">{{ $message }}</span>
            @enderror
        </div>
    @endif
</div>
@endif

@if($user_type !== 'tenant') {{-- hidden: Acceptable Brokerage Relationship --}}
<!-- Acceptable Brokerage Relationship -->
<div class="form-group mb-4">
    <label class="fw-bold">
        Acceptable Brokerage Relationship:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type of legal relationship the Tenant wishes to establish with the Broker. This determines the level of representation the Broker will provide. Real estate laws vary by state, and Brokers may offer different types of agency relationships. Both the Broker and Tenant must comply with all applicable local, state, and federal laws.">
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
                <ul class="mb-2 ps-3" style="list-style-type: disc;">
                    <li>Default brokerage relationship in Florida unless otherwise specified by law.</li>
                    <li>The Broker provides limited representation to both parties without full fiduciary duties.</li>
                    <li>The Broker must act honestly, fairly, and with skill, care, and diligence.</li>
                    <li>This brokerage relationship may not be permitted in certain states, including Texas, Alaska, Vermont, Kansas, or Colorado.</li>
                </ul>
            @elseif($brokerage_relationship === 'Single Agent Representation')
                <h6 class="fw-bold">• Single Agent Representation:</h6>
                <ul class="mb-2 ps-3" style="list-style-type: disc;">
                    <li>The Broker acts as a fiduciary, providing the highest level of loyalty, confidentiality, obedience, and full disclosure.</li>
                    <li>The Broker must always act in the Tenant's best interest.</li>
                    <li>Requires written consent from both parties.</li>
                    <li>If required by state law, a Single Agent Notice will be provided by the Broker and signed by the appropriate party.</li>
                </ul>
            @elseif($brokerage_relationship === 'Dual Agency Representation')
                <h6 class="fw-bold">• Dual Agency Representation:</h6>
                <ul class="mb-2 ps-3" style="list-style-type: disc;">
                    <li>The Broker represents both the Tenant and the Landlord in the same transaction.</li>
                    <li>The Broker must remain neutral and may not disclose confidential information from either party.</li>
                    <li>Requires written consent from both parties.</li>
                    <li>This brokerage relationship may not be permitted in certain states, including Alaska, Colorado, Florida, Kansas, Maryland, Oklahoma, Texas, Vermont, and Wyoming.</li>
                </ul>
            @elseif($brokerage_relationship === 'No Brokerage Relationship')
                <h6 class="fw-bold">• No Brokerage Relationship:</h6>
                <ul class="mb-2 ps-3" style="list-style-type: disc;">
                    <li>The Broker does not represent the Tenant and has no fiduciary duties.</li>
                    <li>The Broker must still act honestly and disclose all known material facts that materially affect the property's value.</li>
                    <li>The Tenant is responsible for their own due diligence and negotiations.</li>
                </ul>
            @endif
            <div class="alert alert-warning mt-3 p-2 small">
                <strong>⚠️ Legal Notice:</strong> Certain brokerage relationships are not permitted in all states. If your selection is not allowed, the Broker will establish a permitted legal alternative. Real estate laws change frequently. Both the Broker and Tenant are responsible for complying with all current local, state, and federal laws.
            </div>
        </div>
    @endif
</div>
@endif

@if($user_type !== 'tenant') {{-- hidden: Additional Terms --}}
<!-- Additional Terms -->
<div class="form-group mb-4">
    <label class="fw-bold">
        Additional Terms:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Include any additional or custom compensation terms, conditions, or agreements not covered above.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <textarea wire:model="additional_details_broker" class="form-control mt-2" rows="3"
        placeholder="Enter any additional terms"></textarea>
</div>
@endif


<script>
function formatWithCommas(input) {
    let value = input.value.replace(/[^\d.]/g, '');
    let parts = value.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    input.value = parts.length > 1 ? parts[0] + '.' + parts[1].substring(0, 2) : parts[0];
}

function validateInput(input) {
    let value = input.value.replace(/[^\d.]/g, '');
    input.value = value;
}

function reformatNumber(input) {
    let value = parseFloat(input.value.replace(/,/g, ''));
    if (!isNaN(value)) {
        input.value = value.toString();
    }
}

function handlePaste(event) {
    event.preventDefault();
    let paste = (event.clipboardData || window.clipboardData).getData('text');
    let cleanValue = paste.replace(/[^\d.]/g, '');
    event.target.value = cleanValue;
    formatWithCommas(event.target);
}

// Re-initialize money formatting after Livewire updates
function initializeMoneyInputs() {
    // Format lease_value input if it's a $ (flat) type
    const leaseInput = document.querySelector('[wire\\:key^="lease-value-input-flat"]');
    if (leaseInput && leaseInput.value) {
        formatWithCommas(leaseInput);
    }
    // Format purchase_value input if it's a $ (flat) type  
    const purchaseInput = document.querySelector('[wire\\:key^="purchase-value-input-flat"]');
    if (purchaseInput && purchaseInput.value) {
        formatWithCommas(purchaseInput);
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeMoneyInputs();
});

// Re-initialize after Livewire updates (Livewire v2)
if (typeof Livewire !== 'undefined') {
    document.addEventListener('livewire:load', function() {
        Livewire.hook('message.processed', function() {
            initializeMoneyInputs();
        });
    });
}
</script>
