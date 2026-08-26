{{-- MLS Import Modal — shared across all four Create Offer Listing forms --}}
{{--
    INDEPENDENT IMPORT MECHANISMS live in this modal. They are presented as
    separate, clearly-titled things because they are genuinely different
    things, not two spellings of the same one:

      1. "Import from Stellar MLS" — Bridge/Stellar OData lookup. Needs ONLY the
                              MLS number: no URL, no pasted text, no MLS or
                              Matrix login. Seller/Landlord only, behind the
                              mls_direct_import flags. Where the quick-import
                              flow is available this is a CARD that navigates to
                              it; where it is not, it is the inline "Import by
                              MLS #" field below.
      2. "Import from Listing Link" — the original scraper. Fetches a public
                              listing URL, or parses text the user pastes.
                              All four roles, never feature-gated, unchanged.
                              This is the path for anyone WITHOUT Stellar MLS
                              access and it holds no Bridge credentials.
      3. "Create Manually"  — dismisses the modal. The form behind it is already
                              the manual path, so there is nothing to set up.

    WHY THERE IS A CHOOSER
    ----------------------
    Both importers were once reachable from this one modal. When the quick
    import flow arrived, the button started redirecting straight into it, and
    the Listing Link importer lost its only entry point on Seller and Landlord.
    Stellar is an additional door, not a replacement one — so the modal now asks
    which door, rather than picking for the user.

    The chooser only appears where there is genuinely more than one mechanism
    ($this->importMethodChoiceAvailable()). Buyer and Tenant, and Seller/Landlord
    with quick import off, open straight onto the link importer's inputs exactly
    as they always have.

    Both importers converge on the SAME preview table and the same Apply Selected
    step, so the review-before-write behaviour is identical whichever was used.

    Do not relabel the URL field as an MLS # field — the two are not
    interchangeable, and a user who pastes a URL into the MLS # box (or types a
    bare number into the URL box) gets an error from the wrong subsystem.

    Required Livewire public properties on the host component:
      $showImportModal   (bool)
      $importMethod      (string: '' = chooser, 'link' = link importer)
      $importUrlInput    (string)
      $importRawText     (string)
      $importMlsNumber   (string)
      $importPreviewData (array)
      $importError       (string)
      $importSuccess     (bool)

    Required Livewire methods on the host component:
      importListingFromUrl()
      importListingByMlsNumber()
      mlsNumberImportAvailable()
      importMethodChoiceAvailable()
      chooseStellarMlsImport()
      chooseListingLinkImport()
      chooseManualListing()
      backToImportMethodChoice()
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

                {{-- ── Step 1: input ── --}}
                @if(empty($importPreviewData))

                {{-- ── Step 0: which import method? ──────────────────────────
                     Only where there is more than one. See the header note. --}}
                @if($importMethod === '' && $this->importMethodChoiceAvailable())

                <p class="text-muted mb-3">
                    Choose how you would like to start this listing.
                </p>

                <div class="row g-3">

                    {{-- Stellar MLS — for members, via our MLS data connection --}}
                    <div class="col-12 col-lg-6">
                        <div class="card h-100 border-primary">
                            <div class="card-body d-flex flex-column">
                                <h6 class="card-title fw-bold mb-1">
                                    <i class="fas fa-database me-2 text-primary"></i>Import from Stellar MLS
                                </h6>
                                <p class="card-text text-muted small flex-grow-1 mb-3">
                                    For Stellar MLS members. Enter the MLS number and we will pull the
                                    listing straight from our MLS data connection — no listing URL and
                                    no MLS login required.
                                </p>
                                <button type="button" class="btn btn-primary w-100"
                                        style="background-color:#0d6efd; border-color:#0d6efd; color:#fff;"
                                        wire:click="chooseStellarMlsImport"
                                        wire:loading.attr="disabled"
                                        wire:target="chooseStellarMlsImport">
                                    <span wire:loading.remove wire:target="chooseStellarMlsImport">
                                        <i class="fas fa-search me-1"></i>Use Stellar MLS #
                                    </span>
                                    <span wire:loading wire:target="chooseStellarMlsImport">
                                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>Opening…
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Listing Link — for everyone else. Never gated on Bridge. --}}
                    <div class="col-12 col-lg-6">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <h6 class="card-title fw-bold mb-1">
                                    <i class="fas fa-link me-2 text-primary"></i>Import from Listing Link
                                </h6>
                                <p class="card-text text-muted small flex-grow-1 mb-3">
                                    Not a Stellar MLS member? Paste the property's public listing URL
                                    and we will fill in what we can read from it. You can also paste the
                                    listing text instead.
                                </p>
                                <button type="button" class="btn btn-outline-primary w-100"
                                        style="color:#0d6efd;"
                                        wire:click="chooseListingLinkImport">
                                    <i class="fas fa-link me-1"></i>Use a Listing Link
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Manual — the form already behind this modal --}}
                <div class="d-flex align-items-center my-4">
                    <hr class="flex-grow-1 my-0">
                    <span class="px-3 text-muted small text-uppercase fw-semibold">Or</span>
                    <hr class="flex-grow-1 my-0">
                </div>

                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <div class="fw-semibold">Create Manually</div>
                        <div class="text-muted small">
                            No MLS number and no listing link — fill in the listing yourself.
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary text-nowrap"
                            style="color:#6c757d;"
                            wire:click="chooseManualListing">
                        <i class="fas fa-pen me-1"></i>Create Manually
                    </button>
                </div>

                @if($importError)
                    <div class="alert alert-danger py-2 mt-3">
                        <i class="fas fa-exclamation-circle me-1"></i>{{ $importError }}
                    </div>
                @endif

                {{-- ── A method is picked (or there was only ever one) ── --}}
                @else

                {{-- Back to the chooser. Rendered only where a chooser exists,
                     so it cannot strand Buyer/Tenant on a screen they never
                     render. --}}
                @if($this->importMethodChoiceAvailable())
                <div class="mb-3">
                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none"
                            wire:click="backToImportMethodChoice">
                        <i class="fas fa-arrow-left me-1"></i>Choose a different import method
                    </button>
                    <h6 class="fw-bold text-uppercase text-secondary small mt-2 mb-0">Import from Listing Link</h6>
                </div>
                @endif

                {{-- ── Option 1: Bridge lookup by MLS # (Seller/Landlord, flagged) ──
                     Inline ONLY where no chooser is in play. With the chooser up,
                     the Stellar card is this mechanism's entry point and a second
                     MLS # box on the link screen would just be a duplicate the
                     user already declined. --}}
                @if($this->mlsNumberImportAvailable() && ! $this->importMethodChoiceAvailable())
                <div class="mb-4">
                    <h6 class="fw-bold text-uppercase text-secondary small mb-2">Import by MLS #</h6>

                    <label for="mls-import-number" class="form-label fw-semibold">MLS #</label>
                    <div class="d-flex gap-2 align-items-start">
                        <input type="text" id="mls-import-number" class="form-control"
                               style="max-width:20rem;"
                               maxlength="64"
                               placeholder="e.g. A4567890"
                               autocomplete="off"
                               wire:model.defer="importMlsNumber"
                               wire:keydown.enter.prevent="importListingByMlsNumber">
                        <button type="button" class="btn btn-primary text-nowrap"
                                style="background-color:#0d6efd; border-color:#0d6efd; color:#fff;"
                                wire:click="importListingByMlsNumber"
                                wire:loading.attr="disabled"
                                wire:target="importListingByMlsNumber">
                            <span wire:loading.remove wire:target="importListingByMlsNumber">
                                <i class="fas fa-search me-1"></i>Find Listing
                            </span>
                            <span wire:loading wire:target="importListingByMlsNumber">
                                <span class="spinner-border spinner-border-sm me-1" role="status"></span>Searching…
                            </span>
                        </button>
                    </div>
                    <div class="form-text">
                        Enter the MLS number to find the listing through our MLS data connection.
                        No listing URL or login required.
                    </div>
                </div>

                <div class="d-flex align-items-center my-4">
                    <hr class="flex-grow-1 my-0">
                    <span class="px-3 text-muted small text-uppercase fw-semibold">Or</span>
                    <hr class="flex-grow-1 my-0">
                </div>

                <h6 class="fw-bold text-uppercase text-secondary small mb-2">Or import from a listing link</h6>
                @endif

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
                    <button type="button" class="btn btn-primary" style="background-color:#0d6efd; border-color:#0d6efd; color:#fff;"
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

                @endif{{-- /chooser vs picked-method --}}

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
                        <button type="button" class="btn btn-success" style="background-color:#198754; border-color:#198754; color:#fff;"
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
