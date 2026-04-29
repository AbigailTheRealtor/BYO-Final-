@props([
    'backUrl',
    'backLabel'   => 'Back to Listing',
    'roleLabel',
    'listingId',
    'address'     => null,
    'bidStatus'   => 'Active',
    'bidStatusColor' => '#1a4a6e',
    'headerTitle' => 'Agent Bid Detail',
])
@php
    $statusTextColor = ($bidStatus === 'Countered') ? '#000' : '#fff';
    $bidStatusPillClass = match($bidStatus) {
        'Active'    => 'status-active',
        'Countered' => 'status-pending',
        'Accepted'  => 'status-hired',
        'Rejected'  => 'status-expired',
        default     => 'status-expired',
    };
@endphp

<div class="container py-4">
    <div class="mb-3">
        <a href="{{ $backUrl }}" class="back-link">
            <i class="fa-solid fa-arrow-left me-2"></i>{{ $backLabel }}
        </a>
    </div>

    <div class="bid-preview-card">

        {{-- ===== HEADER ===== --}}
        <div class="bid-preview-header">
            <div class="d-flex justify-content-between align-items-start flex-wrap">
                <div>
                    <h4 class="mb-2"><i class="fa-solid fa-user-tie me-2"></i>{{ $headerTitle }}</h4>
                    <div class="opacity-75">
                        <span class="me-3"><i class="fa-solid fa-home me-1"></i>{{ $roleLabel }}</span>
                        <span><i class="fa-solid fa-tag me-1"></i>Listing #{{ $listingId }}</span>
                    </div>
                </div>
                <div class="text-end mt-2 mt-md-0">
                    <span class="status-pill {{ $bidStatusPillClass }}">
                        {{ $bidStatus }}
                    </span>
                </div>
            </div>
        </div>

        {{-- ===== BODY (default slot) ===== --}}
        <div class="bid-preview-body">
            {{ $slot }}
        </div>

        {{-- ===== FOOTER ===== --}}
        <div class="action-buttons d-flex flex-wrap justify-content-between align-items-center gap-2">

            {{-- Confidential notice — identical across all roles --}}
            <div class="w-100 p-2 text-center" style="background: #e8f4f5; border-radius: 6px; color: #049399;">
                <i class="fa fa-shield-halved me-2"></i>
                <strong>Confidential:</strong> This information is private and only visible to you.
            </div>

            {{-- Role-specific status/notice banners (w-100 rows) --}}
            {{ $footerBanners ?? '' }}

            {{-- Back button — LEFT --}}
            <a href="{{ $backUrl }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i>{{ $backLabel }}
            </a>

            {{-- Role-specific action buttons — RIGHT --}}
            <div class="d-flex gap-2 flex-wrap align-items-center">
                {{ $footerActions ?? '' }}
            </div>

        </div>
    </div>
</div>
