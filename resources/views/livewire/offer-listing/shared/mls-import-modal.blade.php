{{-- MLS Import Modal — shared across all four Create Offer Listing forms --}}
{{--
    Required Livewire public properties on the host component:
      $showImportModal   (bool)
      $importUrlInput    (string)
      $importRawText     (string)
      $importPreviewData (array)
      $importError       (string)
      $importSuccess     (bool)

    Required Livewire methods on the host component:
      importListingFromUrl()
      applyImportedFields(array $selected, array $overrideKeys)
      closeImportModal()
--}}

@if($showImportModal)
<div class="modal fade show d-block" id="mlsImportModal" tabindex="-1" role="dialog"
     aria-labelledby="mlsImportModalLabel" style="background:rgba(0,0,0,.5); z-index:1060;">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">

            {{-- Header --}}
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-semibold" id="mlsImportModalLabel">
                    <i class="fas fa-file-import me-2 text-primary"></i>Import from MLS Listing
                </h5>
                <button type="button" class="btn-close" wire:click="closeImportModal" aria-label="Close"></button>
            </div>

            <div class="modal-body">

                {{-- ── Step 1: URL / Raw Text Input ── --}}
                @if(empty($importPreviewData))
                <div class="mb-3">
                    <label for="mls-import-url" class="form-label fw-semibold">Public MLS / Matrix Listing URL</label>
                    <input type="url" id="mls-import-url" class="form-control"
                           placeholder="https://www.stellarmls.com/matrix/…"
                           wire:model.defer="importUrlInput">
                    <div class="form-text">Paste a public listing URL (no login required) and click <strong>Import</strong>.</div>
                </div>

                <div class="mb-3">
                    <label for="mls-import-raw" class="form-label fw-semibold">
                        Or paste raw listing text&nbsp;<span class="text-muted fw-normal">(optional)</span>
                    </label>
                    <textarea id="mls-import-raw" class="form-control" rows="6"
                              placeholder="Paste the full listing text here if the URL is not publicly accessible…"
                              wire:model.defer="importRawText"></textarea>
                </div>

                @if($importError)
                    <div class="alert alert-danger py-2">
                        <i class="fas fa-exclamation-circle me-1"></i>{{ $importError }}
                    </div>
                @endif

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" style="color:#fff;"
                            wire:click="importListingFromUrl"
                            wire:loading.attr="disabled"
                            wire:target="importListingFromUrl">
                        <span wire:loading.remove wire:target="importListingFromUrl">
                            <i class="fas fa-search me-1"></i>Import
                        </span>
                        <span wire:loading wire:target="importListingFromUrl">
                            <span class="spinner-border spinner-border-sm me-1" role="status"></span>Fetching…
                        </span>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" style="color:#6c757d;" wire:click="closeImportModal">Cancel</button>
                </div>

                {{-- ── Step 2: Preview Table ── --}}
                @else
                <div x-data="{
                    rows: ({{ json_encode(array_values($importPreviewData)) }}).map(function(r) {
                        return Object.assign({}, r, { checked: !r.has_existing_value });
                    }),
                    get allChecked() {
                        return this.rows.length > 0 && this.rows.every(function(r) { return r.checked; });
                    },
                    toggleAll() {
                        var next = !this.allChecked;
                        this.rows = this.rows.map(function(r) { return Object.assign({}, r, { checked: next }); });
                    },
                    selectedKeys() {
                        return this.rows.filter(function(r) { return r.checked; }).map(function(r) { return r.canonical_key; });
                    },
                    overrideKeys() {
                        return this.rows.filter(function(r) { return r.checked && r.has_existing_value; }).map(function(r) { return r.canonical_key; });
                    }
                }">
                    <div class="alert alert-warning py-2 mb-3">
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Imported listing data is provided for convenience only and should be reviewed for accuracy before publishing.</strong>
                    </div>

                    <p class="text-muted small mb-2">
                        Review the extracted fields below. Fields marked
                        <span class="badge bg-warning text-dark">will overwrite</span> already have a value in the form
                        and are <strong>unchecked by default</strong> — tick the checkbox to allow them to be overwritten.
                        Click <strong>Apply Selected</strong> when ready.
                    </p>

                    <div class="mb-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="toggleAll()">
                            <span x-text="allChecked ? 'Uncheck All' : 'Check All'"></span>
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:2.5rem;" class="text-center">
                                        {{-- One-way bind + click.prevent avoids the double-toggle
                                             that x-model + @change would cause --}}
                                        <input type="checkbox" :checked="allChecked"
                                               @click.prevent="toggleAll()" title="Select / deselect all">
                                    </th>
                                    <th>Imported Field</th>
                                    <th>Form Field</th>
                                    <th>Imported Value</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(row, idx) in rows" :key="idx">
                                    <tr :class="{ 'table-warning': row.has_existing_value && row.checked }">
                                        <td class="text-center">
                                            <input type="checkbox" x-model="row.checked">
                                        </td>
                                        <td x-text="row.label" class="fw-semibold"></td>
                                        <td><code x-text="row.prop_name" class="text-secondary small"></code></td>
                                        <td class="text-break" x-text="row.value" style="max-width:260px;"></td>
                                        <td>
                                            <template x-if="row.has_existing_value">
                                                <span class="badge bg-warning text-dark">will overwrite</span>
                                            </template>
                                            <template x-if="!row.has_existing_value">
                                                <span class="badge bg-success">empty — safe to fill</span>
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="button" class="btn btn-success" style="color:#fff;"
                                @click="$wire.applyImportedFields(selectedKeys(), overrideKeys())"
                                wire:loading.attr="disabled"
                                wire:target="applyImportedFields">
                            <span wire:loading.remove wire:target="applyImportedFields">
                                <i class="fas fa-check me-1"></i>Apply Selected
                            </span>
                            <span wire:loading wire:target="applyImportedFields">
                                <span class="spinner-border spinner-border-sm me-1" role="status"></span>Applying…
                            </span>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" style="color:#6c757d;" wire:click="closeImportModal">Cancel</button>
                    </div>
                </div>
                @endif

                {{-- ── Post-apply success notice ── --}}
                @if($importSuccess && empty($importPreviewData))
                <div class="alert alert-success py-2 mt-3">
                    <i class="fas fa-check-circle me-1"></i>
                    <strong>Imported fields were applied.</strong> Please review all values before publishing.
                </div>
                @endif

            </div>{{-- /.modal-body --}}
        </div>{{-- /.modal-content --}}
    </div>{{-- /.modal-dialog --}}
</div>
@endif
