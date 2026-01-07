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

{{-- 1. Landlord's Broker Lease Fee --}}
@include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.partials.landlord_broker_lease_fee')

{{-- 2 & 3. Tenant's Broker Commission Structure + Fee (Residential only) --}}
@include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.partials.tenant_broker_commission')

{{-- 4. Payment Timing for Broker Fees --}}
@include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.partials.payment_timing')

{{-- 5. Lease Renewal/Extension Fee --}}
@include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.partials.lease_renewal_extension')

{{-- 6. Expansion Commission for Lease Amendment (Commercial only) --}}
@include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.partials.expansion_commission')

{{-- 7. Interested in Property Management --}}
@include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.partials.interested_property_management')

{{-- 8. Interested in Offering a Lease-Option Agreement --}}
@include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.partials.interested_lease_option')

{{-- 9 & 10. Interested in Selling + Landlord's Broker Purchase Fee --}}
@include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.partials.interested_in_selling')

{{-- 11. Protection Period Timeframe (Days) --}}
@include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.partials.protection_period')

{{-- 12. Early Termination (Residential only) --}}
@include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.partials.early_termination')

{{-- 13. Landlord Agency Agreement Timeframe --}}
@include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.partials.agency_agreement_timeframe')

{{-- 14. Acceptable Brokerage Relationship --}}
@include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.partials.acceptable_brokerage')

{{-- 15. Additional Terms --}}
@include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.partials.additional_terms')
