@extends('layouts.main')

@push('styles')
<style>
    .counter-wrap {
        max-width: 860px;
        margin: 0 auto;
    }
    .counter-tab-nav .nav-link {
        color: #495057;
        font-weight: 600;
        font-size: .88rem;
        border-radius: 8px 8px 0 0;
        padding: .6rem 1.1rem;
    }
    .counter-tab-nav .nav-link.active {
        color: #049399;
        border-bottom-color: #fff;
        background: #fff;
        border-color: #dee2e6 #dee2e6 #fff;
    }
    .counter-tab-pane {
        background: #fff;
        border: 1px solid #dee2e6;
        border-top: none;
        border-radius: 0 0 10px 10px;
        padding: 1.5rem 1.75rem;
        margin-bottom: 1.5rem;
    }
    .counter-section-label {
        font-size: .78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #6c757d;
        margin-bottom: .6rem;
    }
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
    .service-check-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .service-check-list li label {
        display: flex;
        align-items: flex-start;
        gap: .55rem;
        cursor: pointer;
        font-size: .9rem;
        color: #1a1a1a;
    }
    .service-check-list li input[type="checkbox"] {
        margin-top: 3px;
        flex-shrink: 0;
        accent-color: #049399;
        width: 16px;
        height: 16px;
    }
    .counter-notice {
        background: #fff8e1;
        border: 1px solid #ffe082;
        border-radius: 8px;
        padding: 1rem 1.25rem;
        font-size: .9rem;
        color: #5d4037;
        line-height: 1.65;
        margin-bottom: 1.25rem;
    }
    .counter-notice strong { color: #e65100; }
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
    .tab-nav-btn {
        background: #f0fafa;
        border: 1px solid #c8e8ea;
        border-radius: 7px;
        padding: 9px 22px;
        font-weight: 600;
        font-size: .9rem;
        color: #049399;
        cursor: pointer;
        transition: background .15s;
    }
    .tab-nav-btn:hover { background: #e0f5f5; }
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
</style>
@endpush

@section('content')
<div class="buyerOfferContentDetails py-4">
<div class="container counter-wrap">

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
                <li class="breadcrumb-item active">Request Changes</li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-1">Request Changes / Counter Terms</h4>
        <p class="text-muted" style="font-size:.93rem;">
            Adjust the services you want, add any notes, and provide your contact details.
            The agent will review your counter request and respond directly.
        </p>
    </div>

    {{-- Flash errors --}}
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Agent summary strip --}}
    <div class="card mb-3" style="border-radius:10px;border-color:#dee2e6;">
        <div class="card-body d-flex align-items-center gap-3 py-3">
            <x-avatar-img :avatar="$agent->avatar" alt="Agent avatar"
                 style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid #c8e8ea;flex-shrink:0;" />
            <div>
                <div class="fw-bold" style="font-size:1rem;">{{ $agentDisplayName }}</div>
                <div class="text-muted small">
                    <span class="badge" style="background:#e8f7f7;color:#036b70;font-size:.75rem;">{{ $roleLabel }}</span>
                    <span class="badge ms-1" style="background:#f0f4ff;color:#4a5aaa;font-size:.75rem;">{{ $propLabel }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Counter notice --}}
    <div class="counter-notice">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>
        <strong>This is a counter request, not a final agreement.</strong>
        Uncheck any services you don't want, add your notes in the Additional Terms tab, then submit.
        No listing or bid will be created until both parties agree on terms.
    </div>

    <form method="POST"
          action="{{ route('hire.agent.direct.counter.submit', ['agentId' => $agent->id, 'role' => $role, 'propertyType' => $propertyType]) }}"
          id="counter-form"
          onsubmit="return counterFormSubmit(this)">
        @csrf
        <input type="hidden" name="_counter_nonce" value="{{ $pending['counter_nonce'] ?? '' }}">

        {{-- Tab navigation --}}
        <ul class="nav nav-tabs counter-tab-nav" id="counterTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-services-btn" data-bs-toggle="tab"
                        data-bs-target="#tab-services" type="button" role="tab">
                    <i class="fa-solid fa-square-check me-1"></i> Services
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-comp-btn" data-bs-toggle="tab"
                        data-bs-target="#tab-comp" type="button" role="tab">
                    <i class="fa-solid fa-file-lines me-1"></i> Comp Terms
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-notes-btn" data-bs-toggle="tab"
                        data-bs-target="#tab-notes" type="button" role="tab">
                    <i class="fa-solid fa-comment-dots me-1"></i> Additional Terms
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-client-btn" data-bs-toggle="tab"
                        data-bs-target="#tab-client" type="button" role="tab">
                    <i class="fa-solid fa-address-card me-1"></i> Your Details
                </button>
            </li>
        </ul>

        <div class="tab-content" id="counterTabContent">

            {{-- Tab 1: Services --}}
            <div class="tab-pane fade show active counter-tab-pane" id="tab-services" role="tabpanel">
                <p class="text-muted small mb-3">
                    All of the agent's services are selected by default.
                    Uncheck any service you do not want included in your request.
                </p>

                @php $isFirstGroup = true; @endphp
                @foreach($groupedAgentServices as $categoryLabel => $categoryServices)
                    @if(!empty($categoryServices))
                    <div style="margin-top: {{ $isFirstGroup ? '0' : '1.25rem' }};">
                        <div class="counter-section-label">{{ $categoryLabel }}</div>
                        <ul class="service-check-list">
                            @foreach($categoryServices as $svc)
                            <li>
                                <label>
                                    <input type="checkbox" name="services[]"
                                           value="{{ $svc }}"
                                           {{ in_array($svc, $selectedServices) ? 'checked' : '' }}>
                                    {{ $svc }}
                                </label>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @php $isFirstGroup = false; @endphp
                    @endif
                @endforeach

                @if(!empty($otherServices))
                <div class="mt-4">
                    <div class="counter-section-label">Additional Services</div>
                    <ul class="service-check-list">
                        @foreach($otherServices as $svc)
                        <li>
                            <label>
                                <input type="checkbox" name="other_services[]"
                                       value="{{ $svc }}"
                                       {{ in_array($svc, $selectedOtherServices) ? 'checked' : '' }}>
                                {{ $svc }}
                            </label>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div class="d-flex justify-content-end mt-4">
                    <button type="button" class="tab-nav-btn" onclick="switchTab('tab-comp-btn')">
                        Next: Comp Terms <i class="fa-solid fa-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>

            {{-- Tab 2: Comp Terms (read-only) --}}
            <div class="tab-pane fade counter-tab-pane" id="tab-comp" role="tabpanel">
                <p class="text-muted small mb-3">
                    These are the agent's proposed compensation and agency agreement terms.
                    They are shown here for reference only — to propose changes, use the Additional Terms tab.
                </p>
                @if(count($compRows) > 0)
                    <table class="comp-table">
                        @foreach($compRows as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td>{{ $row['value'] }}</td>
                        </tr>
                        @endforeach
                    </table>
                @else
                    <p class="text-muted small">No compensation terms have been specified by this agent.</p>
                @endif

                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="tab-nav-btn" onclick="switchTab('tab-services-btn')">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back: Services
                    </button>
                    <button type="button" class="tab-nav-btn" onclick="switchTab('tab-notes-btn')">
                        Next: Additional Terms <i class="fa-solid fa-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>

            {{-- Tab 3: Additional Terms --}}
            <div class="tab-pane fade counter-tab-pane" id="tab-notes" role="tabpanel">
                <p class="text-muted small mb-3">
                    Use this space to describe any changes you'd like to the compensation terms,
                    services, or any other conditions of the arrangement.
                </p>
                <div class="mb-3">
                    <label class="fw-bold form-label">Your Counter Terms / Notes <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea name="additional_terms" rows="7"
                              class="form-control @error('additional_terms') is-invalid @enderror"
                              maxlength="3000"
                              placeholder="e.g. I'd like to negotiate the commission rate, or I'd prefer a 60-day agreement instead of 90 days..."
                              >{{ old('additional_terms', $pending['additional_terms'] ?? '') }}</textarea>
                    @error('additional_terms')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="text-muted small mt-1">Maximum 3,000 characters.</div>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="tab-nav-btn" onclick="switchTab('tab-comp-btn')">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back: Comp Terms
                    </button>
                    <button type="button" class="tab-nav-btn" onclick="switchTab('tab-client-btn')">
                        Next: Your Details <i class="fa-solid fa-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>

            {{-- Tab 4: Client Details --}}
            <div class="tab-pane fade counter-tab-pane" id="tab-client" role="tabpanel">
                @if($role === 'seller')
                    @include('hire-agent-direct.client-details.seller')
                @elseif($role === 'buyer')
                    @include('hire-agent-direct.client-details.buyer')
                @elseif($role === 'landlord')
                    @include('hire-agent-direct.client-details.landlord')
                @else
                    @include('hire-agent-direct.client-details.tenant')
                @endif

                <div class="d-flex justify-content-between mt-2 mb-2">
                    <button type="button" class="tab-nav-btn" onclick="switchTab('tab-notes-btn')">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back: Additional Terms
                    </button>
                </div>
            </div>

        </div>

        {{-- Submit footer --}}
        <div class="d-flex align-items-center gap-3 flex-wrap mb-4 mt-2">
            <button type="submit" id="counter-submit-btn" class="submit-btn btn">
                <i class="fa-solid fa-arrow-right-arrow-left me-2"></i>Submit Counter Request
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
function switchTab(tabBtnId) {
    var btn = document.getElementById(tabBtnId);
    if (btn) btn.click();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function counterFormSubmit(form) {
    var btn = document.getElementById('counter-submit-btn');
    if (!btn || btn.disabled) return false;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Sending\u2026';
    return true;
}
</script>
@endsection
