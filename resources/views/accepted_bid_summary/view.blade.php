@extends('layouts.main')

@section('content')
<div class="container py-4">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <strong>&#10003; {{ session('success') }}</strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('info'))
        <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
            {{ session('info') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Accepted Bid Summary</h2>
                <div>
                    @if($canSign)
                        <a href="#esign-section" class="btn btn-primary">
                            Sign Now
                        </a>
                    @endif
                    @if($summary->isFullySigned())
                        <a href="{{ route('accepted-bid-summary.download-pdf', $summary->id) }}" class="btn btn-success">
                            Download Signed PDF
                        </a>
                    @else
                        <button class="btn btn-outline-secondary" onclick="showPdfNotReadyMessage()" type="button">
                            Download PDF
                        </button>
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
                            <h6>Listing Creator Acknowledgement</h6>
                            @if($summary->isTenantSigned())
                                <p class="mb-1">
                                    <strong>Signature:</strong> {{ $summary->tenant_signature_name }}
                                </p>
                                <p class="text-muted mb-0">
                                    @php
                                        $tenantTz = $summary->tenant_timezone ?: 'UTC';
                                        $tenantTime = $summary->tenant_signed_at->copy()->setTimezone($tenantTz);
                                        $tzAbbr = (new \DateTime('now', new \DateTimeZone($tenantTz)))->format('T');
                                    @endphp
                                    <strong>Date/Time:</strong> {{ $tenantTime->format('M j, Y') }} at {{ $tenantTime->format('g:i A') }} {{ $tzAbbr }}
                                </p>
                            @else
                                <p class="mb-1"><strong>Signature:</strong> —</p>
                                <p class="text-muted mb-0"><strong>Date/Time:</strong> Pending</p>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <h6>Agent Acknowledgement</h6>
                            @if($summary->isAgentSigned())
                                <p class="mb-1">
                                    <strong>Signature:</strong> {{ $summary->agent_signature_name }}
                                </p>
                                <p class="text-muted mb-0">
                                    @php
                                        $agentTz = $summary->agent_timezone ?: 'UTC';
                                        $agentTime = $summary->agent_signed_at->copy()->setTimezone($agentTz);
                                        $agentTzAbbr = (new \DateTime('now', new \DateTimeZone($agentTz)))->format('T');
                                    @endphp
                                    <strong>Date/Time:</strong> {{ $agentTime->format('M j, Y') }} at {{ $agentTime->format('g:i A') }} {{ $agentTzAbbr }}
                                </p>
                            @else
                                <p class="mb-1"><strong>Signature:</strong> —</p>
                                <p class="text-muted mb-0"><strong>Date/Time:</strong> Pending</p>
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

            {{-- ── Optional Document Sharing (listing owner only) ── --}}
            {{-- Access = ownership (tenant_user_id). listing_type controls which fields appear. --}}
            @if($canUploadAcknowledgementDocuments)
            <div class="card border-0 shadow-sm mt-4" style="border-left: 4px solid #0d6efd !important; border-radius: 10px;">
                <div class="card-header bg-white d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #e9ecef; padding: 18px 24px;">
                    <div>
                        <h5 class="mb-1" style="color: #1a3a5c; font-weight: 700;">
                            <i class="fas fa-file-upload me-2" style="color: #0d6efd;"></i>
                            Optional Document Sharing
                            <span class="badge ms-2" style="background: #e8f0fe; color: #0d6efd; font-size: 0.72rem; font-weight: 600; border-radius: 20px; padding: 3px 10px;">Recommended</span>
                        </h5>
                        <p class="mb-0 text-muted" style="font-size: 0.88rem;">
                            Sharing supporting documents helps build trust and speeds up the process. All files are private and only shared with your matched agent.
                        </p>
                    </div>
                </div>

                <div class="card-body" style="padding: 24px;">

                    @if(session('doc_success'))
                    <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                        <i class="fas fa-check-circle me-2"></i>{{ session('doc_success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    @endif

                    <form action="{{ route('accepted-bid-summary.store-documents', $summary->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="row g-4">

                            {{-- Government-Issued ID (all roles) --}}
                            <div class="col-md-6">
                                <div class="p-3 rounded" style="background: #f8f9fa; border: 1px solid #e9ecef;">
                                    <label class="form-label fw-semibold mb-1" style="font-size: 0.9rem;">
                                        <i class="fas fa-id-card me-1 text-muted"></i> Government-Issued ID
                                    </label>
                                    <p class="text-muted mb-2" style="font-size: 0.8rem;">Driver's license, passport, or state ID (PDF, JPG, or PNG)</p>
                                    @if(!empty($existingDocs?->id_document_path))
                                    <div class="d-flex align-items-center mb-2 p-2 rounded" style="background: #d1e7dd; font-size: 0.82rem;">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        <span class="text-success fw-semibold">File uploaded</span>
                                        <span class="ms-auto text-muted">Replace below</span>
                                    </div>
                                    @endif
                                    <input type="file" class="form-control form-control-sm" name="id_document" accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                            </div>

                            {{-- Buyer: Proof of Funds --}}
                            @if($summary->listing_type === 'buyer')
                            <div class="col-md-6">
                                <div class="p-3 rounded" style="background: #f8f9fa; border: 1px solid #e9ecef;">
                                    <label class="form-label fw-semibold mb-1" style="font-size: 0.9rem;">
                                        <i class="fas fa-dollar-sign me-1 text-muted"></i> Proof of Funds
                                    </label>
                                    <p class="text-muted mb-2" style="font-size: 0.8rem;">Bank statement or asset account statement (PDF, JPG, or PNG)</p>
                                    @if(!empty($existingDocs?->proof_of_funds_path))
                                    <div class="d-flex align-items-center mb-2 p-2 rounded" style="background: #d1e7dd; font-size: 0.82rem;">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        <span class="text-success fw-semibold">File uploaded</span>
                                        <span class="ms-auto text-muted">Replace below</span>
                                    </div>
                                    @endif
                                    <input type="file" class="form-control form-control-sm" name="proof_of_funds" accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                            </div>
                            @endif

                            {{-- Buyer: Pre-Approval Letter --}}
                            @if($summary->listing_type === 'buyer')
                            <div class="col-md-6">
                                <div class="p-3 rounded" style="background: #f8f9fa; border: 1px solid #e9ecef;">
                                    <label class="form-label fw-semibold mb-1" style="font-size: 0.9rem;">
                                        <i class="fas fa-file-signature me-1 text-muted"></i> Pre-Approval Letter
                                    </label>
                                    <p class="text-muted mb-2" style="font-size: 0.8rem;">Mortgage pre-approval from a lender (PDF, JPG, or PNG)</p>
                                    @if(!empty($existingDocs?->pre_approval_letter_path))
                                    <div class="d-flex align-items-center mb-2 p-2 rounded" style="background: #d1e7dd; font-size: 0.82rem;">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        <span class="text-success fw-semibold">File uploaded</span>
                                        <span class="ms-auto text-muted">Replace below</span>
                                    </div>
                                    @endif
                                    <input type="file" class="form-control form-control-sm" name="pre_approval_letter" accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                            </div>
                            @endif

                            {{-- Tenant: Proof of Income --}}
                            @if($summary->listing_type === 'tenant')
                            <div class="col-md-6">
                                <div class="p-3 rounded" style="background: #f8f9fa; border: 1px solid #e9ecef;">
                                    <label class="form-label fw-semibold mb-1" style="font-size: 0.9rem;">
                                        <i class="fas fa-file-invoice-dollar me-1 text-muted"></i> Proof of Income
                                    </label>
                                    <p class="text-muted mb-2" style="font-size: 0.8rem;">Pay stub, offer letter, or bank statements (PDF, JPG, or PNG)</p>
                                    @if(!empty($existingDocs?->proof_of_income_path))
                                    <div class="d-flex align-items-center mb-2 p-2 rounded" style="background: #d1e7dd; font-size: 0.82rem;">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        <span class="text-success fw-semibold">File uploaded</span>
                                        <span class="ms-auto text-muted">Replace below</span>
                                    </div>
                                    @endif
                                    <input type="file" class="form-control form-control-sm" name="proof_of_income" accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                            </div>
                            @endif

                            {{-- Seller / Landlord: Property Record Link --}}
                            @if(in_array($summary->listing_type, ['seller', 'landlord']))
                            <div class="col-md-6">
                                <div class="p-3 rounded" style="background: #f8f9fa; border: 1px solid #e9ecef;">
                                    <label class="form-label fw-semibold mb-1" style="font-size: 0.9rem;">
                                        <i class="fas fa-link me-1 text-muted"></i> Property Record Link
                                    </label>
                                    <p class="text-muted mb-2" style="font-size: 0.8rem;">Paste a public county property appraiser or property record link related to this property.</p>
                                    <input
                                        type="url"
                                        class="form-control form-control-sm"
                                        name="property_record_link"
                                        placeholder="https://..."
                                        value="{{ $existingDocs?->property_record_link ?? '' }}"
                                    >
                                </div>
                            </div>
                            @endif

                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary px-4" style="font-weight: 600;">
                                <i class="fas fa-cloud-upload-alt me-2"></i>Save Documents
                            </button>
                        </div>

                    </form>
                </div>
            </div>
            @endif
            {{-- ── end document sharing ── --}}

            @if($canSign)
            <div class="card mt-4" id="esign-section">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">{{ $userRole === 'tenant' ? 'Listing Creator' : 'Agent' }}: E-Sign Acknowledgement</h5>
                </div>
                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <form action="{{ route('accepted-bid-summary.sign', $summary->id) }}" method="POST" id="signForm">
                        @csrf
                        <input type="hidden" name="timezone" id="timezone" value="UTC">
                        <input type="hidden" name="client_signed_at" id="clientSignedAt" value="">

                        <div class="mb-3">
                            <label for="signature_name" class="form-label">
                                Type Your Full Legal Name <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text" 
                                class="form-control @error('signature_name') is-invalid @enderror" 
                                id="signature_name" 
                                name="signature_name" 
                                placeholder="Enter your full legal name"
                                value="{{ old('signature_name') }}"
                                required
                            >
                            @error('signature_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="agree_terms" name="checkbox_confirmed" value="1" required>
                            <label class="form-check-label" for="agree_terms">
                                <strong>Important:</strong> By signing below, you acknowledge receipt and review of this Accepted Bid Summary. This is an acknowledgement only, not a contract execution. I confirm that I have reviewed the Accepted Bid Summary and acknowledge its contents.
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 esign-btn" id="submitBtn" disabled>
                            {{ $userRole === 'tenant' ? 'Listing Creator' : 'Agent' }}: E-Sign Acknowledgement
                        </button>
                    </form>
                </div>
            </div>
            @endif
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
    
    .esign-btn {
        background-color: #0d6efd !important;
        border-color: #0d6efd !important;
        color: #fff !important;
    }
    
    .esign-btn:disabled {
        background-color: #6c757d !important;
        border-color: #6c757d !important;
        opacity: 0.65;
    }
    
    .esign-btn:not(:disabled):hover {
        background-color: #0b5ed7 !important;
        border-color: #0a58ca !important;
    }
</style>

<script>
if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
}
window.addEventListener('load', function() {
    if (!window.location.hash || window.location.hash === '#') {
        setTimeout(function() { window.scrollTo(0, 0); }, 0);
    }
});
window.addEventListener('pageshow', function(event) {
    if (!window.location.hash || window.location.hash === '#') {
        setTimeout(function() { window.scrollTo(0, 0); }, 0);
    }
});
</script>

@if(!$summary->isFullySigned())
<script>
function showPdfNotReadyMessage() {
    alert('PDF will be available once both parties have signed.');
}
</script>
@endif

@if($canSign)
<script>
document.addEventListener('DOMContentLoaded', function() {
    var checkbox = document.getElementById('agree_terms');
    var submitBtn = document.getElementById('submitBtn');
    
    if (checkbox && submitBtn) {
        function toggleSubmitButton() {
            submitBtn.disabled = !checkbox.checked;
        }
        
        checkbox.addEventListener('change', toggleSubmitButton);
        toggleSubmitButton();
        
        document.getElementById('clientSignedAt').value = new Date().toISOString();
        
        document.getElementById('signForm').addEventListener('submit', function() {
            document.getElementById('clientSignedAt').value = new Date().toISOString();
        });
        
        try {
            var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
            document.getElementById('timezone').value = tz || 'UTC';
        } catch (e) {
            document.getElementById('timezone').value = 'UTC';
        }
    }
});
</script>
@endif
@endsection
