@extends('layouts.main')

@push('styles')
<style>
    .ack-wrap {
        max-width: 800px;
        margin: 0 auto;
    }
    .ack-section {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 10px;
        margin-bottom: 1.25rem;
        overflow: hidden;
    }
    .ack-section-header {
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        padding: .7rem 1.25rem;
        font-weight: 700;
        font-size: .87rem;
        display: flex;
        align-items: center;
        gap: .45rem;
        color: #333;
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .ack-section-header i { color: #049399; }
    .ack-section-body { padding: 1.2rem 1.4rem; }
    .comp-table {
        width: 100%;
        font-size: .9rem;
        border-collapse: collapse;
    }
    .comp-table tr:not(:last-child) td { border-bottom: 1px solid #f0f0f0; }
    .comp-table td { padding: .55rem .25rem; vertical-align: top; }
    .comp-table td:first-child {
        width: 45%;
        color: #6c757d;
        font-size: .82rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        padding-right: 1rem;
    }
    .comp-table td:last-child { color: #1a1a1a; }
    .service-bullet-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .service-bullet-list li {
        font-size: .9rem;
        color: #1a1a1a;
        padding: 2px 0;
    }
    .service-bullet-list li::before {
        content: "✓ ";
        color: #049399;
        font-weight: 700;
    }
    .detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .5rem 1.5rem;
        font-size: .9rem;
    }
    .detail-grid dt {
        font-size: .78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #6c757d;
        margin-bottom: 1px;
    }
    .detail-grid dd {
        color: #1a1a1a;
        margin: 0 0 .6rem 0;
    }
    .success-banner {
        background: #f0fafa;
        border: 1px solid #c8e8ea;
        border-radius: 10px;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: flex-start;
        gap: 1rem;
    }
    .success-banner-icon {
        font-size: 2rem;
        color: #049399;
        flex-shrink: 0;
        line-height: 1;
    }
    .success-banner h4 {
        color: #1a3b3e;
        margin: 0 0 .3rem 0;
        font-size: 1.1rem;
        font-weight: 700;
    }
    .success-banner p {
        color: #3a5a5e;
        margin: 0;
        font-size: .92rem;
        line-height: 1.6;
    }
</style>
@endpush

@section('content')
<div class="buyerOfferContentDetails py-4">
<div class="container ack-wrap">

    @php
        $agentFullName    = trim(($mapped['first_name'] ?? '') . ' ' . ($mapped['last_name'] ?? ''));
        $agentDisplayName = $agentFullName ?: ($agent->name ?? 'This Agent');
        $roleLabel        = \App\Models\AgentDefaultProfile::roleLabel($role);
        $propLabel        = \App\Models\AgentDefaultProfile::propertyLabel($propertyType);
    @endphp

    {{-- Breadcrumb --}}
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('search.agents') }}">Browse Agents</a></li>
                <li class="breadcrumb-item active">Hire Request Submitted</li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-1">Hire Request Submitted</h4>
        <p class="text-muted" style="font-size:.93rem;">
            Your hire request has been recorded. Review the details below.
        </p>
    </div>

    {{-- Success banner --}}
    <div class="success-banner">
        <div class="success-banner-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div>
            <h4>Request Received</h4>
            <p>
                Your hire request for <strong>{{ $agentDisplayName }}</strong> has been submitted successfully.
                The agent will review your request and reach out to you directly using the contact information you provided.
                No agreement is finalized until both parties confirm the terms.
            </p>
        </div>
    </div>

    {{-- Agent summary --}}
    <div class="ack-section">
        <div class="ack-section-header"><i class="fa-solid fa-user-tie"></i> Agent</div>
        <div class="ack-section-body d-flex align-items-center gap-3">
            <x-avatar-img :avatar="$agent->avatar" alt="Agent avatar"
                 style="width:54px;height:54px;border-radius:50%;object-fit:cover;border:2px solid #c8e8ea;flex-shrink:0;" />
            <div>
                <div class="fw-bold" style="font-size:1.05rem;">{{ $agentDisplayName }}</div>
                <div class="text-muted small">
                    <span class="badge" style="background:#e8f7f7;color:#036b70;font-size:.75rem;">{{ $roleLabel }}</span>
                    <span class="badge ms-1" style="background:#f0f4ff;color:#4a5aaa;font-size:.75rem;">{{ $propLabel }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Client contact details --}}
    <div class="ack-section">
        <div class="ack-section-header"><i class="fa-solid fa-address-card"></i> Your Submitted Information</div>
        <div class="ack-section-body">
            <dl class="detail-grid">
                @if(!empty($contact['client_name']))
                <div>
                    <dt>Name</dt>
                    <dd>{{ $contact['client_name'] }}</dd>
                </div>
                @endif
                @if(!empty($contact['client_phone']))
                <div>
                    <dt>Phone</dt>
                    <dd>{{ $contact['client_phone'] }}</dd>
                </div>
                @endif
                @if(!empty($contact['client_email']))
                <div>
                    <dt>Email</dt>
                    <dd>{{ $contact['client_email'] }}</dd>
                </div>
                @endif

                {{-- Seller / Landlord: property address --}}
                @if(in_array($role, ['seller', 'landlord']) && !empty($contact['client_property_address']))
                <div style="grid-column: 1 / -1;">
                    <dt>Property Address</dt>
                    <dd>
                        @php
                            $addrParts = array_filter([
                                $contact['client_property_address'] ?? '',
                                $contact['client_property_city']    ?? '',
                                $contact['client_property_state']   ?? '',
                                $contact['client_property_zip']     ?? '',
                            ]);
                        @endphp
                        {{ implode(', ', $addrParts) }}
                    </dd>
                </div>
                @endif

                {{-- Buyer / Tenant: areas of interest --}}
                @if(in_array($role, ['buyer', 'tenant']) && !empty($contact['areas_of_interest']))
                <div style="grid-column: 1 / -1;">
                    <dt>Areas of Interest</dt>
                    <dd>{{ $contact['areas_of_interest'] }}</dd>
                </div>
                @endif

                {{-- Seller-specific fields --}}
                @if($role === 'seller')
                    @if(!empty($contact['desired_sale_price']))
                    <div>
                        <dt>Desired Sale Price</dt>
                        <dd>{{ $contact['desired_sale_price'] }}</dd>
                    </div>
                    @endif
                    @if(!empty($contact['timeline_to_sell']))
                    <div>
                        <dt>Timeline to Sell</dt>
                        <dd>{{ $contact['timeline_to_sell'] }}</dd>
                    </div>
                    @endif
                    @if(!empty($contact['motivation_level']))
                    <div>
                        <dt>Motivation Level</dt>
                        <dd>{{ $contact['motivation_level'] }}</dd>
                    </div>
                    @endif
                @endif

                {{-- Buyer-specific fields --}}
                @if($role === 'buyer')
                    @if(!empty($contact['target_purchase_price']))
                    <div>
                        <dt>Target Purchase Price</dt>
                        <dd>{{ $contact['target_purchase_price'] }}</dd>
                    </div>
                    @endif
                    @if(!empty($contact['timeline_to_purchase']))
                    <div>
                        <dt>Timeline to Purchase</dt>
                        <dd>{{ $contact['timeline_to_purchase'] }}</dd>
                    </div>
                    @endif
                    @if(!empty($contact['pre_approval_status']))
                    <div>
                        <dt>Pre-Approval Status</dt>
                        <dd>{{ $contact['pre_approval_status'] }}</dd>
                    </div>
                    @endif
                    @if(!empty($contact['cash_buyer']))
                    <div>
                        <dt>Cash Buyer</dt>
                        <dd>{{ $contact['cash_buyer'] }}</dd>
                    </div>
                    @endif
                    @if(!empty($contact['estimated_down_payment']))
                    <div>
                        <dt>Estimated Down Payment</dt>
                        <dd>{{ $contact['estimated_down_payment'] }}</dd>
                    </div>
                    @endif
                @endif

                {{-- Landlord-specific fields --}}
                @if($role === 'landlord')
                    @if(!empty($contact['desired_monthly_rent']))
                    <div>
                        <dt>Desired Monthly Rent</dt>
                        <dd>{{ $contact['desired_monthly_rent'] }}</dd>
                    </div>
                    @endif
                    @if(!empty($contact['availability_date']))
                    <div>
                        <dt>Availability Date</dt>
                        <dd>{{ $contact['availability_date'] }}</dd>
                    </div>
                    @endif
                    @if(!empty($contact['occupancy_status']))
                    <div>
                        <dt>Occupancy Status</dt>
                        <dd>{{ $contact['occupancy_status'] }}</dd>
                    </div>
                    @endif
                    @if(!empty($contact['flexibility']))
                    <div>
                        <dt>Flexibility</dt>
                        <dd>{{ $contact['flexibility'] }}</dd>
                    </div>
                    @endif
                @endif

                {{-- Tenant-specific fields --}}
                @if($role === 'tenant')
                    @if(!empty($contact['max_monthly_lease_price']))
                    <div>
                        <dt>Max Monthly Lease Price</dt>
                        <dd>{{ $contact['max_monthly_lease_price'] }}</dd>
                    </div>
                    @endif
                    @if(!empty($contact['desired_lease_length']))
                    <div>
                        <dt>Desired Lease Length</dt>
                        <dd>{{ $contact['desired_lease_length'] }}</dd>
                    </div>
                    @endif
                    @if(!empty($contact['move_in_date']))
                    <div>
                        <dt>Move-In Date</dt>
                        <dd>{{ $contact['move_in_date'] }}</dd>
                    </div>
                    @endif
                    @if(!empty($contact['number_of_occupants']))
                    <div>
                        <dt>Number of Occupants</dt>
                        <dd>{{ $contact['number_of_occupants'] }}</dd>
                    </div>
                    @endif
                    @if(!empty($contact['household_monthly_income']))
                    <div>
                        <dt>Household Monthly Income</dt>
                        <dd>{{ $contact['household_monthly_income'] }}</dd>
                    </div>
                    @endif
                @endif
            </dl>
        </div>
    </div>

    {{-- Client-requested custom services --}}
    @php $clientCustomServices = $submitted['client_custom_services'] ?? []; @endphp
    @if(!empty($clientCustomServices))
    <div class="ack-section">
        <div class="ack-section-header"><i class="fa-solid fa-pen-to-square"></i> Additional Services Requested by You</div>
        <div class="ack-section-body">
            <ul class="service-bullet-list">
                @foreach($clientCustomServices as $svc)
                <li>{{ $svc }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    {{-- Additionally requested services from counter flow --}}
    @php $clientRequestedServices = $submitted['client_requested_services'] ?? []; @endphp
    @if(!empty($clientRequestedServices))
    <div class="ack-section">
        <div class="ack-section-header"><i class="fa-solid fa-circle-plus"></i> Additionally Requested Services</div>
        <div class="ack-section-body">
            <p class="text-muted small mb-2">These services were additionally requested by you and are not part of the agent's standard offering.</p>
            <ul class="service-bullet-list">
                @foreach($clientRequestedServices as $svc)
                <li>{{ $svc }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    {{-- Additional notes / requests --}}
    @php $additionalRequested = $submitted['additional_requested'] ?? null; @endphp
    @if(!empty($additionalRequested))
    <div class="ack-section">
        <div class="ack-section-header"><i class="fa-solid fa-comment-dots"></i> Additional Notes</div>
        <div class="ack-section-body" style="font-size:.9rem;color:#1a1a1a;line-height:1.65;white-space:pre-line;">{{ $additionalRequested }}</div>
    </div>
    @endif

    {{-- Services section: "Your Selected Services" in counter flow, "Accepted Services" in accept flow --}}
    @php $isCounterSubmission = ($submitted['flow'] ?? 'accept') === 'counter'; @endphp
    @if(!empty($services) || !empty($otherServices))
    <div class="ack-section">
        <div class="ack-section-header"><i class="fa-solid fa-square-check"></i> {{ $isCounterSubmission ? 'Your Selected Services' : 'Accepted Services' }}</div>
        <div class="ack-section-body">
            @php $isFirstGroup = true; @endphp
            @foreach($groupedServices as $categoryLabel => $categoryServices)
                @if(!empty($categoryServices))
                <div style="margin-top: {{ $isFirstGroup ? '0' : '1rem' }};">
                    <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6c757d;margin-bottom:.5rem;">{{ $categoryLabel }}</div>
                    <ul class="service-bullet-list">
                        @foreach($categoryServices as $svc)
                        <li>{{ $svc }}</li>
                        @endforeach
                    </ul>
                </div>
                @php $isFirstGroup = false; @endphp
                @endif
            @endforeach
            @if(!empty($otherServices))
            <div class="mt-3">
                <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6c757d;margin-bottom:.5rem;">Additional Services</div>
                <ul class="service-bullet-list">
                    @foreach($otherServices as $svc)
                    <li>{{ $svc }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Compensation / Agency Agreement terms --}}
    @if(count($compRows) > 0)
    <div class="ack-section">
        <div class="ack-section-header">
            <i class="fa-solid fa-file-lines"></i>
            {{ $isCounterSubmission ? 'Your Proposed Broker Compensation &amp; Agency Agreement Terms' : 'Accepted Broker Compensation &amp; Agency Agreement Terms' }}
        </div>
        <div class="ack-section-body">
            <table class="comp-table">
                @foreach($compRows as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td>{{ $row['value'] }}</td>
                </tr>
                @endforeach
            </table>
        </div>
    </div>
    @endif

    {{-- Footer actions --}}
    <div class="d-flex align-items-center gap-3 flex-wrap mb-4">
        <a href="{{ url('/') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-house me-1"></i> Back to Home
        </a>
    </div>

</div>
</div>
@endsection
