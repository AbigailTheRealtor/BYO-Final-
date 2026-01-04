@extends('layouts.main')

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Accepted Bid Summary</h2>
                <div>
                    @if($canSign)
                        <a href="{{ route('accepted-bid-summary.sign-form', $summary->id) }}" class="btn btn-primary">
                            {{ $userRole === 'tenant' ? 'Tenant' : 'Agent' }}: E-Sign Acknowledgement
                        </a>
                    @endif
                    @if($summary->isFullySigned())
                        <a href="{{ route('accepted-bid-summary.download-pdf', $summary->id) }}" class="btn btn-success">
                            Download Signed PDF
                        </a>
                    @endif
                    <button onclick="window.history.back()" class="btn btn-secondary">Back</button>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Document Status</span>
                    <span class="badge {{ $summary->isFullySigned() ? 'bg-success' : 'bg-warning' }}">
                        {{ $summary->getSignatureStatus() }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Tenant Acknowledgement</h6>
                            @if($summary->isTenantSigned())
                                <p class="text-success mb-1">
                                    <i class="fas fa-check-circle"></i> Signed by: {{ $summary->tenant_signature_name }}
                                </p>
                                <small class="text-muted">
                                    {{ $summary->tenant_signed_at->format('M j, Y g:i A') }}
                                </small>
                            @else
                                <p class="text-warning mb-0">
                                    <i class="fas fa-clock"></i> Pending
                                </p>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <h6>Agent Acknowledgement</h6>
                            @if($summary->isAgentSigned())
                                <p class="text-success mb-1">
                                    <i class="fas fa-check-circle"></i> Signed by: {{ $summary->agent_signature_name }}
                                </p>
                                <small class="text-muted">
                                    {{ $summary->agent_signed_at->format('M j, Y g:i A') }}
                                </small>
                            @else
                                <p class="text-warning mb-0">
                                    <i class="fas fa-clock"></i> Pending
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Summary Document</h5>
                </div>
                <div class="card-body p-0">
                    <div class="summary-content" style="background: #fff; padding: 20px;">
                        {!! $html !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .summary-content {
        font-family: Arial, sans-serif;
        line-height: 1.6;
    }
    .summary-content h1 {
        color: #2c3e50;
        border-bottom: 2px solid #3498db;
        padding-bottom: 10px;
    }
    .summary-content h2 {
        color: #2c3e50;
        margin-top: 30px;
        border-bottom: 1px solid #bdc3c7;
        padding-bottom: 5px;
    }
</style>
@endsection
