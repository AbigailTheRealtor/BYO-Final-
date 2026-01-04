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
                                <small class="text-muted d-block">
                                    @php
                                        $tenantTz = $summary->tenant_timezone ?: 'UTC';
                                        $tenantTime = $summary->tenant_signed_at->copy()->setTimezone($tenantTz);
                                        $tzAbbr = (new \DateTime('now', new \DateTimeZone($tenantTz)))->format('T');
                                    @endphp
                                    {{ $tenantTime->format('M j, Y g:i A') }} ({{ $tzAbbr }})
                                </small>
                                <small class="text-muted d-block">
                                    IP: {{ $summary->tenant_ip_address ?: '—' }}
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
                                <small class="text-muted d-block">
                                    @php
                                        $agentTz = $summary->agent_timezone ?: 'UTC';
                                        $agentTime = $summary->agent_signed_at->copy()->setTimezone($agentTz);
                                        $agentTzAbbr = (new \DateTime('now', new \DateTimeZone($agentTz)))->format('T');
                                    @endphp
                                    {{ $agentTime->format('M j, Y g:i A') }} ({{ $agentTzAbbr }})
                                </small>
                                <small class="text-muted d-block">
                                    IP: {{ $summary->agent_ip_address ?: '—' }}
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

            <div class="card summary-document-card">
                <div class="card-header">
                    <h5 class="mb-0">Summary Document</h5>
                </div>
                <div class="card-body summary-document-body">
                    <div class="summary-content">
                        {!! $html !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .summary-document-card {
        border: 1px solid #dee2e6;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }
    
    .summary-document-body {
        padding: 0;
    }
    
    .summary-content {
        width: 100%;
        padding: 30px 40px;
        background: #ffffff;
        font-family: Arial, sans-serif;
        line-height: 1.7;
        color: #333;
    }
    
    .summary-content h1 {
        color: #2c3e50;
        border-bottom: 2px solid #3498db;
        padding-bottom: 12px;
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 1.75rem;
    }
    
    .summary-content h2 {
        color: #2c3e50;
        margin-top: 28px;
        margin-bottom: 16px;
        border-bottom: 1px solid #bdc3c7;
        padding-bottom: 8px;
        font-size: 1.25rem;
        font-weight: 600;
    }
    
    .summary-content h4 {
        color: #007bff;
        margin-bottom: 8px;
        font-size: 1rem;
    }
    
    .summary-content h6 {
        color: #17a2b8;
        font-size: 0.95rem;
        margin-top: 16px;
        margin-bottom: 10px;
    }
    
    .summary-content p {
        margin-bottom: 8px;
    }
    
    .summary-content ul {
        margin: 0 0 16px 0;
        padding-left: 28px;
        list-style-type: disc;
    }
    
    .summary-content ul li {
        margin-bottom: 6px;
        line-height: 1.6;
        list-style-type: disc;
    }
    
    .summary-content table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 16px;
    }
    
    .summary-content table td {
        padding: 10px 12px;
        border-bottom: 1px solid #eee;
        vertical-align: top;
    }
    
    .summary-content table td:first-child {
        font-weight: 600;
        width: 40%;
        color: #555;
    }
    
    .summary-content .service-category {
        margin-bottom: 20px;
    }
    
    .summary-content .alert {
        padding: 16px;
        border-radius: 6px;
        margin-bottom: 20px;
    }

    @media print {
        .container {
            max-width: 100% !important;
            padding: 0 !important;
        }
        
        .d-flex.justify-content-between.align-items-center.mb-4,
        .card.mb-4:first-of-type,
        .btn {
            display: none !important;
        }
        
        .summary-document-card {
            border: none !important;
            box-shadow: none !important;
        }
        
        .summary-content {
            max-width: 100%;
            padding: 20px;
        }
        
        .summary-content h2 {
            page-break-after: avoid;
        }
        
        .summary-content ul {
            page-break-inside: avoid;
        }
        
        .summary-content .service-category {
            page-break-inside: avoid;
        }
        
        .summary-content table {
            page-break-inside: avoid;
        }
    }
</style>
@endsection
