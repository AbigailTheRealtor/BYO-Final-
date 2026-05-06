<h4>Your Counter Terms</h4>

@php
    $agentBidData  = $pab?->get ?? null;
    $agentServices = [];
    if ($agentBidData && !empty($agentBidData->services)) {
        $svcRaw        = $agentBidData->services;
        $agentServices = is_string($svcRaw) ? (json_decode($svcRaw, true) ?? []) : (array) $svcRaw;
        $agentServices = array_values(array_filter($agentServices));
    }
@endphp

@if ($agentBidData)
<div class="mb-4 p-3 rounded" style="background:#f0f9fa;border-left:4px solid #049399;">
    <p class="fw-bold mb-1" style="color:#049399;">Agent Representation Terms (Reference Only)</p>
    <p class="text-muted small mb-2">Agent representation terms were agreed upon separately and are shown here for reference only.</p>
    @if (!empty($agentBidData->purchase_fee_type))
        <p class="mb-1 small"><strong>Lease Fee Type:</strong> {{ $agentBidData->purchase_fee_type }}</p>
    @endif
    @if (!empty($agentServices))
        <p class="mb-0 small"><strong>Services Included:</strong>
            {{ implode(', ', array_slice($agentServices, 0, 5)) }}{{ count($agentServices) > 5 ? ' (+' . (count($agentServices) - 5) . ' more)' : '' }}
        </p>
    @endif
</div>
@endif

{{-- Client Contact Info --}}
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Client Contact Information</h5></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-12 mb-3">
                <label class="fw-bold">Full Name</label>
                <input type="text" class="form-control" wire:model="client_name" placeholder="Client's full name">
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Phone Number</label>
                <input type="text" class="form-control" wire:model="client_phone" placeholder="Client's phone number">
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Email Address</label>
                <input type="email" class="form-control" wire:model="client_email" placeholder="Client's email address">
            </div>
        </div>
    </div>
</div>

{{-- Property Address --}}
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Property Address</h5></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-12 mb-3">
                <label class="fw-bold">Street Address</label>
                <input type="text" class="form-control" wire:model="client_property_address" placeholder="Street address">
            </div>
            <div class="col-md-5 mb-3">
                <label class="fw-bold">City</label>
                <input type="text" class="form-control" wire:model="client_property_city" placeholder="City">
            </div>
            <div class="col-md-4 mb-3">
                <label class="fw-bold">State</label>
                <input type="text" class="form-control" wire:model="client_property_state" placeholder="State">
            </div>
            <div class="col-md-3 mb-3">
                <label class="fw-bold">ZIP Code</label>
                <input type="text" class="form-control" wire:model="client_property_zip" placeholder="ZIP">
            </div>
        </div>
    </div>
</div>

{{-- Deal Terms --}}
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Deal Terms</h5></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Desired Monthly Rent <span class="text-muted fw-normal">(optional)</span></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="text" class="form-control" wire:model="desired_monthly_rent" placeholder="e.g. 2,200">
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Availability Date</label>
                <input type="date" class="form-control" wire:model="availability_date">
            </div>
        </div>
    </div>
</div>

{{-- Qualification Signals --}}
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Qualification Signals</h5></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Occupancy Status</label>
                <select class="form-control" wire:model="occupancy_status">
                    <option value="">-- Select status --</option>
                    <option value="Vacant – ready now">Vacant – ready now</option>
                    <option value="Occupied – tenant leaving soon">Occupied – tenant leaving soon</option>
                    <option value="Owner-occupied">Owner-occupied</option>
                    <option value="Occupied – lease active">Occupied – lease active</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Flexibility</label>
                <select class="form-control" wire:model="flexibility">
                    <option value="">-- Select flexibility --</option>
                    <option value="Very flexible">Very flexible</option>
                    <option value="Somewhat flexible">Somewhat flexible</option>
                    <option value="Not flexible – firm on terms">Not flexible – firm on terms</option>
                </select>
            </div>
        </div>
    </div>
</div>

{{-- Additional Terms or Notes --}}
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Additional Terms or Notes <span class="text-muted fw-normal small">(Non-Binding Context)</span></h5></div>
    <div class="card-body">
        <div class="alert alert-info bg-light-info border-info mb-3">
            <strong>📋 Share any special instructions, preferences, or context to help the Agent understand your counter offer.</strong>
        </div>
        <textarea wire:model="additional_details" class="form-control" rows="4"
            placeholder="Enter any additional terms, conditions, or notes..."
            style="padding:10px;font-size:16px;"></textarea>
    </div>
</div>
