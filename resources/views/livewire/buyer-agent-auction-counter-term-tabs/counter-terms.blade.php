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
    @if (!empty($agentBidData->commission_structure))
        <p class="mb-1 small"><strong>Commission Structure:</strong> {{ $agentBidData->commission_structure }}</p>
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

{{-- Location & Target --}}
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Location &amp; Target</h5></div>
    <div class="card-body">
        <div class="form-group mb-0">
            <label class="fw-bold">Areas of Interest</label>
            <input type="text" class="form-control" wire:model="areas_of_interest" placeholder="e.g. Downtown, Westside, North suburbs">
        </div>
    </div>
</div>

{{-- Deal Terms --}}
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Deal Terms</h5></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Target Purchase Price / Budget</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="text" class="form-control" wire:model="target_purchase_price" placeholder="e.g. 350,000">
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Timeline to Purchase</label>
                <select class="form-control" wire:model="timeline_to_purchase">
                    <option value="">-- Select timeline --</option>
                    <option value="As soon as possible">As soon as possible</option>
                    <option value="1–3 months">1–3 months</option>
                    <option value="3–6 months">3–6 months</option>
                    <option value="6–12 months">6–12 months</option>
                    <option value="12+ months">12+ months</option>
                    <option value="Flexible">Flexible</option>
                </select>
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
                <label class="fw-bold">Pre-Approval Status</label>
                <select class="form-control" wire:model="pre_approval_status">
                    <option value="">-- Select status --</option>
                    <option value="Pre-approved">Pre-approved</option>
                    <option value="Pre-qualified">Pre-qualified</option>
                    <option value="In process">In process</option>
                    <option value="Not yet started">Not yet started</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Cash Buyer?</label>
                <select class="form-control" wire:model="cash_buyer">
                    <option value="">-- Select --</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-bold">Estimated Down Payment <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" class="form-control" wire:model="estimated_down_payment" placeholder="e.g. 20% or 70,000">
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
