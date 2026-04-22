@extends('layouts.main')

@push('styles')
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
        <h1><i class="fa fa-sliders me-2"></i>My Offer Presets</h1>
        <p>Presets define the services and terms you offer by role and property type. Once saved, each preset generates a personal Hire Me link you can share directly with clients — and an embeddable widget card you can place on your own website.</p>
    </div>

    <div class="how-it-works">
        <div class="how-it-works-item">
            <div class="how-it-works-icon"><i class="fa fa-sliders"></i></div>
            <div class="how-it-works-text">
                <strong>1. Build a Preset</strong>
                <span>Select services, add your bio and credentials for a specific role &amp; property type.</span>
            </div>
        </div>
        <div class="how-it-works-item">
            <div class="how-it-works-icon"><i class="fa fa-link"></i></div>
            <div class="how-it-works-text">
                <strong>2. Share Your Hire Me Link</strong>
                <span>Send the link to clients. They click it and hire you directly — no search needed.</span>
            </div>
        </div>
        <div class="how-it-works-item">
            <div class="how-it-works-icon"><i class="fa fa-code"></i></div>
            <div class="how-it-works-text">
                <strong>3. Embed on Your Website</strong>
                <span>Copy the embed code and paste it on your site to show a live Hire Me card.</span>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fa fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

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
                <i class="fa {{ $meta['icon'] }} me-2"></i>{{ $meta['label'] }} Presets
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
                                            <span><i class="fa fa-check-circle text-success me-1"></i>{{ $info['services'] }} service{{ $info['services'] !== 1 ? 's' : '' }} selected</span><br>
                                        @else
                                            <span class="text-warning"><i class="fa fa-exclamation-circle me-1"></i>No services selected</span><br>
                                        @endif
                                        @if ($info['has_bio'])
                                            <span><i class="fa fa-check-circle text-success me-1"></i>Bio included</span>
                                            @if ($info['has_creds']) &nbsp;&middot;&nbsp; <span><i class="fa fa-check-circle text-success me-1"></i>Credentials</span>@endif
                                        @elseif ($info['has_creds'])
                                            <span><i class="fa fa-check-circle text-success me-1"></i>Credentials included</span>
                                        @else
                                            <span class="text-muted"><i class="fa fa-circle-o me-1"></i>No bio or credentials</span>
                                        @endif
                                        @if ($info['updated_at'])
                                            <div class="preset-updated"><i class="fa fa-clock-o me-1"></i>Updated {{ $info['updated_at']->diffForHumans() }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted">No preset saved yet. Click Edit to create one.</span>
                                    @endif
                                </div>
                            </div>
                            <div class="preset-actions">
                                <a href="{{ route('agent.presets.edit', [$role, $propertyType]) }}"
                                   class="btn btn-outline-secondary btn-edit-preset w-100">
                                    <i class="fa fa-pencil me-1"></i>{{ $info['exists'] ? 'Edit Preset' : 'Create Preset' }}
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
                                            <i class="fa fa-copy me-1"></i>Copy Link
                                        </button>
                                        <a href="{{ $fullHireUrl }}"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           class="btn btn-open-hire"
                                           title="See the page your client will land on">
                                            <i class="fa fa-external-link me-1"></i>Open
                                        </a>
                                    </div>
                                    <div class="action-cluster-label" style="margin-top:.75rem">Embed on your website</div>
                                    <div class="preset-embed-actions">
                                        <button type="button"
                                                class="btn btn-outline btn-copy-embed"
                                                title="Copy the iframe snippet to paste into your website"
                                                data-embed-code="{{ $embedCode }}">
                                            <i class="fa fa-code me-1"></i>Copy Embed Code
                                        </button>
                                        <a href="{{ $widgetUrl }}"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           class="btn btn-outline btn-open-widget"
                                           title="See what your embedded card looks like">
                                            <i class="fa fa-eye me-1"></i>Preview
                                        </a>
                                    </div>
                                    <div class="preset-embed-note">
                                        Paste this embed code into your website to show your Hire Me card.
                                    </div>
                                @elseif ($info['exists'] && $info['services'] === 0)
                                    <div class="preset-inactive-note mt-2">
                                        <i class="fa fa-lock me-1"></i>Complete your services to activate this Hire Me link.
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
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-copy-hire').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = this.dataset.hireUrl;
            var self = this;
            var orig = self.innerHTML;

            function showCopied() {
                self.innerHTML = '<i class="fa fa-check me-1"></i>Copied!';
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
                self.innerHTML = '<i class="fa fa-check me-1"></i>Embed code copied!';
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
