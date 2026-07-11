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

{{-- #1 (Batch B): Broker Compensation & Agency Agreement Terms must render on CREATE for a
     blank property type and for both Residential and Commercial. Previously the whole block was
     gated behind @if(str_contains($property_type,'residential')), so it never appeared on create
     (default blank type) and never for Commercial, and the Agency Agreement Terms partials were
     orphaned (no @include anywhere). All fields are optional (see banner above). Every backing
     prop below is already declared, persisted via saveAllMetadata(), and hydrated on edit in both
     LandlordOfferListing and LandlordOfferListingEdit — verified 2026-07-05 — so this is a
     markup-wiring fix only; no component/metadata changes are needed. Each partial self-gates on
     $property_type where a type-specific form is required (see notes below). --}}

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

<!-- Landlord's Broker Lease Fee (self-gates: Residential and Commercial branches) -->
@include('livewire.offer-listing.offer-landlord-tabs.commission-based.partials.landlord_broker_lease_fee')

{{-- Agency Agreement Terms — the previously-orphaned partials, ordered to mirror the Hire
     Landlord broker-compensation tab. Each is self-contained and self-gating:
       • tenant_broker_commission   → Residential + Commercial (own @if branches)
       • payment_timing             → Residential only
       • agency_agreement_timeframe → all property types (ungated)
       • protection_period          → Residential only
       • early_termination          → Residential only
       • additional_terms           → all property types (ungated)
     expansion_commission (no bindings) and commented_expansion (fully commented out) are dead
     partials and are intentionally NOT wired. --}}
@include('livewire.offer-listing.offer-landlord-tabs.commission-based.partials.tenant_broker_commission')
@include('livewire.offer-listing.offer-landlord-tabs.commission-based.partials.payment_timing')
@include('livewire.offer-listing.offer-landlord-tabs.commission-based.partials.agency_agreement_timeframe')
@include('livewire.offer-listing.offer-landlord-tabs.commission-based.partials.protection_period')
@include('livewire.offer-listing.offer-landlord-tabs.commission-based.partials.early_termination')
@include('livewire.offer-listing.offer-landlord-tabs.commission-based.partials.additional_terms')
