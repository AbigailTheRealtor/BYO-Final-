<div>
    {{-- ── Flash toast (driven by browser event, not server re-render) ─── --}}
    <div id="offer-flash-toast" style="display:none;position:fixed;top:1rem;right:1rem;z-index:9999;min-width:280px;" role="alert">
        <div class="alert mb-0 shadow" id="offer-flash-inner"></div>
    </div>

    {{-- Page header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1 fw-bold">
                @if($offer_type === 'sale')   <i class="fa fa-tag me-2" style="color:#049399;"></i>Sale Offer Listing
                @elseif($offer_type === 'rental') <i class="fa fa-home me-2" style="color:#049399;"></i>Rental Offer Listing
                @elseif($offer_type === 'lease')  <i class="fa fa-key me-2" style="color:#049399;"></i>Lease Offer Listing
                @else <i class="fa fa-file-lines me-2" style="color:#049399;"></i>New Offer Listing
                @endif
            </h4>
            <p class="text-muted mb-0 small">
                @if($auctionId)
                    Listing #{{ $auctionId }} &bull; <span class="badge bg-{{ $this->getStatusBadgeClass() }}">{{ $this->getStatusLabel() }}</span>
                @else
                    Fill in each section, then save a draft or publish.
                @endif
            </p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="saveDraft" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="saveDraft"><i class="fa fa-save me-1"></i>Save Draft</span>
                <span wire:loading wire:target="saveDraft">Saving…</span>
            </button>
            <button type="button" class="btn btn-sm text-white fw-semibold" style="background:#049399;" wire:click="submitListing" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="submitListing"><i class="fa fa-circle-check me-1"></i>Publish Listing</span>
                <span wire:loading wire:target="submitListing">Publishing…</span>
            </button>
        </div>
    </div>

    {{-- Validation errors --}}
    @if($errors->any())
    <div class="alert alert-danger mb-3">
        <ul class="mb-0 ps-3">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- Tab navigation (wire:ignore.self on buttons prevents morphdom resetting Bootstrap active state) --}}
    <ul class="nav nav-tabs mb-4" id="offerWizardTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button wire:ignore.self class="nav-link active" id="offer-tab-1" data-bs-toggle="tab" data-bs-target="#offer-panel-1"
                type="button" role="tab">
                <span class="badge me-1 text-white" style="background:#049399;">1</span> Overview
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button wire:ignore.self class="nav-link" id="offer-tab-2" data-bs-toggle="tab" data-bs-target="#offer-panel-2"
                type="button" role="tab">
                <span class="badge me-1 bg-secondary">2</span> Financial Terms
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button wire:ignore.self class="nav-link" id="offer-tab-3" data-bs-toggle="tab" data-bs-target="#offer-panel-3"
                type="button" role="tab">
                <span class="badge me-1 bg-secondary">3</span> Contingencies &amp; Dates
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button wire:ignore.self class="nav-link" id="offer-tab-4" data-bs-toggle="tab" data-bs-target="#offer-panel-4"
                type="button" role="tab">
                <span class="badge me-1 bg-secondary">4</span> Review &amp; Terms
            </button>
        </li>
    </ul>

    <div class="tab-content" id="offerWizardTabsContent">

        {{-- PANEL 1: OVERVIEW --}}
        <div wire:ignore.self class="tab-pane fade show active" id="offer-panel-1" role="tabpanel">
        <div class="card border-0 shadow-sm p-4">
            <h6 class="fw-bold mb-3" style="color:#049399;"><i class="fa fa-circle-info me-2"></i>Listing Overview</h6>

            <div class="mb-3">
                <label class="form-label fw-semibold">Offer Type <span class="text-danger">*</span></label>
                <div class="d-flex gap-3 flex-wrap">
                    @foreach(['sale' => ['tag','Sale (Purchase Offer)'], 'rental' => ['home','Rental Offer'], 'lease' => ['key','Lease Offer']] as $val => [$icon, $lbl])
                    <div class="p-0">
                        <input class="visually-hidden" type="radio" id="ot_{{ $val }}" wire:model="offer_type" value="{{ $val }}">
                        <label class="btn btn-outline-secondary px-3 py-2" for="ot_{{ $val }}"
                            style="{{ $offer_type === $val ? 'background:#049399;color:#fff;border-color:#049399;' : '' }}">
                            <i class="fa fa-{{ $icon }} me-1"></i>{{ $lbl }}
                        </label>
                    </div>
                    @endforeach
                </div>
                @error('offer_type')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Listing Title <span class="text-muted small">(optional)</span></label>
                <input type="text" class="form-control" wire:model.lazy="listing_title" placeholder="e.g. 3BR Offer – 123 Maple St">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Property Address <span class="text-danger">*</span></label>
                <input type="text" class="form-control" wire:model.lazy="property_address" placeholder="Street address">
                @error('property_address')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">City</label>
                    <input type="text" class="form-control" wire:model.lazy="city">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">State</label>
                    <input type="text" class="form-control" wire:model.lazy="state" maxlength="2" placeholder="e.g. CA">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">ZIP Code</label>
                    <input type="text" class="form-control" wire:model.lazy="zip_code">
                </div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Property Type</label>
                    <select class="form-select" wire:model="property_type">
                        <option value="">Select…</option>
                        <option value="house">House</option>
                        <option value="condo">Condo</option>
                        <option value="apartment">Apartment</option>
                        <option value="townhouse">Townhouse</option>
                        <option value="commercial">Commercial</option>
                        <option value="land">Land</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Beds</label>
                    <input type="number" class="form-control" wire:model.lazy="bedrooms" min="0" placeholder="—">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Baths</label>
                    <input type="number" class="form-control" wire:model.lazy="bathrooms" min="0" step="0.5" placeholder="—">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Sq Ft</label>
                    <input type="number" class="form-control" wire:model.lazy="sqft" min="0" placeholder="—">
                </div>
            </div>
            <div class="d-flex justify-content-end mt-2">
                <button type="button" class="btn btn-sm text-white" style="background:#049399;"
                    onclick="offerWizardGoTo('offer-tab-2')">
                    Next: Financial Terms <i class="fa fa-arrow-right ms-1"></i>
                </button>
            </div>
        </div>
        </div>

        {{-- PANEL 2: FINANCIAL TERMS --}}
        <div wire:ignore.self class="tab-pane fade" id="offer-panel-2" role="tabpanel">
        <div class="card border-0 shadow-sm p-4">
            <h6 class="fw-bold mb-3" style="color:#049399;"><i class="fa fa-dollar-sign me-2"></i>Financial Terms</h6>

            {{-- Sale fields --}}
            <div id="offer-sale-fields">
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        Offer Price ($)
                        @if($offer_type === 'sale')<span class="text-danger">*</span>@endif
                    </label>
                    <input type="number" class="form-control" wire:model.lazy="offer_price" min="0" step="1000" placeholder="e.g. 450000">
                    @error('offer_price')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Earnest Money Deposit ($)</label>
                    <input type="number" class="form-control" wire:model.lazy="earnest_deposit" min="0" step="100">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Financing Type</label>
                    <select class="form-select" wire:model="financing_type">
                        <option value="cash">All Cash</option>
                        <option value="conventional">Conventional</option>
                        <option value="fha">FHA</option>
                        <option value="va">VA</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                @if($financing_type !== 'cash')
                <div class="mb-3">
                    <label class="form-label fw-semibold">Down Payment (%)</label>
                    <input type="number" class="form-control" wire:model.lazy="down_payment_percent" min="0" max="100" step="0.5">
                </div>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="fin_cont" wire:model="financing_contingency">
                        <label class="form-check-label fw-semibold" for="fin_cont">Financing Contingency</label>
                    </div>
                    @if($financing_contingency)
                    <div class="mt-2">
                        <label class="form-label small">Contingency Period (days)</label>
                        <input type="number" class="form-control form-control-sm w-auto" wire:model.lazy="financing_contingency_days" min="1" max="90" placeholder="e.g. 21">
                    </div>
                    @endif
                </div>
                @endif
            </div>

            {{-- Rental/Lease fields --}}
            <div id="offer-rental-fields" style="{{ in_array($offer_type, ['rental','lease']) ? '' : 'display:none' }}">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Monthly Rent ($) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" wire:model.lazy="monthly_rent" min="0" step="50">
                    @error('monthly_rent')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Security Deposit ($)</label>
                    <input type="number" class="form-control" wire:model.lazy="security_deposit" min="0" step="50">
                </div>
                @if($offer_type === 'lease')
                <div class="mb-3">
                    <label class="form-label fw-semibold">Lease Term (months)</label>
                    <input type="number" class="form-control w-auto" wire:model.lazy="lease_term_months" min="1" max="360" placeholder="e.g. 12">
                </div>
                @endif
            </div>

            <div class="d-flex justify-content-between mt-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="offerWizardGoTo('offer-tab-1')">
                    <i class="fa fa-arrow-left me-1"></i> Back
                </button>
                <button type="button" class="btn btn-sm text-white" style="background:#049399;" onclick="offerWizardGoTo('offer-tab-3')">
                    Next: Contingencies &amp; Dates <i class="fa fa-arrow-right ms-1"></i>
                </button>
            </div>
        </div>
        </div>

        {{-- PANEL 3: CONTINGENCIES & DATES --}}
        <div wire:ignore.self class="tab-pane fade" id="offer-panel-3" role="tabpanel">
        <div class="card border-0 shadow-sm p-4">
            <h6 class="fw-bold mb-3" style="color:#049399;"><i class="fa fa-calendar me-2"></i>Contingencies &amp; Dates</h6>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="insp_cont" wire:model="inspection_contingency">
                    <label class="form-check-label fw-semibold" for="insp_cont">Inspection Contingency</label>
                </div>
                @if($inspection_contingency)
                <div class="mt-2">
                    <label class="form-label small">Inspection Period (days)</label>
                    <input type="number" class="form-control form-control-sm w-auto" wire:model.lazy="inspection_contingency_days" min="1" max="60" placeholder="e.g. 10">
                </div>
                @endif
            </div>
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="appr_cont" wire:model="appraisal_contingency">
                    <label class="form-check-label fw-semibold" for="appr_cont">Appraisal Contingency</label>
                </div>
            </div>
            <hr class="my-3">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Closing Date</label>
                    <input type="date" class="form-control" wire:model.lazy="closing_date">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Possession Date</label>
                    <input type="date" class="form-control" wire:model.lazy="possession_date">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Offer Expires</label>
                    <input type="date" class="form-control" wire:model.lazy="listing_expiration">
                </div>
            </div>
            <div class="d-flex justify-content-between mt-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="offerWizardGoTo('offer-tab-2')">
                    <i class="fa fa-arrow-left me-1"></i> Back
                </button>
                <button type="button" class="btn btn-sm text-white" style="background:#049399;" onclick="offerWizardGoTo('offer-tab-4')">
                    Next: Review &amp; Terms <i class="fa fa-arrow-right ms-1"></i>
                </button>
            </div>
        </div>
        </div>

        {{-- PANEL 4: REVIEW & CUSTOM TERMS --}}
        <div wire:ignore.self class="tab-pane fade" id="offer-panel-4" role="tabpanel">
        <div class="card border-0 shadow-sm p-4">
            <h6 class="fw-bold mb-3" style="color:#049399;"><i class="fa fa-square-check me-2"></i>Review &amp; Custom Terms</h6>

            <div class="border rounded p-3 mb-4 bg-light">
                <div class="row g-2 small">
                    <div class="col-6 col-md-3"><span class="text-muted">Offer Type</span><br><strong>{{ $offer_type ? ucfirst($offer_type) : '—' }}</strong></div>
                    <div class="col-6 col-md-3"><span class="text-muted">Address</span><br><strong>{{ $property_address ?: '—' }}</strong></div>
                    @if($offer_price)<div class="col-6 col-md-3"><span class="text-muted">Offer Price</span><br><strong>${{ number_format($offer_price) }}</strong></div>@endif
                    @if($monthly_rent)<div class="col-6 col-md-3"><span class="text-muted">Monthly Rent</span><br><strong>${{ number_format($monthly_rent) }}</strong></div>@endif
                    <div class="col-6 col-md-3"><span class="text-muted">Closing Date</span><br><strong>{{ $closing_date ?: '—' }}</strong></div>
                    <div class="col-6 col-md-3"><span class="text-muted">Expires</span><br><strong>{{ $listing_expiration ?: '—' }}</strong></div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Custom Terms / Special Conditions</label>
                <textarea class="form-control" wire:model.lazy="custom_terms" rows="5" placeholder="Any special conditions, addendums, or custom terms…"></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Internal Notes <span class="text-muted small">(not shown publicly)</span></label>
                <textarea class="form-control" wire:model.lazy="notes" rows="3" placeholder="Private notes for your reference…"></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Listing Status</label>
                <select class="form-select w-auto" wire:model="listing_status">
                    <option value="Active">Active</option>
                    <option value="Pending">Pending</option>
                    <option value="Withdrawn">Withdrawn</option>
                </select>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="offerWizardGoTo('offer-tab-3')">
                    <i class="fa fa-arrow-left me-1"></i> Back
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="saveDraft" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveDraft"><i class="fa fa-save me-1"></i>Save Draft</span>
                        <span wire:loading wire:target="saveDraft">Saving…</span>
                    </button>
                    <button type="button" class="btn btn-sm text-white fw-semibold" style="background:#049399;" wire:click="submitListing" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submitListing"><i class="fa fa-circle-check me-1"></i>Publish Listing</span>
                        <span wire:loading wire:target="submitListing">Publishing…</span>
                    </button>
                </div>
            </div>
        </div>
        </div>

    </div>{{-- end .tab-content --}}

    {{-- ── JavaScript: tab persistence across Livewire re-renders + flash ── --}}
    <script>
    (function () {
        // Track the currently active offer wizard tab across Livewire re-renders.
        window._offerWizardActiveTab = 'offer-tab-1';

        // Navigate to a tab by button ID.
        window.offerWizardGoTo = function (btnId) {
            var btn = document.getElementById(btnId);
            if (btn) {
                window._offerWizardActiveTab = btnId;
                new bootstrap.Tab(btn).show();
            }
        };

        // Show flash toast.
        window.addEventListener('offer-flash', function (e) {
            var toast  = document.getElementById('offer-flash-toast');
            var inner  = document.getElementById('offer-flash-inner');
            if (!toast || !inner) return;
            inner.className = 'alert alert-' + (e.detail.type || 'success') + ' mb-0 shadow';
            inner.innerHTML = (e.detail.type === 'success' ? '✓ ' : '⚠ ') + e.detail.message;
            toast.style.display = 'block';
            clearTimeout(window._offerFlashTimer);
            window._offerFlashTimer = setTimeout(function () { toast.style.display = 'none'; }, 4000);
        });

        // Restore active tab after every Livewire component update.
        document.addEventListener('livewire:load', function () {
            // Save tab before update
            Livewire.hook('message.processing', function () {
                var active = document.getElementById(window._offerWizardActiveTab);
                if (active) window._offerWizardActiveTab = active.id;
            });

            // Restore tab after update (slight delay for Bootstrap to re-init)
            Livewire.hook('message.processed', function () {
                setTimeout(function () {
                    var btn = document.getElementById(window._offerWizardActiveTab);
                    if (btn) {
                        new bootstrap.Tab(btn).show();
                    }
                }, 20);
            });
        });

        // Track tab changes made by clicking the nav directly.
        document.addEventListener('shown.bs.tab', function (e) {
            if (e.target && e.target.id && e.target.id.startsWith('offer-tab-')) {
                window._offerWizardActiveTab = e.target.id;
            }
        });
    })();
    </script>
</div>
