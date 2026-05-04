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
    /* ── Read-only service bullet lists ── */
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
    /* ── Profile field labels ── */
    .preview-field-label {
        font-size: .75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6c757d;
        margin-bottom: .2rem;
    }
    .preview-field-value {
        font-size: .92rem;
        color: #1a1a1a;
        line-height: 1.6;
    }
    /* ── Highlight grid ── */
    .preview-highlight-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 1rem;
    }
    .preview-highlight-card {
        background: #f0fafa;
        border: 1px solid #c8e8ea;
        border-radius: 8px;
        padding: .75rem 1rem;
        text-align: center;
    }
    .preview-highlight-card .phc-value {
        font-size: 1.4rem;
        font-weight: 700;
        color: #049399;
        line-height: 1.1;
    }
    .preview-highlight-card .phc-label {
        font-size: .75rem;
        color: #5a7a82;
        margin-top: .2rem;
    }
    /* ── Review block ── */
    .preview-review-block {
        background: #f8f9fa;
        border-left: 4px solid #049399;
        border-radius: 6px;
        padding: .9rem 1.1rem;
        font-size: .9rem;
        color: #333;
        line-height: 1.6;
        margin-bottom: .75rem;
    }
    .preview-review-block:last-child { margin-bottom: 0; }
    /* ── Video embed ── */
    .preview-video-wrap {
        position: relative;
        padding-bottom: 56.25%;
        height: 0;
        overflow: hidden;
        border-radius: 8px;
        background: #000;
    }
    .preview-video-wrap iframe {
        position: absolute;
        top: 0; left: 0;
        width: 100%; height: 100%;
        border: 0;
    }
    /* ── Link pill ── */
    .preview-link-pill {
        display: inline-block;
        background: #f0fafa;
        border: 1px solid #c8e8ea;
        border-radius: 20px;
        padding: .25rem .75rem;
        font-size: .82rem;
        color: #049399;
        text-decoration: none;
        margin: .2rem .2rem .2rem 0;
        word-break: break-all;
    }
    .preview-link-pill:hover { background: #e0f5f5; color: #036b70; }
    /* ── Upload thumbnail ── */
    .preview-upload-thumb {
        display: block;
        max-width: 120px;
        max-height: 120px;
        width: auto;
        height: auto;
        border-radius: 8px;
        border: 1px solid #c8e8ea;
        object-fit: cover;
        margin-bottom: .5rem;
    }
    /* ── Availability tag ── */
    .preview-avail-tag {
        display: inline-block;
        background: #e8f7f7;
        color: #036b70;
        border-radius: 20px;
        padding: .2rem .65rem;
        font-size: .82rem;
        font-weight: 600;
        margin: .15rem .15rem 0 0;
    }
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
    /* ── Submit buttons ── */
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
    .counter-btn {
        background: #fff;
        border: 2px solid #049399;
        border-radius: 7px;
        padding: 11px 28px;
        font-weight: 700;
        font-size: 1rem;
        color: #049399;
        transition: background .15s, color .15s, opacity .15s;
    }
    .counter-btn:hover:not(:disabled) { background: #f0fafa; }
    .counter-btn:disabled { opacity: .55; cursor: not-allowed; }
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

        @php
            $pd = $profile->profile_data ?? [];

            // Agent Overview extras
            $pdWhatSetsYouApart  = $mapped['what_sets_you_apart'] ?? '';
            $pdAdditionalDetails = $mapped['additional_details']  ?? '';

            // Credentials
            $pdYearLicensed        = $mapped['year_licensed']        ?? '';
            $pdBrokerageRelationship = $mapped['brokerage_relationship'] ?? '';

            // Quick Highlights
            $pdYearsExperience       = $pd['years_experience']           ?? '';
            $pdTransactions          = $pd['transactions_last_12_months'] ?? null;
            $pdAvgResponseTime       = $pd['avg_response_time']           ?? '';
            $pdIsFullTime            = $pd['is_full_time']                ?? '';

            // Areas Served
            $pdPrimaryAreas      = $pd['primary_areas_served']    ?? '';
            $pdCitiesServed      = $pd['cities_served']            ?? '';
            $pdCountiesServed    = $pd['counties_served']          ?? '';
            $pdNeighborhoods     = $pd['neighborhoods_served']     ?? '';
            $pdAreasNotes        = $pd['areas_notes']              ?? '';

            // Presentation & Links — normalize array fields (may be stored as newline string)
            $normalizeLinks = function($val) {
                if (is_array($val)) {
                    return array_values(array_filter(array_map('trim', $val)));
                }
                if (is_string($val) && $val !== '') {
                    return array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', $val)))));
                }
                return [];
            };
            $pdWebsiteLinks    = $normalizeLinks($mapped['website_link']   ?? ($pd['website_link']   ?? []));
            $pdSocialMedia     = $normalizeLinks($mapped['social_media']   ?? ($pd['social_media']   ?? []));
            $pdReviewsLinks    = $normalizeLinks($mapped['reviews_links']  ?? ($pd['reviews_links']  ?? []));
            $pdPresentationLink = $mapped['presentation_link'] ?? '';
            $pdBusinessCardLink = $mapped['business_card_link'] ?? '';
            $pdPresentationUploadRaw = $pd['presentation_upload_path'] ?? '';
            $pdBusinessCardUploadRaw = $pd['business_card_upload_path'] ?? '';
            // Resolve to empty string if the file no longer exists on disk
            $pdPresentationUpload = ($pdPresentationUploadRaw && \Illuminate\Support\Facades\Storage::disk('public')->exists($pdPresentationUploadRaw))
                ? $pdPresentationUploadRaw : '';
            $pdBusinessCardUpload = ($pdBusinessCardUploadRaw && \Illuminate\Support\Facades\Storage::disk('public')->exists($pdBusinessCardUploadRaw))
                ? $pdBusinessCardUploadRaw : '';
            // Helper: classify a file path as 'image', 'pdf', 'document', or 'unknown'
            $fileType = function($path) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) return 'image';
                if ($ext === 'pdf') return 'pdf';
                if (in_array($ext, ['doc', 'docx', 'ppt', 'pptx'])) return 'document';
                return 'unknown';
            };

            // Social Proof
            $pdReviews = array_values(array_filter([
                $pd['review_1'] ?? '',
                $pd['review_2'] ?? '',
                $pd['review_3'] ?? '',
            ], fn($r) => trim($r) !== ''));
            $pdAwards = $pd['awards_recognition'] ?? '';

            // Video Intro — use canonical VideoEmbedHelper for consistency
            $pdVideoUrl     = $pd['intro_video_url'] ?? '';
            $pdVideoCaption = $pd['video_caption']   ?? '';
            $pdEmbedUrl = $pdVideoUrl !== ''
                ? \App\Support\VideoEmbedHelper::getEmbedUrl($pdVideoUrl)
                : null;

            // Boolean-ish normalization helper (handles 'yes','1',1,true → true)
            $boolTrue = fn($v) => in_array(strtolower((string)$v), ['yes','1','true'], true);

            // Quick Highlights — normalize is_full_time
            $pdIsFullTimeNorm = '';
            if (!empty($pdIsFullTime)) {
                $pdIsFullTimeNorm = $boolTrue($pdIsFullTime) ? 'Full-Time' : 'Part-Time';
            }

            // Availability & Service Style — normalize boolean fields
            $pdAvailabilityStatus    = $pd['availability_status']      ?? '';
            $pdEveningsAvailableRaw  = $pd['evenings_available']       ?? '';
            $pdWeekendsAvailableRaw  = $pd['weekends_available']       ?? '';
            $pdEveningsAvailable     = $boolTrue($pdEveningsAvailableRaw);
            $pdWeekendsAvailable     = $boolTrue($pdWeekendsAvailableRaw);
            $pdHasFlexibleHours      = !empty($pdEveningsAvailableRaw) || !empty($pdWeekendsAvailableRaw);
            $pdCommunicationStyle    = $pd['communication_style']      ?? '';
            $pdPreferredContact      = $pd['preferred_contact_method']  ?? '';
        @endphp

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

        @if(!empty($pdWhatSetsYouApart))
        <div class="preview-section">
            <div class="preview-section-header"><i class="fa-solid fa-lightbulb"></i> What Sets Me Apart</div>
            <div class="preview-section-body">
                <div class="bio-block">{{ $pdWhatSetsYouApart }}</div>
            </div>
        </div>
        @endif

        @if(!empty($pdAdditionalDetails))
        <div class="preview-section">
            <div class="preview-section-header"><i class="fa-solid fa-circle-info"></i> Additional Details</div>
            <div class="preview-section-body">
                <div class="bio-block">{{ $pdAdditionalDetails }}</div>
            </div>
        </div>
        @endif

        {{-- ── Agent Credentials ────────────────────────────────────── --}}
        @php
            $hasCredentials = !empty($agentLicense) || !empty($mapped['nar_id'])
                || !empty($pdYearLicensed) || !empty($pdBrokerageRelationship);
        @endphp
        @if($hasCredentials)
        <div class="preview-section">
            <div class="preview-section-header"><i class="fa-solid fa-id-card"></i> Agent Credentials</div>
            <div class="preview-section-body">
                <div class="row g-3">
                    @if(!empty($agentLicense))
                    <div class="col-sm-6">
                        <div class="preview-field-label">License Number</div>
                        <div class="preview-field-value">{{ $agentLicense }}</div>
                    </div>
                    @endif
                    @if(!empty($mapped['nar_id']))
                    <div class="col-sm-6">
                        <div class="preview-field-label">NAR ID</div>
                        <div class="preview-field-value">{{ $mapped['nar_id'] }}</div>
                    </div>
                    @endif
                    @if(!empty($pdYearLicensed))
                    <div class="col-sm-6">
                        <div class="preview-field-label">Year Licensed</div>
                        <div class="preview-field-value">{{ $pdYearLicensed }}</div>
                    </div>
                    @endif
                    @if(!empty($pdBrokerageRelationship))
                    <div class="col-sm-6">
                        <div class="preview-field-label">Brokerage Relationship</div>
                        <div class="preview-field-value">{{ $pdBrokerageRelationship }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        {{-- ── Quick Highlights ─────────────────────────────────────── --}}
        @php
            $hasHighlights = !empty($pdYearsExperience)
                || ($pdTransactions !== null && $pdTransactions !== '')
                || !empty($pdAvgResponseTime)
                || !empty($pdIsFullTimeNorm);
        @endphp
        @if($hasHighlights)
        <div class="preview-section">
            <div class="preview-section-header"><i class="fa-solid fa-star"></i> Quick Highlights</div>
            <div class="preview-section-body">
                <div class="preview-highlight-grid">
                    @if(!empty($pdYearsExperience))
                    <div class="preview-highlight-card">
                        <div class="phc-value">{{ $pdYearsExperience }}</div>
                        <div class="phc-label">Years Experience</div>
                    </div>
                    @endif
                    @if($pdTransactions !== null && $pdTransactions !== '')
                    <div class="preview-highlight-card">
                        <div class="phc-value">{{ $pdTransactions }}</div>
                        <div class="phc-label">Transactions (Last 12 Mo.)</div>
                    </div>
                    @endif
                    @if(!empty($pdAvgResponseTime))
                    <div class="preview-highlight-card">
                        <div class="phc-value" style="font-size:1.05rem;">{{ $pdAvgResponseTime }}</div>
                        <div class="phc-label">Avg. Response Time</div>
                    </div>
                    @endif
                    @if(!empty($pdIsFullTimeNorm))
                    <div class="preview-highlight-card">
                        <div class="phc-value" style="font-size:1.05rem;">{{ $pdIsFullTimeNorm }}</div>
                        <div class="phc-label">Agent Status</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        {{-- ── Areas Served ──────────────────────────────────────────── --}}
        @php
            $hasAreas = !empty($pdPrimaryAreas) || !empty($pdCitiesServed)
                || !empty($pdCountiesServed) || !empty($pdNeighborhoods) || !empty($pdAreasNotes);
        @endphp
        @if($hasAreas)
        <div class="preview-section">
            <div class="preview-section-header"><i class="fa-solid fa-map-marker-alt"></i> Areas Served</div>
            <div class="preview-section-body">
                @if(!empty($pdPrimaryAreas))
                <div class="mb-3">
                    <div class="preview-field-label">Primary Areas</div>
                    <div class="preview-field-value">{{ $pdPrimaryAreas }}</div>
                </div>
                @endif
                @if(!empty($pdCitiesServed))
                <div class="mb-3">
                    <div class="preview-field-label">Cities</div>
                    <div class="preview-field-value">{{ $pdCitiesServed }}</div>
                </div>
                @endif
                @if(!empty($pdCountiesServed))
                <div class="mb-3">
                    <div class="preview-field-label">Counties</div>
                    <div class="preview-field-value">{{ $pdCountiesServed }}</div>
                </div>
                @endif
                @if(!empty($pdNeighborhoods))
                <div class="mb-3">
                    <div class="preview-field-label">Neighborhoods</div>
                    <div class="preview-field-value">{{ $pdNeighborhoods }}</div>
                </div>
                @endif
                @if(!empty($pdAreasNotes))
                <div class="mb-0">
                    <div class="preview-field-label">Additional Notes</div>
                    <div class="preview-field-value">{{ $pdAreasNotes }}</div>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- ── Presentation & Links ──────────────────────────────────── --}}
        @php
            $hasPresentationUpload = !empty($pdPresentationUpload);
            $hasBusinessCardUpload = !empty($pdBusinessCardUpload);
            $hasLinks = $hasPresentationUpload || !empty($pdPresentationLink)
                || $hasBusinessCardUpload || !empty($pdBusinessCardLink)
                || count($pdWebsiteLinks) > 0
                || count($pdSocialMedia) > 0
                || count($pdReviewsLinks) > 0;
        @endphp
        @if($hasLinks)
        <div class="preview-section">
            <div class="preview-section-header"><i class="fa-solid fa-link"></i> Presentation &amp; Links</div>
            <div class="preview-section-body">
                @if($hasPresentationUpload || !empty($pdPresentationLink))
                <div class="mb-3">
                    <div class="preview-field-label">Presentation</div>
                    @if($hasPresentationUpload)
                        @php $presType = $fileType($pdPresentationUpload); @endphp
                        @if($presType === 'image')
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($pdPresentationUpload) }}"
                               target="_blank" rel="noopener noreferrer">
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($pdPresentationUpload) }}"
                                     alt="Presentation" class="preview-upload-thumb">
                            </a>
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($pdPresentationUpload) }}"
                               target="_blank" rel="noopener noreferrer" class="preview-link-pill">
                                <i class="fa-solid fa-image me-1"></i>{{ basename($pdPresentationUpload) }}
                            </a>
                        @else
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($pdPresentationUpload) }}"
                               target="_blank" rel="noopener noreferrer" class="preview-link-pill">
                                @if($presType === 'pdf')
                                    <i class="fa-solid fa-file-pdf me-1"></i>
                                @else
                                    <i class="fa-solid fa-file-lines me-1"></i>
                                @endif
                                {{ basename($pdPresentationUpload) }}
                            </a>
                        @endif
                    @elseif(!empty($pdPresentationLink))
                        <a href="{{ $pdPresentationLink }}" target="_blank" rel="noopener noreferrer" class="preview-link-pill">
                            <i class="fa-solid fa-file-lines me-1"></i>View Presentation
                        </a>
                    @endif
                </div>
                @endif
                @if($hasBusinessCardUpload || !empty($pdBusinessCardLink))
                <div class="mb-3">
                    <div class="preview-field-label">Business Card / Headshot</div>
                    @if($hasBusinessCardUpload)
                        @php $bcType = $fileType($pdBusinessCardUpload); @endphp
                        @if($bcType === 'image')
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($pdBusinessCardUpload) }}"
                               target="_blank" rel="noopener noreferrer">
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($pdBusinessCardUpload) }}"
                                     alt="Business Card / Headshot" class="preview-upload-thumb">
                            </a>
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($pdBusinessCardUpload) }}"
                               target="_blank" rel="noopener noreferrer" class="preview-link-pill">
                                <i class="fa-solid fa-id-card me-1"></i>{{ basename($pdBusinessCardUpload) }}
                            </a>
                        @else
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($pdBusinessCardUpload) }}"
                               target="_blank" rel="noopener noreferrer" class="preview-link-pill">
                                @if($bcType === 'pdf')
                                    <i class="fa-solid fa-file-pdf me-1"></i>
                                @else
                                    <i class="fa-solid fa-id-card me-1"></i>
                                @endif
                                {{ basename($pdBusinessCardUpload) }}
                            </a>
                        @endif
                    @elseif(!empty($pdBusinessCardLink))
                        <a href="{{ $pdBusinessCardLink }}" target="_blank" rel="noopener noreferrer" class="preview-link-pill">
                            <i class="fa-solid fa-id-card me-1"></i>View Business Card
                        </a>
                    @endif
                </div>
                @endif
                @if(count($pdWebsiteLinks) > 0)
                <div class="mb-3">
                    <div class="preview-field-label">Website{{ count($pdWebsiteLinks) > 1 ? 's' : '' }}</div>
                    <div style="display:flex;flex-direction:column;gap:.25rem;">
                        @foreach($pdWebsiteLinks as $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="preview-link-pill" style="display:block;width:fit-content;">
                            <i class="fa-solid fa-globe me-1"></i>{{ $url }}
                        </a>
                        @endforeach
                    </div>
                </div>
                @endif
                @if(count($pdSocialMedia) > 0)
                <div class="mb-3">
                    <div class="preview-field-label">Social Media</div>
                    <div style="display:flex;flex-direction:column;gap:.25rem;">
                        @foreach($pdSocialMedia as $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="preview-link-pill" style="display:block;width:fit-content;">
                            <i class="fa-solid fa-share-alt me-1"></i>{{ $url }}
                        </a>
                        @endforeach
                    </div>
                </div>
                @endif
                @if(count($pdReviewsLinks) > 0)
                <div class="mb-0">
                    <div class="preview-field-label">Review Links</div>
                    <div style="display:flex;flex-direction:column;gap:.25rem;">
                        @foreach($pdReviewsLinks as $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="preview-link-pill" style="display:block;width:fit-content;">
                            <i class="fa-solid fa-star me-1"></i>{{ $url }}
                        </a>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- ── Social Proof ──────────────────────────────────────────── --}}
        @if(count($pdReviews) > 0 || !empty($pdAwards))
        <div class="preview-section">
            <div class="preview-section-header"><i class="fa-solid fa-quote-left"></i> Social Proof</div>
            <div class="preview-section-body">
                @foreach($pdReviews as $review)
                <div class="preview-review-block">
                    <i class="fa-solid fa-quote-left text-muted me-2" style="font-size:.75rem;"></i>{{ $review }}
                </div>
                @endforeach
                @if(!empty($pdAwards))
                <div class="{{ count($pdReviews) > 0 ? 'mt-3' : '' }}">
                    <div class="preview-field-label">Awards &amp; Recognition</div>
                    <div class="preview-field-value">{{ $pdAwards }}</div>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- ── Video Intro ───────────────────────────────────────────── --}}
        @if(!empty($pdVideoUrl))
        <div class="preview-section">
            <div class="preview-section-header"><i class="fa-solid fa-play-circle"></i> Video Intro</div>
            <div class="preview-section-body">
                @if($pdEmbedUrl)
                    <div class="preview-video-wrap">
                        <iframe src="{{ $pdEmbedUrl }}"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen></iframe>
                    </div>
                @else
                    <a href="{{ $pdVideoUrl }}" target="_blank" rel="noopener noreferrer" class="preview-link-pill">
                        <i class="fa-solid fa-external-link me-1"></i>Watch Intro Video
                    </a>
                @endif
                @if(!empty($pdVideoCaption))
                    <p class="text-muted small mt-2 mb-0">{{ $pdVideoCaption }}</p>
                @endif
            </div>
        </div>
        @endif

        {{-- ── Availability & Service Style ─────────────────────────── --}}
        @php
            $hasAvail = !empty($pdAvailabilityStatus) || $pdHasFlexibleHours
                || !empty($pdCommunicationStyle) || !empty($pdPreferredContact);
        @endphp
        @if($hasAvail)
        <div class="preview-section">
            <div class="preview-section-header"><i class="fa-solid fa-calendar-check"></i> Availability &amp; Service Style</div>
            <div class="preview-section-body">
                <div class="row g-3">
                    @if(!empty($pdAvailabilityStatus))
                    <div class="col-sm-6">
                        <div class="preview-field-label">Availability</div>
                        <span class="preview-avail-tag"><i class="fa-solid fa-circle-check me-1"></i>{{ $pdAvailabilityStatus }}</span>
                    </div>
                    @endif
                    @if(!empty($pdEveningsAvailableRaw))
                    <div class="col-sm-6">
                        <div class="preview-field-label">Evenings Available</div>
                        <div class="preview-field-value">{{ $pdEveningsAvailable ? 'Yes' : 'No' }}</div>
                    </div>
                    @endif
                    @if(!empty($pdWeekendsAvailableRaw))
                    <div class="col-sm-6">
                        <div class="preview-field-label">Weekends Available</div>
                        <div class="preview-field-value">{{ $pdWeekendsAvailable ? 'Yes' : 'No' }}</div>
                    </div>
                    @endif
                    @if(!empty($pdCommunicationStyle))
                    <div class="col-sm-6">
                        <div class="preview-field-label">Communication Style</div>
                        <div class="preview-field-value">{{ $pdCommunicationStyle }}</div>
                    </div>
                    @endif
                    @if(!empty($pdPreferredContact))
                    <div class="col-sm-6">
                        <div class="preview-field-label">Preferred Contact</div>
                        <div class="preview-field-value">{{ $pdPreferredContact }}</div>
                    </div>
                    @endif
                </div>
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
            <input type="hidden" name="intent" id="hire-intent" value="accept">

            {{-- ── Services (read-only bullet list) ──────────────── --}}
            <div class="preview-section">
                <div class="preview-section-header">
                    <i class="fa-solid fa-square-check"></i> Services Included in This Agent's Proposal
                </div>
                <div class="preview-section-body">
                    @php $isFirstGroup = true; @endphp
                    @foreach($groupedAgentServices as $categoryLabel => $categoryServices)
                        @if(!empty($categoryServices))
                        <div style="margin-top: {{ $isFirstGroup ? '0' : '1.1rem' }};">
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
                        <ul class="service-bullet-list">
                            @foreach($filteredOtherServices as $svc)
                            <li>{{ $svc }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
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
                    <p class="text-muted small mt-3 mb-0">
                        These are the Agent's proposed terms. To request changes to services, compensation, or agreement terms, select "Request Changes / Counter Terms".
                    </p>
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
                    <button type="submit"
                            id="hire-direct-submit"
                            class="confirm-btn btn"
                            onclick="document.getElementById('hire-intent').value='accept';">
                        <i class="fa-solid fa-handshake me-2"></i>Accept &amp; Submit Hire Request
                    </button>
                    <button type="submit"
                            id="hire-direct-counter"
                            class="counter-btn btn"
                            onclick="document.getElementById('hire-intent').value='counter';">
                        <i class="fa-solid fa-pen-to-square me-2"></i>Request Changes / Counter Terms
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
    var primaryBtn = document.getElementById('hire-direct-submit');
    if (!primaryBtn || primaryBtn.disabled) {
        return false;
    }
    var intent = document.getElementById('hire-intent').value;
    var submitBtns = form.querySelectorAll('button[type="submit"]');
    submitBtns.forEach(function(b) { b.disabled = true; });
    if (intent === 'counter') {
        var counterBtn = document.getElementById('hire-direct-counter');
        if (counterBtn) {
            counterBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Sending\u2026';
        }
    } else {
        primaryBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Sending\u2026';
    }
    return true;
}
</script>
@endsection
