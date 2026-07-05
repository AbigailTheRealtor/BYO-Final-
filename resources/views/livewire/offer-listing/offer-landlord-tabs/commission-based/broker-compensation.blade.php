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

@php
$_isResidential = str_contains(strtolower($property_type ?? ''), 'residential');
$_isCommercial  = str_contains(strtolower($property_type ?? ''), 'commercial');
@endphp

{{-- #1 (Batch B): Broker Compensation & Agency Agreement Terms must render on CREATE for a
     blank property type and for both Residential and Commercial — previously the entire block
     was gated behind @if($_isResidential), so it never appeared on create (default blank type)
     and never for Commercial. All fields are optional (see banner above). The backing props are
     already persisted + hydrated by saveAllMetadata() in both create and edit; only the markup
     wiring was missing. --}}

<!-- Landlord's Broker Commission Structure -->
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Landlord's Broker Commission Structure:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select how the Landlord's Broker will be compensated. Choose from a percentage of rent, a percentage of the gross lease value, a percentage of the first month's rent, a flat fee, or Other to define a custom arrangement.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover mt-2">
        <select wire:model.lazy="commission_structure" class="form-control has-icon"
            data-icon="fa-solid fa-handshake">
            <option value="">Select</option>
            <option value="Landlord Pays Broker Directly">Landlord Pays Broker Directly</option>
            <option value="Deducted from First Month's Rent">Deducted from First Month's Rent</option>
            <option value="Deducted from Lease Proceeds">Deducted from Lease Proceeds</option>
            <option value="Other">Other</option>
        </select>
    </div>
</div>

<!-- Landlord's Broker Lease Fee -->
@include('livewire.offer-listing.offer-landlord-tabs.commission-based.partials.landlord_broker_lease_fee')

{{-- Agency Agreement Terms — self-contained partials whose props are already persisted +
     hydrated. agency_agreement_timeframe + additional_terms are ungated (show for all types);
     protection_period + early_termination self-gate to Residential by their existing design. --}}
@include('livewire.offer-listing.offer-landlord-tabs.commission-based.partials.agency_agreement_timeframe')
@include('livewire.offer-listing.offer-landlord-tabs.commission-based.partials.protection_period')
@include('livewire.offer-listing.offer-landlord-tabs.commission-based.partials.early_termination')
@include('livewire.offer-listing.offer-landlord-tabs.commission-based.partials.additional_terms')
