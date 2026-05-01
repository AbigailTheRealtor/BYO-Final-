@extends('layouts.main')

@push('styles')
<style>
    .hire-direct-wrap {
        max-width: 860px;
        margin: 0 auto;
    }
    /* ── Owner preview banner ── */
    .owner-preview-banner {
        background: linear-gradient(90deg, #facd34 0%, #f5b800 100%);
        color: #1a1a1a;
        border-radius: 10px;
        padding: .9rem 1.25rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: .75rem;
        font-weight: 600;
        font-size: .92rem;
    }
    /* ── Page header ── */
    .page-intro h4 {
        font-size: 1.35rem;
        font-weight: 700;
        margin-bottom: .3rem;
    }
    .page-intro .page-subheading {
        color: #5a6a72;
        font-size: .93rem;
        line-height: 1.6;
        max-width: 680px;
    }
    /* ── Agent card ── */
    .agent-card-header {
        background: linear-gradient(135deg, #049399 0%, #036b70 100%);
        color: #fff;
        border-radius: 12px;
        padding: 1.5rem 1.75rem;
        margin-bottom: 1.5rem;
    }
    .agent-card-header h3 {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: .2rem;
        color: #fff;
    }
    .agent-card-header .agent-meta {
        font-size: .88rem;
        opacity: .88;
        line-height: 1.7;
    }
    .agent-card-header .role-pill {
        display: inline-block;
        background: rgba(255,255,255,.2);
        border-radius: 20px;
        padding: .15rem .7rem;
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        margin-top: .4rem;
    }
    /* ── Section chrome ── */
    .preview-section {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 10px;
        margin-bottom: 1.25rem;
        overflow: hidden;
    }
    .preview-section-header {
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
    .preview-section-header i { color: #049399; }
    .preview-section-body { padding: 1.2rem 1.4rem; }
    /* ── Service checkboxes ── */
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
        border-color: #049399;
        background: #f0fafa;
    }
    .service-checkbox-list .service-item input[type="checkbox"] {
        accent-color: #049399;
    }
    /* ── Text content blocks ── */
    .bio-block {
        background: #f8f9fa;
        border-left: 3px solid #049399;
        border-radius: 4px;
        padding: .85rem 1.1rem;
        font-size: .92rem;
        color: #333;
        line-height: 1.65;
    }
    /* ── Compensation table ── */
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
    /* ── Notice block ── */
    .process-notice {
        background: #f0fafa;
        border: 1px solid #c8e8ea;
        border-radius: 8px;
        padding: 1rem 1.25rem;
        font-size: .9rem;
        color: #1a3b3e;
        line-height: 1.65;
        margin-bottom: 1.25rem;
    }
    .process-notice strong { color: #049399; }
    /* ── Unavailable notice ── */
    .unavailable-notice {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 24px;
    }
    /* ── Submit button ── */
    .confirm-btn {
        background: #049399;
        border: none;
        border-radius: 7px;
        padding: 12px 36px;
        font-weight: 700;
        font-size: 1rem;
        color: #fff;
        transition: opacity .15s;
    }
    .confirm-btn:hover:not(:disabled) { opacity: .85; }
    .confirm-btn:disabled { opacity: .55; cursor: not-allowed; }
</style>
@endpush

@section('content')
<div class="buyerOfferContentDetails py-4">
<div class="container hire-direct-wrap">

    @php
        $agentFullName   = trim(($mapped['first_name'] ?? '') . ' ' . ($mapped['last_name'] ?? ''));
        $agentDisplayName = $agentFullName ?: ($agent->name ?? 'This Agent');
        $roleLabel        = \App\Models\AgentDefaultProfile::roleLabel($role);
        $propLabel        = \App\Models\AgentDefaultProfile::propertyLabel($propertyType);
        $agentBrokerage   = $mapped['brokerage']  ?? '';
        $agentLicense     = $mapped['license_no'] ?? '';
    @endphp

    {{-- ── Owner preview banner ─────────────────────────────────── --}}
    @if ($isOwnerPreview)
        <div class="owner-preview-banner">
            <i class="fa-solid fa-eye fa-lg"></i>
            <span>
                You are previewing your own Direct Hire page.
                Clients will use this page to start a hire request.
            </span>
        </div>
    @endif

    {{-- ── Breadcrumb + page heading ──────────────────────────────── --}}
    <div class="mb-4 page-intro">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('search.agents') }}">Browse Agents</a></li>
                <li class="breadcrumb-item active">Review Agent Terms</li>
            </ol>
        </nav>
        <h4>Review This Agent's Proposed Terms</h4>
        <p class="page-subheading">
            These services and terms are based on {{ $agentDisplayName }}'s saved preset.
            You can submit the request as-is or request changes after the Direct Hire request is created.
        </p>
    </div>

    {{-- Flash messages --}}
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ── Agent summary card ──────────────────────────────────────── --}}
    <div class="agent-card-header">
        <div class="d-flex align-items-center gap-3">
            <div style="flex-shrink:0">
                <x-avatar-img :avatar="$agent->avatar" alt="Agent avatar"
                     style="width:68px;height:68px;border-radius:50%;object-fit:cover;border:3px solid rgba(255,255,255,.4);" />
            </div>
            <div>
                <h3>{{ $agentDisplayName }}</h3>
                <div class="agent-meta">
                    @if($agentBrokerage)<i class="fa-solid fa-building me-1"></i>{{ $agentBrokerage }}<br>@endif
                    @if($agentLicense)<i class="fa-solid fa-id-card me-1"></i>License&nbsp;#{{ $agentLicense }}<br>@endif
                    <i class="fa-solid fa-envelope me-1"></i>{{ $agent->email }}
                </div>
                <div class="mt-2">
                    <span class="role-pill">{{ $roleLabel }}</span>
                    <span class="role-pill ms-1" style="background:rgba(255,255,255,.12);">{{ $propLabel }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Unavailable state ────────────────────────────────────────── --}}
    @if(!$presetValid)
        <div class="unavailable-notice">
            @if(!$profile)
                <strong>Profile not available.</strong>
                {{ $agentDisplayName }} has not set up a hiring profile for
                <em>{{ $roleLabel }}</em> / <em>{{ $propLabel }}</em>.
            @else
                <strong>Profile not ready.</strong>
                {{ $agentDisplayName }} has not finished setting up their services yet for this role.
            @endif
            <div class="mt-2 text-muted small">Please contact them directly or browse other agents.</div>
        </div>
        <a href="{{ route('search.agents') }}" class="btn btn-outline-secondary">← Back to Browse Agents</a>

    @else

        {{-- ── Agent overview sections ─────────────────────────────── --}}
        @if(!empty($mapped['bio']))
        <div class="preview-section">
            <div class="preview-section-header"><i class="fa-solid fa-user"></i> About This Agent</div>
            <div class="preview-section-body">
                <div class="bio-block">{{ $mapped['bio'] }}</div>
            </div>
        </div>
        @endif

        @if(!empty($mapped['why_hire_you']))
        <div class="preview-section">
            <div class="preview-section-header"><i class="fa-solid fa-star"></i> Why Hire Me</div>
            <div class="preview-section-body">
                <div class="bio-block">{{ $mapped['why_hire_you'] }}</div>
            </div>
        </div>
        @endif

        @if(!empty($mapped['marketing_plan']))
        <div class="preview-section">
            <div class="preview-section-header"><i class="fa-solid fa-chart-line"></i> Marketing Plan</div>
            <div class="preview-section-body">
                <div class="bio-block">{{ $mapped['marketing_plan'] }}</div>
            </div>
        </div>
        @endif

        {{-- ── FORM ───────────────────────────────────────────────── --}}
        <form method="POST"
              id="hire-direct-form"
              action="{{ route('hire.agent.direct.confirm', ['agentId' => $agent->id, 'role' => $role, 'propertyType' => $propertyType]) }}"
              onsubmit="return hireDirectSubmit(this)">
            @csrf
            <input type="hidden" name="_hire_token" value="{{ $submitToken }}">

            {{-- ── Services ──────────────────────────────────────── --}}
            <div class="preview-section">
                <div class="preview-section-header">
                    <i class="fa-solid fa-square-check"></i> Services Included in This Agent's Proposal
                </div>
                <div class="preview-section-body">
                    <p class="text-muted small mb-3">
                        These are the services {{ $agentDisplayName }} has included in their standing offer.
                        Uncheck any you do not need — only services from this preset can be selected.
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
                    @php
                        $agentServicesLower = array_map('mb_strtolower', $agentServices);
                        $filteredOtherServices = array_values(array_filter(
                            $otherServices,
                            fn($s) => is_string($s) && trim($s) !== ''
                                   && !in_array(mb_strtolower(trim($s)), $agentServicesLower, true)
                        ));
                    @endphp
                    @if(!empty($filteredOtherServices))
                    <div class="mt-3">
                        <div style="font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6c757d;margin-bottom:.5rem;">Additional Services</div>
                        <p class="text-muted small mb-2">These are custom services added by the agent. Uncheck any you do not need.</p>
                        <div class="service-checkbox-list">
                            @foreach($filteredOtherServices as $svc)
                            <label class="service-item">
                                <input type="checkbox"
                                       name="other_services[]"
                                       value="{{ $svc }}"
                                       checked>
                                {{ $svc }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    @error('services') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
                    @error('services.*') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
                </div>
            </div>

            {{-- ── Broker Compensation & Agency Terms preview ────── --}}
            @php
                $compRows = \App\Support\CompensationFormatter::formatPresetRows(
                    $role,
                    $propertyType ?? 'residential',
                    $mapped
                );
            @endphp
            @if(count($compRows) > 0)
            <div class="preview-section">
                <div class="preview-section-header">
                    <i class="fa-solid fa-file-lines"></i> Agent's Default Broker Compensation &amp; Agency Agreement Terms
                </div>
                <div class="preview-section-body">
                    <p class="text-muted small mb-3">
                        These are the Agent's default proposed terms. You may review them before submitting your hire request.
                        Final terms may be accepted, rejected, or countered through the platform.
                    </p>
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

            {{-- ── Property Address ───────────────────────────────── --}}
            <div class="preview-section">
                <div class="preview-section-header"><i class="fa-solid fa-map-marker"></i> Property Address</div>
                <div class="preview-section-body">
                    <p class="text-muted small mb-2">
                        Enter the address of the property this hire request relates to.
                    </p>
                    <input type="text"
                           name="address"
                           class="form-control @error('address') is-invalid @enderror"
                           placeholder="e.g. 123 Main St, Miami, FL 33101"
                           value="{{ old('address') }}"
                           @if($isOwnerPreview) disabled @endif
                           required>
                    @error('address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- ── Client Requested Services ────────────────────────── --}}
            <div class="preview-section">
                <div class="preview-section-header"><i class="fa-solid fa-list-plus"></i> Additional Services You'd Like to Request</div>
                <div class="preview-section-body">
                    <p class="text-muted small mb-2">
                        Request any additional services you'd like the Agent to consider.
                        These are not included unless agreed upon.
                    </p>
                    <textarea name="client_custom_services"
                              class="form-control @error('client_custom_services') is-invalid @enderror"
                              rows="4"
                              placeholder="Enter one service per line, e.g.:&#10;Provide virtual staging for the listing&#10;Coordinate with HOA for access"
                              @if($isOwnerPreview) disabled @endif>{{ old('client_custom_services') }}</textarea>
                    <div class="text-muted" style="font-size:.78rem;margin-top:.35rem;">Enter one service per line.</div>
                    @error('client_custom_services')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- ── Additional Services Requested ──────────────────── --}}
            <div class="preview-section">
                <div class="preview-section-header"><i class="fa-solid fa-plus-circle"></i> Additional Services Requested</div>
                <div class="preview-section-body">
                    <p class="text-muted small mb-2">
                        Optional. List any additional services you would like this agent to consider.
                        These are requests only — the agent's preset determines what is formally included.
                    </p>
                    <textarea name="additional_requested"
                              class="form-control @error('additional_requested') is-invalid @enderror"
                              rows="3"
                              placeholder="List any additional services you would like this Agent to consider."
                              @if($isOwnerPreview) disabled @endif>{{ old('additional_requested') }}</textarea>
                    @error('additional_requested')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- ── Process notice ─────────────────────────────────── --}}
            <div class="process-notice">
                <i class="fa-solid fa-circle-info me-2"></i>
                <strong>Submitting this request does not finalize an agreement.</strong>
                The agent will receive your request, and both parties may accept, counter, or reject terms
                before anything is finalized. Once both sides agree, you will sign the agreement digitally
                to make it official.
            </div>

            {{-- ── Submit / Owner preview state ──────────────────── --}}
            @if($isOwnerPreview)
                <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
                    <i class="fa-solid fa-eye fa-lg"></i>
                    <span>
                        You are previewing your own Direct Hire page.
                        Clients will use this page to start a hire request — the submit button is not active in preview mode.
                    </span>
                </div>
            @else
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <button type="submit" id="hire-direct-submit" class="confirm-btn btn">
                        <i class="fa-solid fa-handshake me-2"></i>Start Direct Hire Request
                    </button>
                    <a href="{{ route('search.agents') }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            @endif

        </form>
    @endif

</div>
</div>

<script>
function hireDirectSubmit(form) {
    var btn = document.getElementById('hire-direct-submit');
    if (!btn || btn.disabled) {
        return false;
    }
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Sending…';
    return true;
}
</script>
@endsection
