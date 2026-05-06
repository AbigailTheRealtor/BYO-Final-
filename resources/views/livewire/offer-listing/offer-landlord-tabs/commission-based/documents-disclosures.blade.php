<h3 class="fw-bold mb-3">Documents &amp; Disclosures</h3>

<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>Upload and indicate the availability of key property documents and disclosures so Agents and Tenants have full access to the information they need before submitting an offer.</strong>
        </div>
    </div>
</div>

{{-- ===== GROUP 5: DOCUMENTS & DISCLOSURES ===== --}}
<div class="card border mb-4">
    <div class="card-header fw-bold bg-light">
        <i class="fa-solid fa-file-shield me-2 text-primary"></i>Documents &amp; Disclosures
    </div>
    <div class="card-body">

        {{-- Landlord Disclosure Available --}}
        <div class="form-group">
            <label class="fw-bold">Landlord Disclosure Available:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Indicate whether a completed Landlord Property Disclosure form is available for this listing. Landlord disclosures inform tenants of known property conditions and defects.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <select wire:model="landlord_disclosure_available" class="form-control has-icon"
                    data-icon="fa-solid fa-file-lines">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                    <option value="Not Applicable">Not Applicable</option>
                    <option value="Unknown">Unknown</option>
                </select>
            </div>
        </div>
        @if ($landlord_disclosure_available === 'Yes')
            <div class="conditional-upload-block">
                <label class="fw-bold">Upload Landlord Disclosure:</label>
                <p class="text-muted small mb-1">Upload the document related to this disclosure.</p>
                <input type="file" wire:model="landlord_disclosure_file" class="form-control"
                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                <div wire:loading wire:target="landlord_disclosure_file" class="text-muted small mt-1">
                    <span class="spinner-border spinner-border-sm" role="status"></span> Uploading…
                </div>
                @error('landlord_disclosure_file') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @if ($landlord_disclosure_file_path)
                    <div class="mt-1 small">
                        <a href="{{ \Illuminate\Support\Facades\Storage::url($landlord_disclosure_file_path) }}" target="_blank" class="text-primary">
                            <i class="fa-solid fa-file me-1"></i>View current file
                        </a>
                    </div>
                @endif
            </div>
        @endif

        {{-- Survey Available --}}
        <div class="form-group mt-3">
            <label class="fw-bold">Survey Available:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Indicate whether a current property survey is available. A survey shows the legal boundaries, improvements, and easements of the property.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <select wire:model="survey_available" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                    <option value="Not Applicable">Not Applicable</option>
                    <option value="Unknown">Unknown</option>
                </select>
            </div>
        </div>
        @if ($survey_available === 'Yes')
            <div class="conditional-upload-block">
                <label class="fw-bold">Upload Survey:</label>
                <p class="text-muted small mb-1">Upload the document related to this disclosure.</p>
                <input type="file" wire:model="survey_file" class="form-control"
                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                <div wire:loading wire:target="survey_file" class="text-muted small mt-1">
                    <span class="spinner-border spinner-border-sm" role="status"></span> Uploading…
                </div>
                @error('survey_file') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @if ($survey_file_path)
                    <div class="mt-1 small">
                        <a href="{{ \Illuminate\Support\Facades\Storage::url($survey_file_path) }}" target="_blank" class="text-primary">
                            <i class="fa-solid fa-file me-1"></i>View current file
                        </a>
                    </div>
                @endif
            </div>
        @endif

        {{-- Inspection Report Available --}}
        <div class="form-group mt-3">
            <label class="fw-bold">Inspection Report Available:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Indicate whether a recent property inspection report is available. Pre-listing inspections can increase tenant confidence and reduce negotiation friction.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <select wire:model="inspection_report_available" class="form-control has-icon"
                    data-icon="fa-solid fa-magnifying-glass">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                    <option value="Not Applicable">Not Applicable</option>
                    <option value="Unknown">Unknown</option>
                </select>
            </div>
        </div>
        @if ($inspection_report_available === 'Yes')
            <div class="conditional-upload-block">
                <label class="fw-bold">Upload Inspection Report:</label>
                <p class="text-muted small mb-1">Upload the document related to this disclosure.</p>
                <input type="file" wire:model="inspection_report_file" class="form-control"
                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                <div wire:loading wire:target="inspection_report_file" class="text-muted small mt-1">
                    <span class="spinner-border spinner-border-sm" role="status"></span> Uploading…
                </div>
                @error('inspection_report_file') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @if ($inspection_report_file_path)
                    <div class="mt-1 small">
                        <a href="{{ \Illuminate\Support\Facades\Storage::url($inspection_report_file_path) }}" target="_blank" class="text-primary">
                            <i class="fa-solid fa-file me-1"></i>View current file
                        </a>
                    </div>
                @endif
            </div>
        @endif

        {{-- HOA/Condo Documents Available --}}
        <div class="form-group mt-3">
            <label class="fw-bold">HOA/Condo Documents Available:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Indicate whether HOA or condominium governing documents are available, including CC&Rs, bylaws, rules and regulations, and financial statements.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <select wire:model="hoa_condo_docs_available" class="form-control has-icon"
                    data-icon="fa-solid fa-building-user">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                    <option value="Not Applicable">Not Applicable</option>
                    <option value="Unknown">Unknown</option>
                </select>
            </div>
        </div>
        @if ($hoa_condo_docs_available === 'Yes')
            <div class="conditional-upload-block">
                <label class="fw-bold">Upload HOA/Condo Documents:</label>
                <p class="text-muted small mb-1">Upload the document related to this disclosure.</p>
                <input type="file" wire:model="hoa_condo_docs_file" class="form-control"
                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                <div wire:loading wire:target="hoa_condo_docs_file" class="text-muted small mt-1">
                    <span class="spinner-border spinner-border-sm" role="status"></span> Uploading…
                </div>
                @error('hoa_condo_docs_file') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @if ($hoa_condo_docs_file_path)
                    <div class="mt-1 small">
                        <a href="{{ \Illuminate\Support\Facades\Storage::url($hoa_condo_docs_file_path) }}" target="_blank" class="text-primary">
                            <i class="fa-solid fa-file me-1"></i>View current file
                        </a>
                    </div>
                @endif
            </div>
        @endif

        {{-- Flood Disclosure Available --}}
        <div class="form-group mt-3">
            <label class="fw-bold">Flood Disclosure Available:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Indicate whether a flood zone disclosure or flood history disclosure is available for this property.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <select wire:model="flood_disclosure_available" class="form-control has-icon"
                    data-icon="fa-solid fa-water">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                    <option value="Not Applicable">Not Applicable</option>
                    <option value="Unknown">Unknown</option>
                </select>
            </div>
        </div>
        @if ($flood_disclosure_available === 'Yes')
            <div class="conditional-upload-block">
                <label class="fw-bold">Upload Flood Disclosure:</label>
                <p class="text-muted small mb-1">Upload the document related to this disclosure.</p>
                <input type="file" wire:model="flood_disclosure_file" class="form-control"
                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                <div wire:loading wire:target="flood_disclosure_file" class="text-muted small mt-1">
                    <span class="spinner-border spinner-border-sm" role="status"></span> Uploading…
                </div>
                @error('flood_disclosure_file') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @if ($flood_disclosure_file_path)
                    <div class="mt-1 small">
                        <a href="{{ \Illuminate\Support\Facades\Storage::url($flood_disclosure_file_path) }}" target="_blank" class="text-primary">
                            <i class="fa-solid fa-file me-1"></i>View current file
                        </a>
                    </div>
                @endif
            </div>
        @endif

        {{-- Lead-Based Paint Disclosure Required --}}
        <div class="form-group mt-3">
            <label class="fw-bold">Lead-Based Paint Disclosure Required:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Federal law requires landlords to disclose known lead-based paint and hazards for homes built before 1978. Select Yes if this disclosure is required for this property.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <select wire:model="lead_based_paint_disclosure" class="form-control has-icon"
                    data-icon="fa-solid fa-triangle-exclamation">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                    <option value="Unknown">Unknown</option>
                </select>
            </div>
        </div>
        @if ($lead_based_paint_disclosure === 'Yes')
            <div class="conditional-upload-block">
                <label class="fw-bold">Upload Lead-Based Paint Disclosure:</label>
                <p class="text-muted small mb-1">Upload the document related to this disclosure.</p>
                <input type="file" wire:model="lead_based_paint_file" class="form-control"
                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                <div wire:loading wire:target="lead_based_paint_file" class="text-muted small mt-1">
                    <span class="spinner-border spinner-border-sm" role="status"></span> Uploading…
                </div>
                @error('lead_based_paint_file') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @if ($lead_based_paint_file_path)
                    <div class="mt-1 small">
                        <a href="{{ \Illuminate\Support\Facades\Storage::url($lead_based_paint_file_path) }}" target="_blank" class="text-primary">
                            <i class="fa-solid fa-file me-1"></i>View current file
                        </a>
                    </div>
                @endif
            </div>
        @endif

        {{-- Environmental Report Available --}}
        <div class="form-group mt-3">
            <label class="fw-bold">Environmental Report Available:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Indicate whether an environmental assessment or report (such as a Phase I or Phase II ESA) is available for this property.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <select wire:model="environmental_report_available" class="form-control has-icon"
                    data-icon="fa-solid fa-leaf">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                    <option value="Not Applicable">Not Applicable</option>
                    <option value="Unknown">Unknown</option>
                </select>
            </div>
        </div>
        @if ($environmental_report_available === 'Yes')
            <div class="conditional-upload-block">
                <label class="fw-bold">Upload Environmental Report:</label>
                <p class="text-muted small mb-1">Upload the document related to this disclosure.</p>
                <input type="file" wire:model="environmental_report_file" class="form-control"
                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                <div wire:loading wire:target="environmental_report_file" class="text-muted small mt-1">
                    <span class="spinner-border spinner-border-sm" role="status"></span> Uploading…
                </div>
                @error('environmental_report_file') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @if ($environmental_report_file_path)
                    <div class="mt-1 small">
                        <a href="{{ \Illuminate\Support\Facades\Storage::url($environmental_report_file_path) }}" target="_blank" class="text-primary">
                            <i class="fa-solid fa-file me-1"></i>View current file
                        </a>
                    </div>
                @endif
            </div>
        @endif

        {{-- Additional Documents Available (Select2 multi-select) --}}
        <div class="form-group mt-3">
            <label class="fw-bold">Additional Documents Available:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Select any additional documents available for this listing. You may select multiple options or type to add a custom entry.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            @php
                $additionalDocumentOptions = [
                    'Appraisal Report', 'As-Built Plans / Floor Plans', 'Certificate of Occupancy',
                    'Drainage / Stormwater Report', 'Elevation Certificate', 'Energy Audit Report',
                    'Geotechnical / Soil Report', 'Hazardous Materials Report', 'Historic Designation Documents',
                    'Lease Agreements (Existing Tenants)', 'Maintenance Records', 'Permits & Permit History',
                    'Roof Certification', 'Septic Inspection Report', 'Title Insurance Commitment',
                    'Utility Bills / History', 'Warranty Documents', 'Well Water Test Report', 'Other',
                ];
            @endphp
            <div class="input-cover has-select-icon" wire:ignore>
                <select id="additional_documents" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-folder-open" data-placeholder="Select" multiple>
                    <option value=""></option>
                    @foreach ($additionalDocumentOptions as $option)
                        <option value="{{ $option }}" {{ in_array($option, $additional_documents ?? []) ? 'selected' : '' }}>
                            {{ $option }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div id="additional-documents-other-section" wire:ignore.self style="display: {{ in_array('Other', $additional_documents ?? []) ? 'block' : 'none' }}">
            <div class="form-group mt-2">
                <label class="fw-bold">Other Document Type:
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                        title="Describe the additional document type that is available for this property.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                </label>
                <div class="input-cover">
                    <input type="text" wire:model="other_document_type" class="form-control has-icon"
                        data-icon="fa-solid fa-folder-open"
                        placeholder="Enter document type (e.g., Lead Paint Disclosure, Inspection Report)">
                </div>
            </div>
        </div>

    </div>
</div>
