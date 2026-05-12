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
    .ack-notice {
        background: #f0fafa;
        border: 1px solid #c8e8ea;
        border-radius: 8px;
        padding: 1rem 1.25rem;
        font-size: .9rem;
        color: #1a3b3e;
        line-height: 1.65;
        margin-bottom: 1.25rem;
    }
    .ack-notice strong { color: #049399; }
    .submit-btn {
        background: #049399;
        border: none;
        border-radius: 7px;
        padding: 12px 36px;
        font-weight: 700;
        font-size: 1rem;
        color: #fff;
        transition: opacity .15s;
    }
    .submit-btn:hover:not(:disabled) { opacity: .85; }
    .submit-btn:disabled { opacity: .55; cursor: not-allowed; }
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
                <li class="breadcrumb-item">
                    <a href="{{ route('hire.agent.direct.preview', ['agentId' => $agent->id, 'role' => $role, 'propertyType' => $propertyType]) }}">
                        Review Agent Terms
                    </a>
                </li>
                <li class="breadcrumb-item active">Confirm Hire Request</li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-1">Confirm Your Hire Request</h4>
        <p class="text-muted" style="font-size:.93rem;">
            Review the accepted terms below, provide your contact information, and submit your hire request.
        </p>
    </div>

    {{-- Flash errors --}}
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

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

    {{-- Accepted compensation terms --}}
    @if(count($compRows) > 0)
    <div class="ack-section">
        <div class="ack-section-header"><i class="fa-solid fa-file-lines"></i> Accepted Broker Compensation &amp; Agency Agreement Terms</div>
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

    {{-- Accepted services --}}
    @if(!empty($services) || !empty($otherServices))
    <div class="ack-section">
        <div class="ack-section-header"><i class="fa-solid fa-square-check"></i> Accepted Services</div>
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

    {{-- Contact form --}}
    <form method="POST"
          action="{{ route('hire.agent.direct.acknowledge.submit', ['agentId' => $agent->id, 'role' => $role, 'propertyType' => $propertyType]) }}"
          onsubmit="return ackSubmit(this)">
        @csrf
        <input type="hidden" name="_ack_nonce" value="{{ $pending['ack_nonce'] ?? '' }}">

        @if($role === 'seller')
            @include('hire-agent-direct.client-details.seller')
        @elseif($role === 'buyer')
            @include('hire-agent-direct.client-details.buyer')
        @elseif($role === 'landlord')
            @include('hire-agent-direct.client-details.landlord')
        @else
            @include('hire-agent-direct.client-details.tenant')
        @endif

        <div class="ack-notice">
            <i class="fa-solid fa-circle-info me-2"></i>
            <strong>Submitting this request does not finalize an agreement.</strong>
            The agent will receive your request and both parties may accept, counter, or reject terms before anything is finalized.
        </div>

        <div class="d-flex align-items-center gap-3 flex-wrap mb-4">
            <button type="submit" id="ack-submit-btn" class="submit-btn btn">
                <i class="fa-solid fa-handshake me-2"></i>Submit Hire Request
            </button>
            <a href="{{ route('hire.agent.direct.preview', ['agentId' => $agent->id, 'role' => $role, 'propertyType' => $propertyType]) }}"
               class="btn btn-outline-secondary">
                ← Back to Review
            </a>
        </div>

    </form>

</div>
</div>

<script>
function ackSubmit(form) {
    var btn = document.getElementById('ack-submit-btn');
    if (!btn || btn.disabled) return false;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Sending\u2026';
    return true;
}
</script>
@endsection
