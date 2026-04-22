@extends('layouts.main')

@push('styles')
<style>
    .hire-direct-wrap {
        max-width: 860px;
        margin: 0 auto;
    }
    .agent-card-header {
        background: #f8f9fa;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 24px;
        margin-bottom: 24px;
    }
    .agent-card-header h3 {
        font-size: 1.35rem;
        font-weight: 700;
        margin-bottom: 4px;
    }
    .agent-card-header .agent-meta {
        color: #6c757d;
        font-size: .9rem;
    }
    .section-label {
        font-weight: 600;
        font-size: .85rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #6c757d;
        margin-bottom: 8px;
    }
    .service-checkbox-list {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .service-checkbox-list .service-item {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 8px 14px;
        cursor: pointer;
        transition: border-color .15s, background .15s;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: .9rem;
    }
    .service-checkbox-list .service-item:has(input:checked) {
        border-color: #facd34;
        background: #fffdf0;
    }
    .service-checkbox-list .service-item input[type="checkbox"] {
        accent-color: #facd34;
    }
    .bio-block {
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 16px 20px;
        margin-bottom: 16px;
        font-size: .92rem;
        color: #333;
    }
    .unavailable-notice {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 24px;
    }
    .what-next-block {
        background: #f0f9f0;
        border: 1px solid #c3e6cb;
        border-radius: 8px;
        padding: 18px 22px;
        margin-bottom: 24px;
        font-size: .92rem;
    }
    .what-next-block h6 {
        font-weight: 700;
        color: #1a5c2a;
        margin-bottom: 10px;
    }
    .what-next-block ol {
        margin: 0;
        padding-left: 20px;
        color: #2d4f36;
        line-height: 1.7;
    }
    .confirm-btn {
        background: #facd34;
        border: none;
        border-radius: 6px;
        padding: 12px 36px;
        font-weight: 700;
        font-size: 1rem;
        color: #1a1a1a;
        transition: opacity .15s;
    }
    .confirm-btn:hover:not(:disabled) { opacity: .85; }
    .confirm-btn:disabled { opacity: .55; cursor: not-allowed; }
    .role-badge {
        display: inline-block;
        background: #facd34;
        color: #1a1a1a;
        font-size: .78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
        border-radius: 4px;
        padding: 2px 10px;
        margin-left: 8px;
        vertical-align: middle;
    }
</style>
@endpush

@section('content')
<div class="buyerOfferContentDetails py-4">
<div class="container hire-direct-wrap">

    {{-- Page Header — Fix 6: include agent name --}}
    @php
        $agentFullName = trim(($agent->first_name ?? '') . ' ' . ($agent->last_name ?? ''));
        $agentDisplayName = $agentFullName ?: ($agent->name ?? 'This Agent');
    @endphp
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('search.agents') }}">Browse Agents</a></li>
                <li class="breadcrumb-item active">Hire {{ $agentDisplayName }}</li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-1">
            Hire {{ $agentDisplayName }}
            <span class="role-badge">{{ \App\Models\AgentDefaultProfile::roleLabel($role) }}</span>
        </h4>
        <p class="text-muted small mb-0">
            You are reviewing this agent's proposed offer directly.
            Confirm below to send your hire request — nothing is final until both sides agree and sign.
        </p>
    </div>

    {{-- Flash messages --}}
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Agent summary card --}}
    <div class="agent-card-header">
        <div class="d-flex align-items-start gap-3">
            <div style="flex-shrink:0">
                <img src="{{ asset('images/avatar/'.($agent->avatar ?? 'default.png')) }}"
                     onerror="this.src='{{ asset('images/avatar/default.png') }}'"
                     alt="Agent avatar"
                     style="width:70px;height:70px;border-radius:50%;object-fit:cover;border:2px solid #dee2e6;">
            </div>
            <div>
                <h3>{{ $agentDisplayName }}
                    <span style="font-size:.85rem;font-weight:400;color:#6c757d;">
                        — {{ \App\Models\AgentDefaultProfile::roleLabel($role) }}
                    </span>
                </h3>
                <div class="agent-meta">
                    @if($agent->brokerage) {{ $agent->brokerage }}&nbsp;&bull;&nbsp; @endif
                    @if($agent->license_no) Lic. {{ $agent->license_no }}&nbsp;&bull;&nbsp; @endif
                    {{ $agent->email }}
                </div>
                <div class="mt-1">
                    <span class="badge bg-secondary" style="font-size:.75rem;">
                        {{ \App\Models\AgentDefaultProfile::propertyLabel($propertyType) }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Fix 1: Unavailable state — no preset OR empty services --}}
    @if(!$presetValid)
        <div class="unavailable-notice">
            @if(!$profile)
                <strong>Profile not available.</strong>
                {{ $agentDisplayName }} has not set up a hiring profile for
                <em>{{ \App\Models\AgentDefaultProfile::roleLabel($role) }}</em> /
                <em>{{ \App\Models\AgentDefaultProfile::propertyLabel($propertyType) }}</em>.
            @else
                <strong>Profile not ready.</strong>
                {{ $agentDisplayName }} has not finished setting up their services yet for this role.
            @endif
            <div class="mt-2 text-muted small">Please contact them directly or browse other agents.</div>
        </div>
        <a href="{{ route('search.agents') }}" class="btn btn-outline-secondary">← Back to Browse Agents</a>
    @else

        {{-- Agent overview --}}
        @if(!empty($mapped['bio']))
        <div class="mb-3">
            <div class="section-label">About This Agent</div>
            <div class="bio-block">{{ $mapped['bio'] }}</div>
        </div>
        @endif

        @if(!empty($mapped['why_hire_you']))
        <div class="mb-3">
            <div class="section-label">Why Hire Me</div>
            <div class="bio-block">{{ $mapped['why_hire_you'] }}</div>
        </div>
        @endif

        @if(!empty($mapped['marketing_plan']))
        <div class="mb-3">
            <div class="section-label">Marketing Plan</div>
            <div class="bio-block">{{ $mapped['marketing_plan'] }}</div>
        </div>
        @endif

        {{-- Fix 2 (frontend): disable button on submit; pass one-time token --}}
        <form method="POST"
              id="hire-direct-form"
              action="{{ route('hire.agent.direct.confirm', ['agentId' => $agent->id, 'role' => $role, 'propertyType' => $propertyType]) }}"
              onsubmit="return hireDirectSubmit(this)">
            @csrf
            <input type="hidden" name="_hire_token" value="{{ $submitToken }}">

            {{-- Services Section — only shown when presetValid (services is non-empty) --}}
            <div class="mb-4">
                <div class="section-label">Included Services</div>
                <p class="text-muted small mb-2">
                    These are the services {{ $agentDisplayName }} includes in their offer.
                    Uncheck any you do not need.
                </p>
                <div class="service-checkbox-list">
                    @foreach($agentServices as $svc)
                    <label class="service-item">
                        <input type="checkbox"
                               name="services[]"
                               value="{{ $svc }}"
                               checked>
                        {{ $svc }}
                    </label>
                    @endforeach
                </div>
                @error('services') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @error('services.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>

            {{-- Property Address --}}
            <div class="mb-4">
                <div class="section-label">Property Address <span class="text-danger">*</span></div>
                <p class="text-muted small mb-2">
                    Enter the address of the property this hire request relates to.
                </p>
                <input type="text"
                       name="address"
                       class="form-control @error('address') is-invalid @enderror"
                       placeholder="e.g. 123 Main St, Miami, FL 33101"
                       value="{{ old('address') }}"
                       required>
                @error('address')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            {{-- Additional Requested Services --}}
            <div class="mb-4">
                <div class="section-label">Additional Requests (Optional)</div>
                <p class="text-muted small mb-2">
                    Anything extra you'd like to note. These are requests only — the agent's offer
                    determines what is formally included.
                </p>
                <textarea name="additional_requested"
                          class="form-control @error('additional_requested') is-invalid @enderror"
                          rows="3"
                          placeholder="e.g. I'd also like weekly progress reports, staging consultation, etc.">{{ old('additional_requested') }}</textarea>
                @error('additional_requested')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            {{-- Fix 4: replaced "create a listing" wording --}}
            {{-- Fix 5: What happens next section --}}
            <div class="what-next-block mb-4">
                <h6>What happens next</h6>
                <ol>
                    <li>Your request is sent directly to {{ $agentDisplayName }} with their proposed terms.</li>
                    <li>You can review the offer and choose to <strong>accept</strong>, request changes with a <strong>counter</strong>, or <strong>decline</strong> — no obligation yet.</li>
                    <li>Once both sides agree on the terms, you will <strong>sign the agreement digitally</strong> to make it official.</li>
                </ol>
            </div>

            {{-- Submit --}}
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <button type="submit" id="hire-direct-submit" class="confirm-btn btn">
                    Send Hire Request to {{ $agentDisplayName }}
                </button>
                <a href="{{ route('search.agents') }}" class="btn btn-outline-secondary">
                    Cancel
                </a>
            </div>
        </form>
    @endif

</div>
</div>

{{-- Fix 2 (frontend): disable button immediately on submit to prevent double-click --}}
<script>
function hireDirectSubmit(form) {
    var btn = document.getElementById('hire-direct-submit');
    if (btn.disabled) {
        return false;
    }
    btn.disabled = true;
    btn.textContent = 'Sending…';
    return true;
}
</script>
@endsection
