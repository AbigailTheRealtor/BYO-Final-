@extends('layouts.main')

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>{{ $userRole === 'tenant' ? 'Listing Owner' : 'Agent' }}: E-Sign Acknowledgement</h2>
                <a href="{{ route('accepted-bid-summary.view', $summary->id) }}" class="btn btn-secondary">Cancel</a>
            </div>

            {{-- ── Row 1: Summary preview + Signature form ── --}}
            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Review Accepted Bid Summary</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="summary-preview" style="max-height: 500px; overflow-y: auto; padding: 20px; background: #f8f9fa;">
                                {!! $html !!}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card position-sticky" id="signatureSection" style="top: 20px;">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">E-Sign Acknowledgement</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <small>
                                    <strong>Important:</strong> By signing below, you acknowledge receipt and review of this Accepted Bid Summary. This is an acknowledgement only, not a contract execution.
                                </small>
                            </div>

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

                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <input type="text" class="form-control" value="{{ ucfirst($userRole) }}" disabled>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Timestamp</label>
                                    <input type="text" class="form-control" id="localTimestamp" value="Loading..." disabled>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Your Timezone</label>
                                    <input type="text" class="form-control" id="timezoneDisplay" value="Detecting..." disabled>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">IP Address (Captured at Signing)</label>
                                    @php
                                        $clientIp = request()->ip();
                                        $isPrivateIp = preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.|127\.)/', $clientIp);
                                    @endphp
                                    <input type="text" class="form-control" value="{{ $isPrivateIp ? 'Will be captured at signing' : $clientIp }}" disabled>
                                </div>

                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" id="agree_terms" name="checkbox_confirmed" value="1" required>
                                    <label class="form-check-label" for="agree_terms">
                                        <small>I confirm that I have reviewed the Accepted Bid Summary and acknowledge its contents.</small>
                                    </label>
                                </div>

                                <button type="submit" class="btn btn-primary btn-lg w-100" id="submitBtn" style="background-color: #0d6efd; border-color: #0d6efd; color: #ffffff;" disabled>
                                    {{ $userRole === 'tenant' ? 'Listing Owner' : 'Agent' }}: E-Sign Acknowledgement
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Row 2: Optional Document Sharing (listing owner only) ── --}}
            @if($userRole === 'tenant')
            <div class="row mt-2 mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm" style="border-left: 4px solid #0d6efd !important; border-radius: 10px;">
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
                            <a href="#signatureSection" class="btn btn-outline-secondary btn-sm" style="white-space: nowrap; font-size: 0.82rem;">
                                Skip for Now &darr;
                            </a>
                        </div>

                        <div class="card-body" style="padding: 24px;">

                            @if(session('doc_success'))
                            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                                <i class="fas fa-check-circle me-2"></i>{{ session('doc_success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            @endif

                            <form action="{{ route('accepted-bid-summary.store-documents', $summary->id) }}" method="POST" enctype="multipart/form-data" id="docForm">
                                @csrf

                                <div class="row g-4">

                                    {{-- ─── ID Document (all roles) ─── --}}
                                    <div class="col-md-6">
                                        <div class="doc-upload-card p-3 rounded" style="background: #f8f9fa; border: 1px solid #e9ecef;">
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

                                    {{-- ─── Buyer-specific: Proof of Funds ─── --}}
                                    @if(in_array($summary->listing_type, ['buyer']))
                                    <div class="col-md-6">
                                        <div class="doc-upload-card p-3 rounded" style="background: #f8f9fa; border: 1px solid #e9ecef;">
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

                                    {{-- ─── Buyer-specific: Pre-Approval Letter ─── --}}
                                    @if(in_array($summary->listing_type, ['buyer']))
                                    <div class="col-md-6">
                                        <div class="doc-upload-card p-3 rounded" style="background: #f8f9fa; border: 1px solid #e9ecef;">
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

                                    {{-- ─── Tenant-specific: Proof of Income ─── --}}
                                    @if(in_array($summary->listing_type, ['tenant']))
                                    <div class="col-md-6">
                                        <div class="doc-upload-card p-3 rounded" style="background: #f8f9fa; border: 1px solid #e9ecef;">
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

                                    {{-- ─── Seller / Landlord: Property Record Link ─── --}}
                                    @if(in_array($summary->listing_type, ['seller', 'landlord']))
                                    <div class="col-md-6">
                                        <div class="doc-upload-card p-3 rounded" style="background: #f8f9fa; border: 1px solid #e9ecef;">
                                            <label class="form-label fw-semibold mb-1" style="font-size: 0.9rem;">
                                                <i class="fas fa-link me-1 text-muted"></i> Property MLS / Public Record Link
                                            </label>
                                            <p class="text-muted mb-2" style="font-size: 0.8rem;">Link to the MLS listing, Zillow, or county property record page</p>
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

                                </div>{{-- /row.g-4 --}}

                                <div class="d-flex align-items-center mt-4 gap-3">
                                    <button type="submit" class="btn btn-primary px-4" style="font-weight: 600;">
                                        <i class="fas fa-cloud-upload-alt me-2"></i>Save Documents
                                    </button>
                                    <a href="#signatureSection" class="btn btn-link text-muted p-0" style="font-size: 0.9rem; text-decoration: none;">
                                        Skip for now — go to signature &darr;
                                    </a>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @endif
            {{-- ── end document sharing ── --}}

        </div>
    </div>
</div>

<style>
    .summary-preview {
        font-size: 0.9em;
    }
    .summary-preview h1 {
        font-size: 1.5em;
    }
    .summary-preview h2 {
        font-size: 1.2em;
    }
    .doc-upload-card {
        transition: border-color 0.2s;
    }
    .doc-upload-card:hover {
        border-color: #0d6efd !important;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var checkbox = document.getElementById('agree_terms');
    var submitBtn = document.getElementById('submitBtn');

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

        var abbr = getTimezoneAbbr(tz);
        document.getElementById('timezoneDisplay').value = abbr + ' (' + tz + ')';

        var now = new Date();
        var options = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        };
        var localTimeStr = now.toLocaleString('en-US', options);
        document.getElementById('localTimestamp').value = localTimeStr + ' (' + abbr + ')';
    } catch (e) {
        document.getElementById('timezone').value = 'UTC';
        document.getElementById('timezoneDisplay').value = 'Unknown (Could not detect)';
        document.getElementById('localTimestamp').value = new Date().toISOString();
    }

    function getTimezoneAbbr(tz) {
        if (!tz) return 'Unknown';
        var abbrs = {
            'America/New_York': 'ET',
            'America/Chicago': 'CT',
            'America/Denver': 'MT',
            'America/Los_Angeles': 'PT',
            'America/Phoenix': 'MST',
            'America/Anchorage': 'AKT',
            'Pacific/Honolulu': 'HST'
        };
        return abbrs[tz] || tz.split('/').pop().replace(/_/g, ' ');
    }
});
</script>
@endsection
