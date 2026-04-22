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
    .preset-updated {
        font-size: .73rem;
        color: #9aa5b1;
        margin-top: .45rem;
    }
    .page-hero {
        background: linear-gradient(135deg, #049399 0%, #036b70 100%);
        color: #fff;
        border-radius: 12px;
        padding: 1.6rem 2rem;
        margin-bottom: 2rem;
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
</style>
@endpush

@section('content')
<div class="preset-hub-wrap py-4 px-3">

    <div class="page-hero">
        <h1><i class="fa fa-sliders me-2"></i>My Offer Presets</h1>
        <p>Save default services, bio, credentials, and compensation details for each role and property type. These presets auto-fill your bids when a client hires you directly.</p>
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
                                @if ($info['exists'])
                                    <button type="button"
                                            class="btn btn-outline btn-copy-hire w-100 mt-2"
                                            data-hire-url="{{ route('hire.agent.direct.preview', ['agentId' => $userId, 'role' => $role, 'propertyType' => $propertyType]) }}">
                                        <i class="fa fa-link me-1"></i>Copy Hire Me Link
                                    </button>
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
});
</script>
@endpush
