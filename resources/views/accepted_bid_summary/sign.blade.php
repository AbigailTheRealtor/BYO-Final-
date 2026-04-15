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
            {{-- Access = ownership (tenant_user_id), not role string. listing_type controls which fields appear. --}}
            @if($canUploadAcknowledgementDocuments)
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
                                            <p class="text-muted mb-2" style="font-size: 0.8rem;">Driver's license, passport, or state ID</p>

                                            @if(!empty($existingDocs?->id_document_path))
                                            <div class="d-flex align-items-center mb-2 p-2 rounded" style="background: #d1e7dd; font-size: 0.82rem;">
                                                <i class="fas fa-check-circle text-success me-2"></i>
                                                <span class="text-success fw-semibold">File uploaded</span>
                                                <span class="ms-auto text-muted">Replace below</span>
                                            </div>
                                            @endif

                                            <input type="file" class="form-control form-control-sm doc-file-input" name="id_document" accept=".pdf,.jpg,.jpeg,.png">
                                            <div class="doc-file-hint text-muted mt-1" style="font-size: 0.76rem;"><i class="fas fa-info-circle me-1"></i>Accepted: PDF, JPG, PNG &middot; Max 20 MB</div>
                                            <div class="doc-file-error text-danger mt-1 d-none" style="font-size: 0.78rem;"></div>
                                        </div>
                                    </div>

                                    {{-- ─── Buyer-specific: Proof of Funds ─── --}}
                                    @if(in_array($summary->listing_type, ['buyer']))
                                    <div class="col-md-6">
                                        <div class="doc-upload-card p-3 rounded" style="background: #f8f9fa; border: 1px solid #e9ecef;">
                                            <label class="form-label fw-semibold mb-1" style="font-size: 0.9rem;">
                                                <i class="fas fa-dollar-sign me-1 text-muted"></i> Proof of Funds
                                            </label>
                                            <p class="text-muted mb-2" style="font-size: 0.8rem;">Bank statement or asset account statement</p>

                                            @if(!empty($existingDocs?->proof_of_funds_path))
                                            <div class="d-flex align-items-center mb-2 p-2 rounded" style="background: #d1e7dd; font-size: 0.82rem;">
                                                <i class="fas fa-check-circle text-success me-2"></i>
                                                <span class="text-success fw-semibold">File uploaded</span>
                                                <span class="ms-auto text-muted">Replace below</span>
                                            </div>
                                            @endif

                                            <input type="file" class="form-control form-control-sm doc-file-input" name="proof_of_funds" accept=".pdf,.jpg,.jpeg,.png">
                                            <div class="doc-file-hint text-muted mt-1" style="font-size: 0.76rem;"><i class="fas fa-info-circle me-1"></i>Accepted: PDF, JPG, PNG &middot; Max 20 MB</div>
                                            <div class="doc-file-error text-danger mt-1 d-none" style="font-size: 0.78rem;"></div>
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
                                            <p class="text-muted mb-2" style="font-size: 0.8rem;">Mortgage pre-approval from a lender</p>

                                            @if(!empty($existingDocs?->pre_approval_letter_path))
                                            <div class="d-flex align-items-center mb-2 p-2 rounded" style="background: #d1e7dd; font-size: 0.82rem;">
                                                <i class="fas fa-check-circle text-success me-2"></i>
                                                <span class="text-success fw-semibold">File uploaded</span>
                                                <span class="ms-auto text-muted">Replace below</span>
                                            </div>
                                            @endif

                                            <input type="file" class="form-control form-control-sm doc-file-input" name="pre_approval_letter" accept=".pdf,.jpg,.jpeg,.png">
                                            <div class="doc-file-hint text-muted mt-1" style="font-size: 0.76rem;"><i class="fas fa-info-circle me-1"></i>Accepted: PDF, JPG, PNG &middot; Max 20 MB</div>
                                            <div class="doc-file-error text-danger mt-1 d-none" style="font-size: 0.78rem;"></div>
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
                                            <p class="text-muted mb-2" style="font-size: 0.8rem;">Pay stub, offer letter, or bank statements</p>

                                            @if(!empty($existingDocs?->proof_of_income_path))
                                            <div class="d-flex align-items-center mb-2 p-2 rounded" style="background: #d1e7dd; font-size: 0.82rem;">
                                                <i class="fas fa-check-circle text-success me-2"></i>
                                                <span class="text-success fw-semibold">File uploaded</span>
                                                <span class="ms-auto text-muted">Replace below</span>
                                            </div>
                                            @endif

                                            <input type="file" class="form-control form-control-sm doc-file-input" name="proof_of_income" accept=".pdf,.jpg,.jpeg,.png">
                                            <div class="doc-file-hint text-muted mt-1" style="font-size: 0.76rem;"><i class="fas fa-info-circle me-1"></i>Accepted: PDF, JPG, PNG &middot; Max 20 MB</div>
                                            <div class="doc-file-error text-danger mt-1 d-none" style="font-size: 0.78rem;"></div>
                                        </div>
                                    </div>
                                    @endif

                                    {{-- ─── Seller / Landlord: Property Record Link ─── --}}
                                    @if(in_array($summary->listing_type, ['seller', 'landlord']))
                                    <div class="col-md-6">
                                        <div class="doc-upload-card p-3 rounded" style="background: #f8f9fa; border: 1px solid #e9ecef;">
                                            <label class="form-label fw-semibold mb-1" style="font-size: 0.9rem;">
                                                <i class="fas fa-link me-1 text-muted"></i> Property Record Link
                                            </label>
                                            <p class="text-muted mb-2" style="font-size: 0.8rem;">Paste a public county property appraiser or property record link related to this property.</p>
                                            <input
                                                type="url"
                                                class="form-control form-control-sm doc-url-input"
                                                name="property_record_link"
                                                placeholder="https://..."
                                                value="{{ $existingDocs?->property_record_link ?? '' }}"
                                            >
                                        </div>
                                    </div>
                                    @endif

                                </div>{{-- /row.g-4 --}}

                                <div class="d-flex align-items-center mt-4 gap-3 flex-wrap">
                                    <button type="submit" class="btn btn-primary px-4" id="signSaveDocsBtn" style="font-weight: 600;" disabled>
                                        <i class="fas fa-cloud-upload-alt me-2"></i>Save Documents
                                    </button>
                                    <span class="text-muted" id="signSaveDocsBtnHint" style="font-size: 0.82rem;">
                                        Select at least one file to enable saving.
                                    </span>
                                    <a href="#signatureSection" class="btn btn-link text-muted p-0 ms-auto" style="font-size: 0.9rem; text-decoration: none;">
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

            {{-- ── Shared Documents (agent view) ── --}}
            @if($userRole === 'agent')
            @php
                $docTypes = [
                    'id_document'         => ['label' => 'Government-Issued ID',  'icon' => 'fas fa-id-card'],
                    'proof_of_funds'      => ['label' => 'Proof of Funds',         'icon' => 'fas fa-dollar-sign'],
                    'pre_approval_letter' => ['label' => 'Pre-Approval Letter',    'icon' => 'fas fa-file-signature'],
                    'proof_of_income'     => ['label' => 'Proof of Income',        'icon' => 'fas fa-file-invoice-dollar'],
                ];
                $pathMap = [
                    'id_document'         => 'id_document_path',
                    'proof_of_funds'      => 'proof_of_funds_path',
                    'pre_approval_letter' => 'pre_approval_letter_path',
                    'proof_of_income'     => 'proof_of_income_path',
                ];
                $hasAnyDoc = false;
                if ($sharedDocs) {
                    foreach ($pathMap as $col) {
                        if (!empty($sharedDocs->{$col})) { $hasAnyDoc = true; break; }
                    }
                    if (!$hasAnyDoc && !empty($sharedDocs->property_record_link)) {
                        $hasAnyDoc = true;
                    }
                }
            @endphp
            <div class="row mt-2 mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm" style="border-left: 4px solid #198754 !important; border-radius: 10px;">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #e9ecef; padding: 18px 24px;">
                            <div>
                                <h5 class="mb-1" style="color: #1a3a5c; font-weight: 700;">
                                    <i class="fas fa-folder-open me-2" style="color: #198754;"></i>
                                    Shared Documents
                                </h5>
                                <p class="mb-0 text-muted" style="font-size: 0.88rem;">
                                    Documents shared by the listing owner for this engagement.
                                </p>
                            </div>
                        </div>
                        <div class="card-body" style="padding: 24px;">
                            @if(!$sharedDocs || !$hasAnyDoc)
                                <p class="text-muted mb-0"><i class="fas fa-info-circle me-1"></i> No documents have been shared yet.</p>
                            @else
                                <div class="row g-3">
                                    @foreach($docTypes as $typeKey => $meta)
                                        @php $col = $pathMap[$typeKey]; $filePath = $sharedDocs->{$col} ?? null; @endphp
                                        @if(!empty($filePath))
                                        <div class="col-md-6">
                                            <div class="p-3 rounded d-flex align-items-center gap-3" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                                                <i class="{{ $meta['icon'] }} fa-lg text-success"></i>
                                                <div class="flex-grow-1 overflow-hidden">
                                                    <div class="fw-semibold" style="font-size: 0.9rem;">{{ $meta['label'] }}</div>
                                                    <div class="text-muted text-truncate" style="font-size: 0.78rem;">{{ basename($filePath) }}</div>
                                                </div>
                                                <a href="{{ route('accepted-bid-summary.download-document', ['id' => $summary->id, 'type' => $typeKey]) }}"
                                                   target="_blank"
                                                   class="btn btn-sm btn-outline-success flex-shrink-0">
                                                    <i class="fas fa-eye me-1"></i>View
                                                </a>
                                            </div>
                                        </div>
                                        @endif
                                    @endforeach
                                    @if(!empty($sharedDocs->property_record_link))
                                    <div class="col-md-6">
                                        <div class="p-3 rounded d-flex align-items-center gap-3" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                                            <i class="fas fa-link fa-lg text-success"></i>
                                            <div class="flex-grow-1 overflow-hidden">
                                                <div class="fw-semibold" style="font-size: 0.9rem;">Property Record Link</div>
                                                <a href="{{ $sharedDocs->property_record_link }}" target="_blank" rel="noopener" class="text-truncate d-block" style="font-size: 0.78rem;">{{ $sharedDocs->property_record_link }}</a>
                                            </div>
                                            <a href="{{ $sharedDocs->property_record_link }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success flex-shrink-0">
                                                <i class="fas fa-external-link-alt me-1"></i>Open
                                            </a>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif
            {{-- ── end shared documents ── --}}

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

@if($canUploadAcknowledgementDocuments)
<script>
(function () {
    var MAX_BYTES   = 20 * 1024 * 1024;
    var ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png'];
    var form        = document.getElementById('docForm');
    if (!form) return;

    var saveBtn  = document.getElementById('signSaveDocsBtn');
    var hintSpan = document.getElementById('signSaveDocsBtnHint');

    function getExt(filename) {
        return filename.split('.').pop().toLowerCase();
    }

    function validateAndSync() {
        var hasValidFile = false;
        var hasError     = false;

        form.querySelectorAll('input.doc-file-input').forEach(function (input) {
            var errEl = input.parentElement.querySelector('.doc-file-error');
            if (!errEl) return;

            if (input.files && input.files.length > 0) {
                var file = input.files[0];
                var ext  = getExt(file.name);

                if (!ALLOWED_EXT.includes(ext)) {
                    errEl.textContent = '\u26a0 Unsupported file type. Please upload a PDF, JPG, or PNG.';
                    errEl.classList.remove('d-none');
                    hasError = true;
                } else if (file.size > MAX_BYTES) {
                    errEl.textContent = '\u26a0 File exceeds the 20 MB limit. Please choose a smaller file.';
                    errEl.classList.remove('d-none');
                    hasError = true;
                } else {
                    errEl.textContent = '';
                    errEl.classList.add('d-none');
                    hasValidFile = true;
                }
            } else {
                errEl.textContent = '';
                errEl.classList.add('d-none');
            }
        });

        var urlInput = form.querySelector('input.doc-url-input');
        if (urlInput && urlInput.value.trim() !== '') {
            hasValidFile = true;
        }

        var canSave = hasValidFile && !hasError;
        saveBtn.disabled = !canSave;
        if (hintSpan) {
            hintSpan.textContent = canSave
                ? ''
                : (hasError ? 'Please fix the error above before saving.' : 'Select at least one file to enable saving.');
        }
    }

    form.querySelectorAll('input.doc-file-input').forEach(function (input) {
        input.addEventListener('change', validateAndSync);
    });

    var urlInput = form.querySelector('input.doc-url-input');
    if (urlInput) {
        urlInput.addEventListener('input', validateAndSync);
        if (urlInput.value.trim() !== '') {
            saveBtn.disabled = false;
            if (hintSpan) hintSpan.textContent = '';
        }
    }

    form.addEventListener('submit', function () {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving\u2026';
    });
})();
</script>
@endif

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
