@extends('layouts.main')

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" crossorigin="anonymous">
<style>
    .preset-hub-wrap {
        max-width: 1100px;
        margin: 0 auto;
    }
    .preset-role-group {
        margin-bottom: 2.5rem;
    }
    .preset-role-title {
        font-size: 1.05rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #444;
        border-bottom: 2px solid #e0e0e0;
        padding-bottom: .5rem;
        margin-bottom: 1.25rem;
    }
    .preset-card {
        border: 1px solid #dee2e6;
        border-radius: 10px;
        padding: 1.25rem 1.4rem;
        background: #fff;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: box-shadow .15s;
    }
    .preset-card:hover {
        box-shadow: 0 2px 10px rgba(0,0,0,.08);
    }
    .preset-card.has-preset {
        border-left: 4px solid #049399;
    }
    .preset-card.no-preset {
        border-left: 4px solid #dee2e6;
    }
    .preset-prop-label {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: .4rem;
    }
    .preset-status-badge {
        font-size: .75rem;
        padding: .2rem .55rem;
        border-radius: 20px;
        font-weight: 600;
    }
    .preset-meta {
        font-size: .8rem;
        color: #6c757d;
        margin-top: .5rem;
        min-height: 2.5rem;
    }
    .preset-actions {
        margin-top: 1rem;
    }
    .btn-edit-preset {
        font-size: .83rem;
        padding: .35rem .9rem;
        border-radius: 6px;
    }
    .btn-copy-hire {
        font-size: .78rem;
        padding: .3rem .8rem;
        border-radius: 6px;
        color: #049399;
        border-color: #049399;
    }
    .btn-copy-hire:hover {
        background: #e8f7f7;
        color: #036b70;
        border-color: #036b70;
    }
    .btn-copy-hire.copied {
        color: #198754;
        border-color: #198754;
        background: #f0faf4;
    }
    .btn-open-hire {
        font-size: .78rem;
        padding: .3rem .8rem;
        border-radius: 6px;
        background: #049399;
        color: #fff;
        border-color: #049399;
    }
    .btn-open-hire:hover {
        background: #036b70;
        border-color: #036b70;
        color: #fff;
    }
    .preset-share-actions {
        display: flex;
        gap: .5rem;
        margin-top: .5rem;
    }
    .preset-share-actions .btn {
        flex: 1;
    }
    .preset-hire-note {
        font-size: .73rem;
        color: #6a8fa0;
        margin-top: .45rem;
        line-height: 1.4;
    }
    .preset-inactive-note {
        font-size: .73rem;
        color: #a07a30;
        margin-top: .5rem;
        line-height: 1.4;
    }
    .btn-copy-embed {
        font-size: .78rem;
        padding: .3rem .8rem;
        border-radius: 6px;
        color: #6c757d;
        border-color: #ced4da;
        background: #fff;
        width: 100%;
        margin-top: .4rem;
        text-align: left;
    }
    .btn-copy-embed:hover {
        background: #f8f9fa;
        color: #495057;
        border-color: #adb5bd;
    }
    .btn-copy-embed.copied {
        color: #198754;
        border-color: #198754;
        background: #f0faf4;
    }
    .btn-open-widget {
        font-size: .78rem;
        padding: .3rem .8rem;
        border-radius: 6px;
        color: #6c757d;
        border-color: #ced4da;
        background: #fff;
        white-space: nowrap;
    }
    .btn-open-widget:hover {
        background: #f8f9fa;
        color: #495057;
        border-color: #adb5bd;
    }
    .preset-embed-actions {
        display: flex;
        gap: .5rem;
        margin-top: .4rem;
        align-items: stretch;
    }
    .preset-embed-actions .btn-copy-embed {
        flex: 1;
        margin-top: 0;
        width: auto;
        text-align: left;
    }
    .preset-embed-note {
        font-size: .72rem;
        color: #8fa8b8;
        margin-top: .35rem;
        line-height: 1.4;
    }
    .preset-updated {
        font-size: .73rem;
        color: #9aa5b1;
        margin-top: .45rem;
    }
    .avatar-section {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 10px;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.75rem;
        display: flex;
        align-items: center;
        gap: 1.5rem;
        flex-wrap: wrap;
    }
    .avatar-preview {
        flex-shrink: 0;
        width: 84px;
        height: 84px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #c8e8ea;
        background: #f0fafa;
    }
    .avatar-placeholder {
        flex-shrink: 0;
        width: 84px;
        height: 84px;
        border-radius: 50%;
        background: #e9ecef;
        border: 3px dashed #ced4da;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #adb5bd;
        font-size: 2rem;
    }
    .avatar-upload-info h6 {
        font-size: .95rem;
        font-weight: 700;
        margin-bottom: .25rem;
    }
    .avatar-upload-info p {
        font-size: .82rem;
        color: #6c757d;
        margin-bottom: .6rem;
    }
    .avatar-upload-form {
        display: flex;
        align-items: center;
        gap: .6rem;
        flex-wrap: wrap;
    }
    .avatar-crop-preview {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #049399;
        display: none;
        flex-shrink: 0;
    }
    .avatar-crop-note {
        font-size: .78rem;
        color: #049399;
        margin-top: .35rem;
        display: none;
    }
    #avatar-crop-modal .modal-body {
        background: #111;
        padding: 0;
        line-height: 0;
    }
    #avatar-crop-modal img {
        display: block;
        max-width: 100%;
    }
    .preset-hire-path {
        font-size: .72rem;
        font-family: monospace;
        color: #6c9ab0;
        background: #f1f7fb;
        border: 1px solid #d4e6f0;
        border-radius: 4px;
        padding: .25rem .55rem;
        margin-top: .6rem;
        word-break: break-all;
        display: block;
        user-select: all;
        -webkit-user-select: all;
        cursor: text;
    }
    .page-hero {
        background: linear-gradient(135deg, #049399 0%, #036b70 100%);
        color: #fff;
        border-radius: 12px;
        padding: 1.6rem 2rem;
        margin-bottom: 1.25rem;
    }
    .page-hero h1 {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0 0 .3rem;
    }
    .page-hero p {
        margin: 0;
        opacity: .85;
        font-size: .92rem;
    }
    .how-it-works {
        background: #f0fafa;
        border: 1px solid #c8e8ea;
        border-radius: 10px;
        padding: .9rem 1.2rem;
        margin-bottom: 1.75rem;
        display: flex;
        flex-wrap: wrap;
        gap: .6rem 2rem;
    }
    .how-it-works-item {
        display: flex;
        align-items: flex-start;
        gap: .55rem;
        flex: 1 1 200px;
    }
    .how-it-works-icon {
        color: #049399;
        font-size: 1rem;
        margin-top: .12rem;
        flex-shrink: 0;
    }
    .how-it-works-text strong {
        font-size: .82rem;
        font-weight: 700;
        color: #1a1a1a;
        display: block;
        line-height: 1.3;
    }
    .how-it-works-text span {
        font-size: .77rem;
        color: #5a7a82;
        line-height: 1.35;
    }
    .action-cluster-label {
        font-size: .68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #9aa5b1;
        margin-top: .7rem;
        margin-bottom: .2rem;
    }
</style>
@endpush

@section('content')
<div class="preset-hub-wrap py-4 px-3">

    <div class="page-hero">
        <h1><i class="fa-solid fa-sliders me-2"></i>My Offer Presets</h1>
        <p>Presets define the services and terms you offer by role and property type. Once saved, each preset generates a personal Hire Me link you can share directly with clients — and an embeddable widget card you can place on your own website.</p>
    </div>

    <div class="how-it-works">
        <div class="how-it-works-item">
            <div class="how-it-works-icon"><i class="fa-solid fa-sliders"></i></div>
            <div class="how-it-works-text">
                <strong>1. Build a Preset</strong>
                <span>Select services, add your bio and credentials for a specific role &amp; property type.</span>
            </div>
        </div>
        <div class="how-it-works-item">
            <div class="how-it-works-icon"><i class="fa-solid fa-link"></i></div>
            <div class="how-it-works-text">
                <strong>2. Share Your Hire Me Link</strong>
                <span>Send the link to clients. They click it and hire you directly — no search needed.</span>
            </div>
        </div>
        <div class="how-it-works-item">
            <div class="how-it-works-icon"><i class="fa-solid fa-code"></i></div>
            <div class="how-it-works-text">
                <strong>3. Embed on Your Website</strong>
                <span>Copy the embed code and paste it on your site to show a live Hire Me card.</span>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ── Profile Photo ──────────────────────────────────────────────────── --}}
    <div class="avatar-section">
        <x-avatar-img :avatar="auth()->user()->avatar"
             alt="Your profile photo"
             class="avatar-preview" />
        <div class="avatar-upload-info flex-grow-1">
            <h6><i class="fa-solid fa-camera me-1" style="color:#049399"></i>Profile Photo</h6>
            <p>Your photo appears on your <a href="{{ route('hire.agent.public', ['agentShortId' => $agentShortId, 'role' => 'buyer', 'propertyType' => 'residential']) }}" target="_blank" rel="noopener noreferrer">Hire Me page</a> and your <a href="{{ route('agent.profile.public', ['agentShortId' => $agentShortId]) }}" target="_blank" rel="noopener noreferrer">public profile page</a> so clients can put a face to your name.</p>
            @error('avatar')
                <div class="alert alert-danger py-1 px-2 mb-2" style="font-size:.82rem;">{{ $message }}</div>
            @enderror
            <form method="POST"
                  action="{{ route('agent.avatar.upload') }}"
                  enctype="multipart/form-data"
                  class="avatar-upload-form"
                  id="avatar-upload-form">
                @csrf
                <input type="file"
                       id="avatar-file-input"
                       name="avatar"
                       accept="image/jpeg,image/png,image/gif,image/webp"
                       style="display:none;">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="avatar-choose-btn">
                    <i class="fa-solid fa-image me-1"></i>Choose Photo&hellip;
                </button>
                <button type="submit" class="btn btn-sm btn-primary" id="avatar-upload-btn" style="background:#049399;border-color:#049399;white-space:nowrap;display:none;">
                    <i class="fa-solid fa-upload me-1"></i>Upload Photo
                </button>
            </form>
            <div class="d-flex align-items-center gap-3 mt-2" id="avatar-crop-result" style="display:none!important;">
                <img id="avatar-crop-preview" class="avatar-crop-preview" alt="Cropped preview">
                <div>
                    <div class="avatar-crop-note" id="avatar-crop-note">
                        <i class="fa-solid fa-circle-check me-1"></i>Looking good! Click <strong>Upload Photo</strong> to save.
                    </div>
                </div>
            </div>
            <div style="font-size:.75rem;color:#9aa5b1;margin-top:.4rem;">JPEG, PNG, GIF or WebP &middot; Max 4 MB</div>
        </div>
    </div>

    @php
        $roleLabels = [
            'buyer'    => ['label' => 'Buyer Agent', 'icon' => 'fa-search', 'color' => '#0d6efd'],
            'seller'   => ['label' => 'Seller Agent', 'icon' => 'fa-gavel', 'color' => '#198754'],
            'tenant'   => ['label' => 'Tenant Agent', 'icon' => 'fa-key', 'color' => '#049399'],
            'landlord' => ['label' => 'Landlord Agent', 'icon' => 'fa-building', 'color' => '#fd7e14'],
        ];
        $propTypeLabels = [
            'residential' => 'Residential',
            'income'      => 'Income Property',
            'commercial'  => 'Commercial',
            'business'    => 'Business Opportunity',
            'vacant_land' => 'Vacant Land',
        ];
    @endphp

    @foreach ($roles as $role)
        @php
            $meta = $roleLabels[$role];
        @endphp
        <div class="preset-role-group">
            <div class="preset-role-title" style="color: {{ $meta['color'] }}">
                <i class="fa-solid {{ $meta['icon'] }} me-2"></i>{{ $meta['label'] }} Presets
            </div>
            <div class="row g-3">
                @foreach ($presets[$role] as $propertyType => $info)
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="preset-card {{ $info['exists'] ? 'has-preset' : 'no-preset' }}">
                            <div>
                                <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
                                    <div class="preset-prop-label">{{ $propTypeLabels[$propertyType] ?? ucwords(str_replace('_',' ',$propertyType)) }}</div>
                                    @if ($info['exists'])
                                        <span class="preset-status-badge bg-success text-white">Saved</span>
                                    @else
                                        <span class="preset-status-badge bg-light text-muted border">Not set</span>
                                    @endif
                                </div>
                                <div class="preset-meta">
                                    @if ($info['exists'])
                                        @if ($info['services'] > 0)
                                            <span><i class="fa-solid fa-circle-check text-success me-1"></i>{{ $info['services'] }} service{{ $info['services'] !== 1 ? 's' : '' }} selected</span><br>
                                        @else
                                            <span class="text-warning"><i class="fa-solid fa-exclamation-circle me-1"></i>No services selected</span><br>
                                        @endif
                                        @if ($info['has_bio'])
                                            <span><i class="fa-solid fa-circle-check text-success me-1"></i>Bio included</span>
                                            @if ($info['has_creds']) &nbsp;&middot;&nbsp; <span><i class="fa-solid fa-circle-check text-success me-1"></i>Credentials</span>@endif
                                        @elseif ($info['has_creds'])
                                            <span><i class="fa-solid fa-circle-check text-success me-1"></i>Credentials included</span>
                                        @else
                                            <span class="text-muted"><i class="fa-solid fa-circle me-1"></i>No bio or credentials</span>
                                        @endif
                                        @if ($info['updated_at'])
                                            <div class="preset-updated"><i class="fa-solid fa-clock me-1"></i>Updated {{ $info['updated_at']->diffForHumans() }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted">No preset saved yet. Click Edit to create one.</span>
                                    @endif
                                </div>
                                {{-- ── Readiness coaching hints (P6) ── --}}
                                @if ($info['exists'])
                                @php
                                    $coaching        = $info['coaching'] ?? [];
                                    $coachMissQ      = $coaching['missing_quick_labels'] ?? [];
                                    $coachMissF      = $coaching['missing_full_labels']  ?? [];
                                    $coachImpact     = $coaching['impact'] ?? '';
                                    $coachAllReady   = (empty($coachMissQ) && empty($coachMissF));
                                    $coachHasMissing = (!empty($coachMissQ) || !empty($coachMissF));
                                    $coachBlockingQ  = !empty($coachMissQ);
                                    $coachPanelId    = 'coachHints_' . $role . '_' . $propertyType;
                                @endphp
                                @if($coachAllReady)
                                <div class="mt-2" style="font-size:.76rem;color:#198754;">
                                    <i class="fa-solid fa-circle-check me-1"></i>
                                    All match readiness fields set
                                </div>
                                @elseif($coachHasMissing)
                                <div class="mt-2">
                                    <button type="button"
                                            class="btn btn-sm p-0 d-flex align-items-center gap-1"
                                            style="font-size:.76rem;color:{{ $coachBlockingQ ? '#856404' : '#004085' }};background:transparent;border:none;"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#{{ $coachPanelId }}"
                                            aria-expanded="false">
                                        <i class="fa-solid fa-lightbulb me-1"
                                           style="color:{{ $coachBlockingQ ? '#856404' : '#0d6efd' }};"></i>
                                        {{ $coachBlockingQ ? 'Fields needed for Quick Match Ready' : 'Fields to reach Full Match Ready' }}
                                        <i class="fa-solid fa-chevron-down ms-1" style="font-size:.65rem;"></i>
                                    </button>
                                    <div class="collapse" id="{{ $coachPanelId }}">
                                        <div class="mt-1 p-2"
                                             style="background:{{ $coachBlockingQ ? '#fff3cd' : '#e8f4ff' }};border-radius:6px;font-size:.75rem;color:{{ $coachBlockingQ ? '#856404' : '#004085' }};">
                                            <div style="font-weight:600;margin-bottom:3px;">
                                                <i class="fa-solid fa-circle-info me-1"></i>{{ $coachImpact }}
                                            </div>
                                            <ul class="mb-0 ps-3" style="line-height:1.7;">
                                                @foreach(($coachBlockingQ ? $coachMissQ : $coachMissF) as $coachField)
                                                <li>{{ $coachField }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                @endif
                                @endif
                            </div>
                            <div class="preset-actions">
                                <a href="{{ route('agent.presets.edit', [$role, $propertyType]) }}"
                                   class="btn btn-outline-secondary btn-edit-preset w-100">
                                    <i class="fa-solid fa-pencil me-1"></i>{{ $info['exists'] ? 'Edit Preset' : 'Create Preset' }}
                                </a>
                                @if ($info['exists'] && $info['services'] > 0)
                                    @php
                                        $cleanPath = '/hire/' . $agentShortId . '/' . $role . '/' . $propertyType;
                                        $fullHireUrl = route('hire.agent.public', ['agentShortId' => $agentShortId, 'role' => $role, 'propertyType' => $propertyType]);
                                        $widgetUrl = route('hire.agent.widget', ['agentShortId' => $agentShortId, 'role' => $role, 'propertyType' => $propertyType]);
                                        $embedCode = '<iframe src="' . $widgetUrl . '" width="320" height="220" frameborder="0" style="border-radius:10px;border:none;display:block;"></iframe>';
                                    @endphp
                                    <div class="action-cluster-label">Share with clients</div>
                                    <span class="preset-hire-path" title="Click to select — then copy">{{ $cleanPath }}</span>
                                    <div class="preset-share-actions">
                                        <button type="button"
                                                class="btn btn-outline btn-copy-hire"
                                                title="Copy link to share directly with clients"
                                                data-hire-url="{{ $fullHireUrl }}">
                                            <i class="fa-solid fa-copy me-1"></i>Copy Link
                                        </button>
                                        <a href="{{ $fullHireUrl }}"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           class="btn btn-open-hire"
                                           title="Open your Hire Me page for this role and property type">
                                            <i class="fa-solid fa-external-link me-1"></i>Open
                                        </a>
                                    </div>
                                    <div class="action-cluster-label" style="margin-top:.75rem">Embed on your website</div>
                                    <div class="preset-embed-actions">
                                        <button type="button"
                                                class="btn btn-outline btn-copy-embed"
                                                title="Copy the iframe snippet to paste into your website"
                                                data-embed-code="{{ $embedCode }}">
                                            <i class="fa-solid fa-code me-1"></i>Copy Embed Code
                                        </button>
                                        <a href="{{ $widgetUrl }}"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           class="btn btn-outline btn-open-widget"
                                           title="See what your embedded card looks like">
                                            <i class="fa-solid fa-eye me-1"></i>Preview
                                        </a>
                                    </div>
                                    <div class="preset-embed-note">
                                        Paste this embed code into your website to show your Hire Me card.
                                    </div>
                                @elseif ($info['exists'] && $info['services'] === 0)
                                    <div class="preset-inactive-note mt-2">
                                        <i class="fa-solid fa-lock me-1"></i>Complete your services to activate this Hire Me link.
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

</div>

{{-- ── Avatar Crop Modal ────────────────────────────────────────────── --}}
<div class="modal fade" id="avatar-crop-modal" tabindex="-1" aria-labelledby="avatarCropModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
        <div class="modal-content">
            <div class="modal-header" style="background:#f8f9fa;">
                <h5 class="modal-title" id="avatarCropModalLabel"><i class="fa-solid fa-crop-simple me-2" style="color:#049399"></i>Crop Your Photo</h5>
                <button type="button" class="btn-close" id="avatar-crop-cancel" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="background:#111;padding:0;line-height:0;">
                <img id="avatar-crop-img" src="" alt="Crop preview" style="display:block;max-width:100%;">
            </div>
            <div class="modal-footer" style="background:#f8f9fa;flex-wrap:wrap;gap:.5rem;">
                <div style="font-size:.78rem;color:#6c757d;flex:1 1 100%;margin-bottom:.25rem;">
                    <i class="fa-solid fa-arrows-up-down-left-right me-1"></i>Drag to reposition &ensp;
                    <i class="fa-solid fa-magnifying-glass-plus me-1"></i>Scroll or pinch to zoom
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="avatar-crop-cancel2">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="avatar-crop-confirm" style="background:#049399;border-color:#049399;">
                    <i class="fa-solid fa-check me-1"></i>Use This Photo
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Avatar Crop/Preview ──────────────────────────────────────────────
    (function () {
        var fileInput    = document.getElementById('avatar-file-input');
        var chooseBtn    = document.getElementById('avatar-choose-btn');
        var uploadBtn    = document.getElementById('avatar-upload-btn');
        var cropImg      = document.getElementById('avatar-crop-img');
        var cropResult   = document.getElementById('avatar-crop-result');
        var cropPreview  = document.getElementById('avatar-crop-preview');
        var cropNote     = document.getElementById('avatar-crop-note');
        var confirmBtn   = document.getElementById('avatar-crop-confirm');
        var cancelBtn    = document.getElementById('avatar-crop-cancel');
        var cancelBtn2   = document.getElementById('avatar-crop-cancel2');

        var cropperInstance = null;
        var modalEl         = document.getElementById('avatar-crop-modal');
        var bsModal         = new bootstrap.Modal(modalEl);

        chooseBtn.addEventListener('click', function () {
            fileInput.value = '';
            fileInput.click();
        });

        fileInput.addEventListener('change', function () {
            var file = fileInput.files[0];
            if (!file) return;

            var reader = new FileReader();
            reader.onload = function (e) {
                cropImg.src = e.target.result;
                bsModal.show();
            };
            reader.readAsDataURL(file);
        });

        modalEl.addEventListener('shown.bs.modal', function () {
            if (cropperInstance) {
                cropperInstance.destroy();
            }
            cropperInstance = new Cropper(cropImg, {
                aspectRatio: 1,
                viewMode: 1,
                autoCropArea: 0.9,
                movable: true,
                zoomable: true,
                rotatable: false,
                scalable: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxResizable: true,
                background: true,
            });
        });

        function closeAndReset() {
            bsModal.hide();
            if (cropperInstance) {
                cropperInstance.destroy();
                cropperInstance = null;
            }
            fileInput.value = '';
        }

        cancelBtn.addEventListener('click', closeAndReset);
        cancelBtn2.addEventListener('click', closeAndReset);

        confirmBtn.addEventListener('click', function () {
            if (!cropperInstance) return;

            var canvas = cropperInstance.getCroppedCanvas({ width: 400, height: 400 });
            if (!canvas) return;
            canvas.toBlob(function (blob) {
                if (!blob) return;
                var croppedFile = new File([blob], 'avatar_crop.jpg', { type: 'image/jpeg' });

                var dt = new DataTransfer();
                dt.items.add(croppedFile);
                fileInput.files = dt.files;

                var previewUrl = URL.createObjectURL(blob);
                cropPreview.src = previewUrl;
                cropPreview.style.display = 'block';
                cropResult.style.display  = 'flex';
                cropNote.style.display    = 'block';
                uploadBtn.style.display   = 'inline-block';

                bsModal.hide();
                if (cropperInstance) {
                    cropperInstance.destroy();
                    cropperInstance = null;
                }
            }, 'image/jpeg', 0.9);
        });

        modalEl.addEventListener('hidden.bs.modal', function () {
            if (cropperInstance) {
                cropperInstance.destroy();
                cropperInstance = null;
            }
        });
    })();

    // ── Copy Hire-Me link & embed ────────────────────────────────────────
    document.querySelectorAll('.btn-copy-hire').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = this.dataset.hireUrl;
            var self = this;
            var orig = self.innerHTML;

            function showCopied() {
                self.innerHTML = '<i class="fa-solid fa-check me-1"></i>Copied!';
                self.classList.add('copied');
                setTimeout(function () {
                    self.innerHTML = orig;
                    self.classList.remove('copied');
                }, 2200);
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(showCopied).catch(function () {
                    fallbackCopy(url, showCopied);
                });
            } else {
                fallbackCopy(url, showCopied);
            }
        });
    });

    function fallbackCopy(text, callback) {
        var inp = document.createElement('input');
        inp.style.position = 'fixed';
        inp.style.opacity = '0';
        inp.value = text;
        document.body.appendChild(inp);
        inp.focus();
        inp.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(inp);
        callback();
    }

    document.querySelectorAll('.btn-copy-embed').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var code = this.dataset.embedCode;
            var self = this;
            var orig = self.innerHTML;

            function showCopied() {
                self.innerHTML = '<i class="fa-solid fa-check me-1"></i>Embed code copied!';
                self.classList.add('copied');
                setTimeout(function () {
                    self.innerHTML = orig;
                    self.classList.remove('copied');
                }, 2500);
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).then(showCopied).catch(function () {
                    fallbackCopy(code, showCopied);
                });
            } else {
                fallbackCopy(code, showCopied);
            }
        });
    });
});
</script>
@endpush
