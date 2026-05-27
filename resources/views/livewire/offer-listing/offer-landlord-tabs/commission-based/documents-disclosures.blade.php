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

        {{-- Property Documents & Disclosures (Repeatable Rows) --}}
        <div class="form-group">
            <label class="fw-bold">Property Documents &amp; Disclosures:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Add any property documents and disclosures available for this listing. At least one row is shown by default; add more as needed. Documents are optional.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>

            @php
                $landlordDocTypeOptions = [
                    'Appraisal Report',
                    'As-Built Plans / Floor Plans',
                    'Certificate of Occupancy',
                    'Drainage / Stormwater Report',
                    'Elevation Certificate',
                    'Energy Audit Report',
                    'Environmental Report',
                    'Flood Disclosure',
                    'Geotechnical / Soil Report',
                    'Hazardous Materials Report',
                    'Historic Designation Documents',
                    'HOA/Condo Documents',
                    'Inspection Report',
                    'Landlord Disclosure',
                    'Lead-Based Paint Disclosure',
                    'Lease Agreements (Existing Tenants)',
                    'Maintenance Records',
                    'Permits & Permit History',
                    'Roof Certification',
                    'Seller Disclosure',
                    'Septic Inspection Report',
                    'Survey',
                    'Title Insurance Commitment',
                    'Utility Bills / History',
                    'Warranty Documents',
                    'Well Water Test Report',
                    'Other',
                ];
                $blankLandlordDocRow    = ['type' => '', 'custom_type' => '', 'description' => '', 'stored_path' => '', 'original_name' => ''];
                $initialLandlordDocRows = !empty($landlord_doc_rows) ? $landlord_doc_rows : [$blankLandlordDocRow];
            @endphp

            <div
                x-data="{
                    rows: {{ json_encode($initialLandlordDocRows) }},
                    typeOptions: {{ json_encode($landlordDocTypeOptions) }},
                    uploading: [],
                    _offFileStored: null,
                    _offFileRemoved: null,
                    init() {
                        this.uploading = this.rows.map(() => false);
                        this._offFileStored = Livewire.on('landlordDocFileStored', (index, path, originalName) => {
                            if (this.rows[index] !== undefined) {
                                this.rows[index].stored_path   = path;
                                this.rows[index].original_name = originalName;
                                this.uploading[index]          = false;
                                this.sync();
                            }
                        });
                        this._offFileRemoved = Livewire.on('landlordDocRowFileRemoved', (index) => {
                            if (this.rows[index] !== undefined) {
                                this.rows[index].stored_path   = '';
                                this.rows[index].original_name = '';
                                this.sync();
                            }
                        });
                    },
                    destroy() {
                        if (typeof this._offFileStored === 'function') this._offFileStored();
                        if (typeof this._offFileRemoved === 'function') this._offFileRemoved();
                    },
                    addRow() {
                        this.rows.push({ type: '', custom_type: '', description: '', stored_path: '', original_name: '' });
                        this.uploading.push(false);
                        this.$nextTick(() => this.sync());
                    },
                    removeRow(index) {
                        if (this.rows.length <= 1) return;
                        this.rows.splice(index, 1);
                        this.uploading.splice(index, 1);
                        this.sync();
                    },
                    sync() {
                        @this.set('landlord_doc_rows', this.rows, false);
                    },
                    uploadFile(index, event) {
                        const file = event.target.files[0];
                        if (!file) return;
                        this.uploading[index] = true;
                        @this.set('landlordDocFileIndex', index).then(() => {
                            @this.upload('landlordDocFileUpload', file,
                                () => {},
                                () => { this.uploading[index] = false; },
                                () => {}
                            );
                        });
                    }
                }"
                wire:ignore
            >
                <template x-for="(row, index) in rows" :key="index">
                    <div class="border rounded p-3 mb-2">

                        {{-- Type dropdown + Remove button --}}
                        <div class="d-flex align-items-end gap-2 mb-2">
                            <div class="flex-grow-1">
                                <label class="small text-muted mb-1">Document Type</label>
                                <select class="form-control" x-model="row.type" @change="sync()">
                                    <option value="">— Select document type —</option>
                                    <template x-for="opt in typeOptions" :key="opt">
                                        <option :value="opt" :selected="row.type === opt" x-text="opt"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="flex-shrink-0">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    @click="removeRow(index)"
                                    :disabled="rows.length <= 1"
                                    title="Remove row"
                                    x-bind:style="rows.length <= 1 ? 'opacity:0.4;pointer-events:none' : ''"
                                >
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>
                        </div>

                        {{-- Custom type (Other only) --}}
                        <div class="mb-2" x-show="row.type === 'Other'">
                            <label class="small text-muted mb-1">Custom Document Name</label>
                            <input
                                type="text"
                                class="form-control"
                                placeholder="Enter custom document name (e.g., Lead-based paint disclosure, Flood zone notice)"
                                x-model="row.custom_type"
                                @input="sync()"
                            >
                        </div>

                        {{-- Description --}}
                        <div class="mb-2">
                            <label class="small text-muted mb-1">Description <span class="fw-normal text-muted">(optional)</span></label>
                            <textarea
                                class="form-control"
                                rows="2"
                                placeholder="Enter a brief description of this document"
                                x-model="row.description"
                                @input="sync()"
                            ></textarea>
                        </div>

                        {{-- File upload / existing file --}}
                        <div>
                            <div x-show="!row.stored_path">
                                <label class="small text-muted mb-1">Upload Document</label>
                                <input
                                    type="file"
                                    class="form-control form-control-sm"
                                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                    @change="uploadFile(index, $event)"
                                    :disabled="uploading[index]"
                                >
                                <div x-show="uploading[index]" class="text-muted small mt-1">
                                    <i class="fa-solid fa-spinner fa-spin me-1"></i>Uploading…
                                </div>
                            </div>
                            <div x-show="row.stored_path" class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="small text-muted" x-text="row.original_name"></span>
                                <a
                                    :href="'/storage/' + row.stored_path"
                                    target="_blank"
                                    class="btn btn-sm btn-outline-secondary"
                                >
                                    <i class="fa-solid fa-file me-1"></i>View Current File
                                </a>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    @click="@this.call('removeLandlordDocRowFile', index)"
                                >
                                    <i class="fa-solid fa-trash me-1"></i>Remove File
                                </button>
                            </div>
                        </div>

                    </div>
                </template>

                <button type="button" class="btn btn-sm btn-outline-primary mt-1" @click="addRow()">
                    <i class="fa-solid fa-plus me-1"></i>Add Another Document
                </button>
            </div>
        </div>

    </div>
</div>
