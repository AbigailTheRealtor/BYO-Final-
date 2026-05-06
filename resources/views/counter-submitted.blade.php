@extends('layouts.main')

@section('content')
@php
$roleLabels = [
    'buyer'    => "Buyer's Agent",
    'seller'   => "Seller's Agent",
    'landlord' => "Landlord's Agent",
    'tenant'   => "Tenant's Agent",
];
$backRoutes = [
    'buyer'    => route('buyer.view-auction', $auctionId),
    'seller'   => route('seller.agent.auction.detail', ['id' => $auctionId]),
    'landlord' => route('landlord.agent.auction.view', $auctionId),
    'tenant'   => route('tenant.agent.auction.view', $auctionId),
];
$roleLabel = $roleLabels[$role] ?? ucfirst($role);
$backUrl   = $backRoutes[$role] ?? route('dashboard');
@endphp

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i><strong>{{ session('success') }}</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="bid-preview-card">

                <div class="bid-preview-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h4 class="mb-1">
                                <i class="fa-solid fa-paper-plane me-2"></i>Counter Submitted
                            </h4>
                            <div class="opacity-75">
                                <span class="me-3">
                                    <i class="fa-solid fa-user-tie me-1"></i>{{ $roleLabel }}
                                </span>
                                <span>
                                    <i class="fa-solid fa-tag me-1"></i>Listing #{{ $auctionId }}
                                </span>
                            </div>
                        </div>
                        <span class="status-pill status-pending">Countered</span>
                    </div>
                </div>

                <div class="bid-preview-body">
                    <div class="text-center py-5">
                        <i class="fa-solid fa-circle-check" style="font-size:3.5rem;color:#049399;opacity:.9;"></i>
                        <h5 class="mt-3 fw-bold">Your counter terms have been submitted successfully.</h5>
                        <p class="text-muted mb-0">
                            The other party has been notified and can review your counter terms on the listing page.
                        </p>
                    </div>
                </div>

                <div class="action-buttons d-flex flex-wrap justify-content-between align-items-center gap-2">

                    <div class="w-100 p-2 text-center" style="background:#e8f4f5;border-radius:6px;color:#049399;">
                        <i class="fa-solid fa-shield-halved me-2"></i>
                        <strong>Confidential:</strong> Your counter terms are private and only visible to authorised parties.
                    </div>

                    <a href="{{ $backUrl }}" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-arrow-left me-1"></i>Back to Listing
                    </a>

                    <a href="{{ route('dashboard') }}" class="btn" style="background:#049399;color:#fff;">
                        <i class="fa-solid fa-gauge me-1"></i>Dashboard
                    </a>

                </div>

            </div>

        </div>
    </div>
</div>
@endsection
