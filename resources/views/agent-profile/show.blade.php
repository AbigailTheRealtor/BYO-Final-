@extends('layouts.main')

@push('styles')
<style>
    .agent-profile-wrap {
        max-width: 900px;
        margin: 0 auto;
    }
    .preview-banner {
        background: linear-gradient(90deg, #facd34 0%, #f5b800 100%);
        color: #1a1a1a;
        border-radius: 10px;
        padding: .85rem 1.25rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: .75rem;
        font-weight: 600;
        font-size: .92rem;
    }
    .preview-banner i { font-size: 1.1rem; }
    .profile-hero {
        background: linear-gradient(135deg, #049399 0%, #036b70 100%);
        color: #fff;
        border-radius: 12px;
        padding: 1.75rem 2rem;
        margin-bottom: 1.75rem;
        display: flex;
        align-items: flex-start;
        gap: 1.5rem;
    }
    .profile-hero-avatar img {
        width: 90px;
        height: 90px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid rgba(255,255,255,.4);
        flex-shrink: 0;
    }
    .profile-hero-info h1 {
        font-size: 1.55rem;
        font-weight: 700;
        margin: 0 0 .25rem;
    }
    .profile-hero-info .hero-meta {
        opacity: .85;
        font-size: .92rem;
        line-height: 1.7;
    }
    .profile-section {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 10px;
        margin-bottom: 1.4rem;
        overflow: hidden;
    }
    .profile-section-header {
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        padding: .75rem 1.25rem;
        font-weight: 700;
        font-size: .9rem;
        display: flex;
        align-items: center;
        gap: .5rem;
        color: #333;
    }
    .profile-section-header i {
        color: #049399;
        font-size: .95rem;
    }
    .profile-section-body {
        padding: 1.25rem 1.4rem;
    }
    .profile-field-label {
        font-size: .75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6c757d;
        margin-bottom: .2rem;
    }
    .profile-field-value {
        font-size: .92rem;
        color: #1a1a1a;
        line-height: 1.6;
    }
    .highlight-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
    }
    .highlight-card {
        background: #f0fafa;
        border: 1px solid #c8e8ea;
        border-radius: 8px;
        padding: .75rem 1rem;
        text-align: center;
    }
    .highlight-card .hc-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #049399;
        line-height: 1.1;
    }
    .highlight-card .hc-label {
        font-size: .75rem;
        color: #5a7a82;
        margin-top: .2rem;
    }
    .review-block {
        background: #f8f9fa;
        border-left: 4px solid #049399;
        border-radius: 6px;
        padding: .9rem 1.1rem;
        font-size: .9rem;
        color: #333;
        line-height: 1.6;
        margin-bottom: .75rem;
    }
    .review-block:last-child { margin-bottom: 0; }
    .video-embed-wrap {
        position: relative;
        padding-bottom: 56.25%;
        height: 0;
        overflow: hidden;
        border-radius: 8px;
        background: #000;
    }
    .video-embed-wrap iframe {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        border: 0;
    }
    .hire-btn-grid {
        display: flex;
        flex-wrap: wrap;
        gap: .75rem;
    }
    .hire-btn {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        background: #049399;
        color: #fff;
        border-radius: 7px;
        padding: .6rem 1.2rem;
        font-weight: 600;
        font-size: .9rem;
        text-decoration: none;
        transition: background .15s;
    }
    .hire-btn:hover {
        background: #036b70;
        color: #fff;
    }
    .hire-btn .hire-role-badge {
        font-size: .72rem;
        background: rgba(255,255,255,.2);
        border-radius: 4px;
        padding: .1rem .4rem;
    }
    .hire-picker-wrap {
        display: inline-block;
        position: relative;
    }
    .hire-picker-wrap > summary {
        list-style: none;
        cursor: pointer;
    }
    .hire-picker-wrap > summary::-webkit-details-marker { display: none; }
    .hire-picker-options {
        position: absolute;
        top: calc(100% + .35rem);
        left: 0;
        background: #fff;
        border: 1px solid #c8e8ea;
        border-radius: 8px;
        box-shadow: 0 4px 14px rgba(0,0,0,.1);
        min-width: 220px;
        z-index: 100;
        padding: .35rem 0;
    }
    .hire-picker-option {
        display: block;
        padding: .5rem 1rem;
        font-size: .88rem;
        font-weight: 600;
        color: #036b70;
        text-decoration: none;
        white-space: nowrap;
        transition: background .12s;
    }
    .hire-picker-option:hover {
        background: #f0fafa;
        color: #024e52;
    }
    .hire-owner-note {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        background: #f8f9fa;
        border: 1px dashed #ced4da;
        border-radius: 7px;
        padding: .55rem 1.1rem;
        font-size: .88rem;
        color: #6c757d;
        font-style: italic;
    }
    .link-pill {
        display: inline-block;
        background: #f0fafa;
        border: 1px solid #c8e8ea;
        border-radius: 20px;
        padding: .25rem .75rem;
        font-size: .82rem;
        color: #049399;
        text-decoration: none;
        margin: .2rem;
        word-break: break-all;
    }
    .link-pill:hover { background: #e0f5f5; color: #036b70; }
    .avail-tag {
        display: inline-block;
        background: #e8f7f7;
        color: #036b70;
        border-radius: 20px;
        padding: .2rem .65rem;
        font-size: .82rem;
        font-weight: 600;
        margin: .15rem;
    }
</style>
@endpush

@section('content')
<div class="agent-profile-wrap py-4 px-3">

    @if ($isOwnerPreview)
        <div class="preview-banner">
            <i class="fa fa-eye"></i>
            <span>Preview Mode — This is how your public profile appears to clients.</span>
            <a href="{{ route('agent.presets.index') }}" class="btn btn-sm btn-dark ms-auto">
                <i class="fa fa-arrow-left me-1"></i>Back to Presets
            </a>
        </div>
    @endif

    @php
        $agentFullName = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        $agentDisplayName = $agentFullName ?: ($agent->name ?? 'Agent');
    @endphp

    {{-- ── AGENT OVERVIEW ────────────────────────────────────────── --}}
    <div class="profile-hero">
        <div class="profile-hero-avatar">
            <img src="{{ asset('images/avatar/'.($agent->avatar ?? 'default.png')) }}"
                 onerror="this.src='{{ asset('images/avatar/default.png') }}'"
                 alt="{{ $agentDisplayName }}">
        </div>
        <div class="profile-hero-info">
            <h1>{{ $agentDisplayName }}</h1>
            <div class="hero-meta">
                @if (!empty($data['brokerage']))
                    <span><i class="fa fa-building me-1"></i>{{ $data['brokerage'] }}</span><br>
                @endif
                @if (!empty($data['license_no']))
                    <span><i class="fa fa-id-card-o me-1"></i>License #{{ $data['license_no'] }}</span><br>
                @endif
                @if (!empty($data['bio']))
                    <span style="margin-top:.5rem;display:block;opacity:.9;">{{ Str::limit($data['bio'], 180) }}</span>
                @endif
            </div>
        </div>
    </div>

    @if (!empty($data['why_hire_you']) || !empty($data['what_sets_you_apart']))
        <div class="profile-section">
            <div class="profile-section-header">
                <i class="fa fa-user"></i> About This Agent
            </div>
            <div class="profile-section-body">
                @if (!empty($data['bio']))
                    <div class="mb-3">
                        <div class="profile-field-label">Bio</div>
                        <div class="profile-field-value">{{ $data['bio'] }}</div>
                    </div>
                @endif
                @if (!empty($data['why_hire_you']))
                    <div class="mb-3">
                        <div class="profile-field-label">Why Hire Me</div>
                        <div class="profile-field-value">{{ $data['why_hire_you'] }}</div>
                    </div>
                @endif
                @if (!empty($data['what_sets_you_apart']))
                    <div class="mb-0">
                        <div class="profile-field-label">What Sets Me Apart</div>
                        <div class="profile-field-value">{{ $data['what_sets_you_apart'] }}</div>
                    </div>
                @endif
            </div>
        </div>
    @elseif (!empty($data['bio']))
        <div class="profile-section">
            <div class="profile-section-header"><i class="fa fa-user"></i> About This Agent</div>
            <div class="profile-section-body">
                <div class="profile-field-value">{{ $data['bio'] }}</div>
            </div>
        </div>
    @endif

    {{-- ── QUICK HIGHLIGHTS ────────────────────────────────────────── --}}
    @php
        $hasHighlights = !empty($data['years_experience'])
            || isset($data['transactions_last_12_months']) && $data['transactions_last_12_months'] !== null && $data['transactions_last_12_months'] !== ''
            || !empty($data['avg_response_time'])
            || !empty($data['is_full_time']);
    @endphp
    @if ($hasHighlights)
        <div class="profile-section">
            <div class="profile-section-header"><i class="fa fa-star"></i> Quick Highlights</div>
            <div class="profile-section-body">
                <div class="highlight-grid">
                    @if (!empty($data['years_experience']))
                        <div class="highlight-card">
                            <div class="hc-value">{{ $data['years_experience'] }}</div>
                            <div class="hc-label">Years Experience</div>
                        </div>
                    @endif
                    @if (isset($data['transactions_last_12_months']) && $data['transactions_last_12_months'] !== null && $data['transactions_last_12_months'] !== '')
                        <div class="highlight-card">
                            <div class="hc-value">{{ $data['transactions_last_12_months'] }}</div>
                            <div class="hc-label">Transactions (Last 12 Mo.)</div>
                        </div>
                    @endif
                    @if (!empty($data['avg_response_time']))
                        <div class="highlight-card">
                            <div class="hc-value" style="font-size:1.1rem;">{{ $data['avg_response_time'] }}</div>
                            <div class="hc-label">Avg. Response Time</div>
                        </div>
                    @endif
                    @if (!empty($data['is_full_time']))
                        <div class="highlight-card">
                            <div class="hc-value" style="font-size:1.1rem;">{{ $data['is_full_time'] === 'Yes' ? 'Full-Time' : $data['is_full_time'] }}</div>
                            <div class="hc-label">Agent Status</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- ── CREDENTIALS ─────────────────────────────────────────────── --}}
    @php
        $hasCredentials = !empty($data['license_no']) || !empty($data['nar_id'])
            || !empty($data['year_licensed']) || !empty($data['brokerage_relationship']);
    @endphp
    @if ($hasCredentials)
        <div class="profile-section">
            <div class="profile-section-header"><i class="fa fa-id-card-o"></i> Credentials</div>
            <div class="profile-section-body">
                <div class="row g-3">
                    @if (!empty($data['license_no']))
                        <div class="col-sm-6">
                            <div class="profile-field-label">License Number</div>
                            <div class="profile-field-value">{{ $data['license_no'] }}</div>
                        </div>
                    @endif
                    @if (!empty($data['nar_id']))
                        <div class="col-sm-6">
                            <div class="profile-field-label">NAR ID</div>
                            <div class="profile-field-value">{{ $data['nar_id'] }}</div>
                        </div>
                    @endif
                    @if (!empty($data['year_licensed']))
                        <div class="col-sm-6">
                            <div class="profile-field-label">Year Licensed</div>
                            <div class="profile-field-value">{{ $data['year_licensed'] }}</div>
                        </div>
                    @endif
                    @if (!empty($data['brokerage_relationship']))
                        <div class="col-sm-6">
                            <div class="profile-field-label">Brokerage Relationship</div>
                            <div class="profile-field-value">{{ $data['brokerage_relationship'] }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- ── AREAS SERVED ─────────────────────────────────────────────── --}}
    @php
        $hasAreas = !empty($data['primary_areas_served']) || !empty($data['cities_served'])
            || !empty($data['counties_served']) || !empty($data['neighborhoods_served'])
            || !empty($data['areas_notes']);
    @endphp
    @if ($hasAreas)
        <div class="profile-section">
            <div class="profile-section-header"><i class="fa fa-map-marker"></i> Areas Served</div>
            <div class="profile-section-body">
                @if (!empty($data['primary_areas_served']))
                    <div class="mb-3">
                        <div class="profile-field-label">Primary Areas</div>
                        <div class="profile-field-value">{{ $data['primary_areas_served'] }}</div>
                    </div>
                @endif
                @if (!empty($data['cities_served']))
                    <div class="mb-3">
                        <div class="profile-field-label">Cities</div>
                        <div class="profile-field-value">{{ $data['cities_served'] }}</div>
                    </div>
                @endif
                @if (!empty($data['counties_served']))
                    <div class="mb-3">
                        <div class="profile-field-label">Counties</div>
                        <div class="profile-field-value">{{ $data['counties_served'] }}</div>
                    </div>
                @endif
                @if (!empty($data['neighborhoods_served']))
                    <div class="mb-3">
                        <div class="profile-field-label">Neighborhoods</div>
                        <div class="profile-field-value">{{ $data['neighborhoods_served'] }}</div>
                    </div>
                @endif
                @if (!empty($data['areas_notes']))
                    <div class="mb-0">
                        <div class="profile-field-label">Additional Notes</div>
                        <div class="profile-field-value">{{ $data['areas_notes'] }}</div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ── SOCIAL PROOF ─────────────────────────────────────────────── --}}
    @php
        $reviews = array_filter([
            $data['review_1'] ?? '',
            $data['review_2'] ?? '',
            $data['review_3'] ?? '',
        ]);
        $hasProof = count($reviews) > 0 || !empty($data['awards_recognition']);
    @endphp
    @if ($hasProof)
        <div class="profile-section">
            <div class="profile-section-header"><i class="fa fa-quote-left"></i> Social Proof</div>
            <div class="profile-section-body">
                @foreach ($reviews as $review)
                    <div class="review-block">
                        <i class="fa fa-quote-left text-muted me-2" style="font-size:.75rem;"></i>{{ $review }}
                    </div>
                @endforeach
                @if (!empty($data['awards_recognition']))
                    <div class="{{ count($reviews) > 0 ? 'mt-3' : '' }}">
                        <div class="profile-field-label">Awards &amp; Recognition</div>
                        <div class="profile-field-value">{{ $data['awards_recognition'] }}</div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ── VIDEO INTRO ──────────────────────────────────────────────── --}}
    @if (!empty($data['intro_video_url']))
        @php
            $videoUrl = $data['intro_video_url'];
            $isYouTube = str_contains($videoUrl, 'youtube.com') || str_contains($videoUrl, 'youtu.be');
            $isVimeo   = str_contains($videoUrl, 'vimeo.com');
            $embedUrl  = null;
            if ($isYouTube) {
                preg_match('/(?:v=|youtu\.be\/)([A-Za-z0-9_\-]+)/', $videoUrl, $m);
                if (!empty($m[1])) {
                    $embedUrl = 'https://www.youtube.com/embed/' . $m[1];
                }
            } elseif ($isVimeo) {
                preg_match('/vimeo\.com\/(\d+)/', $videoUrl, $m);
                if (!empty($m[1])) {
                    $embedUrl = 'https://player.vimeo.com/video/' . $m[1];
                }
            }
        @endphp
        <div class="profile-section">
            <div class="profile-section-header"><i class="fa fa-play-circle"></i> Video Intro</div>
            <div class="profile-section-body">
                @if ($embedUrl)
                    <div class="video-embed-wrap">
                        <iframe src="{{ $embedUrl }}"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen></iframe>
                    </div>
                @else
                    <a href="{{ $videoUrl }}" target="_blank" rel="noopener noreferrer" class="link-pill">
                        <i class="fa fa-external-link me-1"></i>Watch Intro Video
                    </a>
                @endif
                @if (!empty($data['video_caption']))
                    <p class="text-muted small mt-2 mb-0">{{ $data['video_caption'] }}</p>
                @endif
            </div>
        </div>
    @endif

    {{-- ── PRESENTATION & LINKS ─────────────────────────────────────── --}}
    @php
        $hasLinks = !empty($data['presentation_link']) || !empty($data['business_card_link'])
            || !empty($data['website_link']) || !empty($data['social_media']);
    @endphp
    @if ($hasLinks)
        <div class="profile-section">
            <div class="profile-section-header"><i class="fa fa-link"></i> Presentation &amp; Links</div>
            <div class="profile-section-body">
                @if (!empty($data['presentation_link']))
                    <div class="mb-2">
                        <div class="profile-field-label">Presentation</div>
                        <a href="{{ $data['presentation_link'] }}" target="_blank" rel="noopener noreferrer" class="link-pill">
                            <i class="fa fa-file-text-o me-1"></i>View Presentation
                        </a>
                    </div>
                @endif
                @if (!empty($data['business_card_link']))
                    <div class="mb-2">
                        <div class="profile-field-label">Business Card / Headshot</div>
                        <a href="{{ $data['business_card_link'] }}" target="_blank" rel="noopener noreferrer" class="link-pill">
                            <i class="fa fa-id-card-o me-1"></i>View
                        </a>
                    </div>
                @endif
                @if (!empty($data['website_link']))
                    <div class="mb-2">
                        <div class="profile-field-label">Website{{ count($data['website_link']) > 1 ? 's' : '' }}</div>
                        <div>
                            @foreach ($data['website_link'] as $url)
                                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="link-pill">
                                    <i class="fa fa-globe me-1"></i>{{ $url }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if (!empty($data['social_media']))
                    <div class="mb-0">
                        <div class="profile-field-label">Social Media</div>
                        <div>
                            @foreach ($data['social_media'] as $url)
                                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="link-pill">
                                    <i class="fa fa-share-alt me-1"></i>{{ $url }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ── AVAILABILITY / SERVICE STYLE ─────────────────────────────── --}}
    @php
        $hasAvail = !empty($data['availability_status']) || !empty($data['evenings_available'])
            || !empty($data['weekends_available']) || !empty($data['communication_style'])
            || !empty($data['preferred_contact_method']);
    @endphp
    @if ($hasAvail)
        <div class="profile-section">
            <div class="profile-section-header"><i class="fa fa-calendar"></i> Availability &amp; Service Style</div>
            <div class="profile-section-body">
                <div class="row g-3">
                    @if (!empty($data['availability_status']))
                        <div class="col-sm-6">
                            <div class="profile-field-label">Availability</div>
                            <span class="avail-tag"><i class="fa fa-circle-check me-1"></i>{{ $data['availability_status'] }}</span>
                        </div>
                    @endif
                    @if (!empty($data['evenings_available']) || !empty($data['weekends_available']))
                        <div class="col-sm-6">
                            <div class="profile-field-label">Flexible Hours</div>
                            @if (!empty($data['evenings_available']) && $data['evenings_available'] === 'Yes')
                                <span class="avail-tag">Evenings</span>
                            @endif
                            @if (!empty($data['weekends_available']) && $data['weekends_available'] === 'Yes')
                                <span class="avail-tag">Weekends</span>
                            @endif
                            @if (($data['evenings_available'] ?? '') !== 'Yes' && ($data['weekends_available'] ?? '') !== 'Yes')
                                <span class="avail-tag text-muted">Weekdays only</span>
                            @endif
                        </div>
                    @endif
                    @if (!empty($data['communication_style']))
                        <div class="col-sm-6">
                            <div class="profile-field-label">Communication Style</div>
                            <div class="profile-field-value">{{ $data['communication_style'] }}</div>
                        </div>
                    @endif
                    @if (!empty($data['preferred_contact_method']))
                        <div class="col-sm-6">
                            <div class="profile-field-label">Preferred Contact</div>
                            <div class="profile-field-value">{{ $data['preferred_contact_method'] }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- ── HIRE BUTTONS ─────────────────────────────────────────────── --}}
    @if ($isOwnerPreview && count($hireButtons) > 0)
        <div class="profile-section">
            <div class="profile-section-header"><i class="fa fa-handshake-o"></i> Hire This Agent</div>
            <div class="profile-section-body">
                <div class="hire-btn-grid">
                    <span class="hire-owner-note">
                        <i class="fa fa-circle-info"></i>
                        Clients will use this button to hire you — it is not active in preview mode.
                    </span>
                </div>
            </div>
        </div>
    @elseif (!$isOwnerPreview && count($hireButtons) > 0)
        <div class="profile-section">
            <div class="profile-section-header"><i class="fa fa-handshake-o"></i> Hire This Agent</div>
            <div class="profile-section-body">
                <p class="text-muted small mb-3">Select the role you'd like to hire {{ $agentDisplayName }} for:</p>
                <div class="hire-btn-grid">
                    @foreach ($hireButtons as $btn)
                        @if ($btn['direct'])
                            <a href="{{ $btn['options'][0]['url'] }}" class="hire-btn">
                                <i class="fa fa-arrow-right"></i>
                                {{ $btn['roleLabel'] }}
                                <span class="hire-role-badge">{{ $btn['options'][0]['propLabel'] }}</span>
                            </a>
                        @else
                            <details class="hire-picker-wrap property-type-picker">
                                <summary class="hire-btn">
                                    <i class="fa fa-arrow-right"></i>
                                    {{ $btn['roleLabel'] }}
                                    <i class="fa fa-caret-down hire-role-badge" style="font-size:.8rem;background:none;padding:0;"></i>
                                </summary>
                                <div class="hire-picker-options">
                                    @foreach ($btn['options'] as $opt)
                                        <a href="{{ $opt['url'] }}" class="hire-picker-option">
                                            <i class="fa fa-home me-1" style="width:1rem;text-align:center;"></i>{{ $opt['propLabel'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    @endif

</div>
@push('scripts')
<script>
(function () {
    document.addEventListener('click', function (e) {
        document.querySelectorAll('details.property-type-picker').forEach(function (picker) {
            if (!picker.contains(e.target)) {
                picker.removeAttribute('open');
            }
        });
    });
}());
</script>
@endpush

@endsection
