@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/choices.min.css') }}">
    <style>
        .wizard-steps-progress { height: 5px; width: 100%; background-color: #CCC; position: absolute; top: 0; left: 0; }
        .steps-progress-percent { height: 100%; width: 0%; background-color: #11b7cf; }
        .wizard-step { display: none; }
        .wizard-step.active { display: block; }
        .tab-content { padding: 20px; border: 1px solid #ddd; border-top: none; }
        .nav-tabs .nav-link { border: 1px solid #ddd; border-bottom: none; margin-right: 5px; padding: 10px 20px; background-color: #f8f9fa; }
        .nav-tabs .nav-link.active { background-color: #fff; border-bottom: 1px solid #fff; }
        .form-group { margin-bottom: 15px; }
        .form-group label { font-weight: bold; }
        .form-control { min-height: 50px; }
        .input-cover .form-control { padding-left: 50px; width: 100%; }
        #bio, #why_hire_you, #what_sets_you_apart, #marketing_plan { padding: 10px !important; }
        .nav-tabs .nav-link.active { background-color: #049399 !important; color: white !important; border-color: #049399 !important; }
        .input-cover { position: relative; display: flex; align-items: center; }
        .input-cover .input-icon { position: absolute; left: 10px; font-size: 25px; color: #11b7cf; pointer-events: none; top: 50%; transform: translateY(-50%); }
        .has-icon { padding-left: 40px; }
        .error { display: block; color: red; font-size: 14px; margin-top: 5px; width: 100%; }
        .d-none { display: none; }
        .hidden { display: none; }
        .fee-option-card { transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .fee-option-card:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .form-control:focus { border-color: #049399; box-shadow: 0 0 0 0.2rem rgba(4,147,153,0.25); }
        .service-section .section-header { border-radius: 4px; }
        #save-button.disable { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
    </style>
@endpush

<div class="container pt-5 pb-5">
    <div class="card">
        <div class="row">
            <div class="col-12 p-4">
                @if (session()->has('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if (session()->has('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <h2 class="mb-4">Hire a Seller's Agent — Submit Your Bid</h2>

                <form wire:submit.prevent="submit" novalidate id="seller-bid-form">
                    <div id="submit-error-banner" class="alert alert-danger d-none" role="alert" style="position: sticky; top: 0; z-index: 1050;">
                        <strong>Please complete the required fields before submitting:</strong>
                        <ul id="submit-error-list" class="mb-0 mt-2"></ul>
                    </div>
                    <!-- Tab Navigation -->
                    @php
                        $tabs = ['Agent Overview', 'Broker Compensation and Agency Agreement'];
                        if ($this->hasReferralTab()) {
                            $tabs[] = 'Referral Fee & Cooperation Terms';
                        }
                        $tabs[] = 'Additional Details';
                        $tabs[] = 'Services the Seller Requests from Their Agent';
                        $tabs[] = 'Agent Presentation & Marketing Materials';
                        $tabs[] = 'Agent Credentials & Contact Info';
                        $tabs[] = 'Working Style & Compatibility';
                    @endphp

                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                        @foreach ($tabs as $index => $tab)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link {{ $activeTab === $index ? 'active' : '' }}"
                                    wire:click="setActiveTab({{ $index }})"
                                    id="{{ str_replace(' ', '-', strtolower($tab)) }}-tab"
                                    data-bs-toggle="tab"
                                    data-bs-target="#{{ str_replace(' ', '-', strtolower($tab)) }}"
                                    type="button" role="tab"
                                    aria-controls="{{ str_replace(' ', '-', strtolower($tab)) }}"
                                    aria-selected="{{ $activeTab === $index ? 'true' : 'false' }}">
                                    {{ $tab }}
                                </button>
                            </li>
                        @endforeach
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content mt-3">
                        <!-- Tab 0: Agent Overview -->
                        <div class="tab-pane fade {{ $activeTab === 0 ? 'show active' : '' }}">
                            @include('livewire.seller-agent-auction-bid-tabs.commission-based.agent-overview')
                        </div>

                        <!-- Tab 1: Broker Compensation -->
                        <div class="tab-pane fade {{ $activeTab === 1 ? 'show active' : '' }}">
                            @include('livewire.seller-agent-auction-bid-tabs.commission-based.broker-compensation')
                        </div>

                        @php $tabOffset = $this->hasReferralTab() ? 1 : 0; @endphp

                        @if ($this->hasReferralTab())
                        <!-- Tab 2: Referral Fee & Cooperation Terms -->
                        <div class="tab-pane fade {{ $activeTab === 2 ? 'show active' : '' }}">
                            <h3>Referral Fee &amp; Cooperation Terms</h3>
                            <div class="form-group mb-4 mt-3">
                                <label class="fw-semibold" for="referral_fee_percent_seller_bid">Referral Fee (%) <span class="text-muted fw-normal">(Agent-to-Agent)</span></label>
                                <input type="number"
                                       class="form-control mt-1"
                                       id="referral_fee_percent_seller_bid"
                                       wire:model.live.debounce.300ms="referral_fee_percent"
                                       min="0" max="100" step="0.01"
                                       placeholder="e.g. 25">
                                <div class="form-text text-muted mt-1" style="font-size:.85rem;">
                                    This is the referral fee offered to or requested from the hired Agent or their brokerage. This term is negotiated between agents and is not paid by the client.
                                </div>
                                @error('referral_fee_percent') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        @endif

                        <!-- Tab 2/3: Additional Details -->
                        <div class="tab-pane fade {{ $activeTab === (2 + $tabOffset) ? 'show active' : '' }}">
                            @include('livewire.seller-agent-auction-bid-tabs.commission-based.additional-details')
                        </div>

                        <!-- Tab 3/4: Services -->
                        <div class="tab-pane fade {{ $activeTab === (3 + $tabOffset) ? 'show active' : '' }}" id="services">
                            @include('livewire.seller-agent-auction-bid-tabs.commission-based.services')
                        </div>

                        <!-- Tab 4/5: Promotional Materials -->
                        <div class="tab-pane fade {{ $activeTab === (4 + $tabOffset) ? 'show active' : '' }}">
                            @include('livewire.seller-agent-auction-bid-tabs.commission-based.agent-presentation')
                        </div>

                        <!-- Tab 5/6: Agent Credentials -->
                        <div class="tab-pane fade {{ $activeTab === (5 + $tabOffset) ? 'show active' : '' }}">
                            @include('livewire.seller-agent-auction-bid-tabs.commission-based.agent-info')
                        </div>
                        <!-- Tab 6/7: Working Style & Compatibility -->
                        <div class="tab-pane fade {{ $activeTab === (6 + $tabOffset) ? 'show active' : '' }}">
                            @include('partials.agent-bid-compatibility')
                        </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="d-flex justify-content-between form-group mt-4">
                        <div>
                            <button type="button" wire:click="goToPreviousStep"
                                    class="btn btn-secondary wizard-step-back">
                                Previous
                            </button>
                        </div>
                        <div>
                            <button type="button" wire:click="goToNextStep"
                                    class="btn btn-primary wizard-step-next">
                                Next
                            </button>
                            <button type="submit"
                                    class="btn btn-success wizard-step-finish"
                                    id="save-button"
                                    wire:loading.attr="disabled">
                                <span wire:loading.remove>Submit Bid</span>
                                <span wire:loading>Saving...</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function initializeTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function(el) {
            new bootstrap.Tooltip(el, { trigger: 'hover focus', html: true });
        });
    }

    document.addEventListener('livewire:load', function() {
        initializeTooltips();
    });

    document.addEventListener('livewire:update', function() {
        initializeTooltips();
    });

    function validateInput(input) {
        input.value = input.value.replace(/[^0-9.,]/g, '');
    }

    function reformatNumber(input) {
        let val = input.value.replace(/,/g, '');
        let num = parseFloat(val);
        if (!isNaN(num)) {
            input.value = num.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
        }
    }

    function handlePaste(event) {
        let pastedData = (event.clipboardData || window.clipboardData).getData('text');
        pastedData = pastedData.replace(/[^0-9.,]/g, '');
        event.preventDefault();
        document.execCommand('insertText', false, pastedData);
    }

    function formatPhoneNumber(input) {
        let value = input.value.replace(/\D/g, '');
        if (value.length > 10) value = value.substring(0, 10);
        if (value.length >= 6) {
            input.value = '(' + value.substring(0,3) + ') ' + value.substring(3,6) + '-' + value.substring(6);
        } else if (value.length >= 3) {
            input.value = '(' + value.substring(0,3) + ') ' + value.substring(3);
        } else if (value.length > 0) {
            input.value = '(' + value;
        }
    }

    // Photo enhancement toggle — immediate feedback matching listing creation behavior.
    // Strategy: always keep panel display in sync with the checkbox's actual checked state.
    // This is called on initial load, on every click, and after every Livewire re-render
    // so the panel is always correct regardless of Livewire's DOM diffing timing.
    function syncEnhancementPanels() {
        document.querySelectorAll('[data-enhancement-trigger]').forEach(function(cb) {
            var container = cb.closest('.form-check');
            if (!container) return;
            var next = container.nextElementSibling;
            while (next) {
                if (next.classList && next.classList.contains('enhancement-options')) {
                    next.style.display = cb.checked ? 'block' : 'none';
                    break;
                }
                next = next.nextElementSibling;
            }
        });
    }

    // Immediate feedback on click (before Livewire round-trip)
    document.addEventListener('click', function(e) {
        var trigger = e.target.closest('[data-enhancement-trigger]');
        if (trigger) {
            // Use requestAnimationFrame so checkbox.checked has settled
            requestAnimationFrame(function() { syncEnhancementPanels(); });
        }
    });

    // Re-sync after every Livewire re-render to override any stale server-rendered display value
    document.addEventListener('livewire:update', function() {
        syncEnhancementPanels();
    });

    document.addEventListener('livewire:load', function() {
        syncEnhancementPanels();
    });

    // Add icon rendering for input-cover elements
    document.addEventListener('livewire:load', function () {
        renderIcons();
    });
    document.addEventListener('livewire:update', function () {
        renderIcons();
    });

    function renderIcons() {
        document.querySelectorAll('.input-cover .has-icon[data-icon]').forEach(function(el) {
            let parent = el.parentElement;
            if (!parent.querySelector('.input-icon')) {
                let icon = document.createElement('i');
                icon.className = el.dataset.icon + ' input-icon';
                parent.insertBefore(icon, el);
            }
        });
    }
</script>
    <script>
        (function() {
            var _sellerBidCorrectionMode = false;
            var _sellerBidMissingItems = [];

            function sellerBidGetInvalidItems() {
                var allTabPanes = Array.from(document.querySelectorAll('#seller-bid-form .tab-content .tab-pane'));
                var items = [];
                document.querySelectorAll('#seller-bid-form [required]').forEach(function(field) {
                    var tabPane = field.closest('.tab-pane');
                    if (!tabPane) return;
                    var el = field.parentElement;
                    while (el && el !== tabPane) {
                        if (el.classList && el.classList.contains('d-none')) return;
                        if (el.style && el.style.display === 'none') return;
                        el = el.parentElement;
                    }
                    var isEmpty = false;
                    if (field.type === 'checkbox' || field.type === 'radio') {
                        isEmpty = !field.checked;
                    } else if (field.tagName === 'SELECT') {
                        isEmpty = field.value === '' || field.value === null;
                    } else {
                        isEmpty = !field.value || field.value.trim() === '';
                    }
                    if (isEmpty) {
                        var label = field.closest('.form-group') && field.closest('.form-group').querySelector('label');
                        var fieldName = label ? label.textContent.replace(/[*:]/g, '').trim() : (field.getAttribute('placeholder') || field.name || field.id || 'Required field');
                        var key = field.getAttribute('wire:model') || field.getAttribute('wire:model.defer') || field.getAttribute('wire:model.lazy') || field.id || field.name || '';
                        var tabIndex = allTabPanes.indexOf(tabPane);
                        items.push({ field: field, tab: tabPane, tabIndex: tabIndex, fieldName: fieldName, key: key });
                    }
                });
                var seen = new Set();
                return items.filter(function(item) {
                    var k = item.key || item.fieldName;
                    if (seen.has(k)) return false;
                    seen.add(k);
                    return true;
                });
            }

            function sellerBidMarkAllInvalid(items) {
                document.querySelectorAll('#seller-bid-form .is-invalid').forEach(function(f) { f.classList.remove('is-invalid'); });
                items.forEach(function(item) {
                    if (item.field) item.field.classList.add('is-invalid');
                });
            }

            function sellerBidNavigateToItem(item) {
                if (item && item.tabIndex >= 0) {
                    try { @this.call('setActiveTab', item.tabIndex); } catch(e) {}
                    var navLinks = document.querySelectorAll('#myTab .nav-link');
                    if (navLinks[item.tabIndex]) {
                        try { new bootstrap.Tab(navLinks[item.tabIndex]).show(); } catch(e2) {}
                    }
                }
                setTimeout(function() {
                    if (item && item.field && item.field !== document.body) {
                        item.field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        if (typeof item.field.focus === 'function' && item.field.tagName !== 'DIV') {
                            item.field.focus();
                        }
                    }
                    var banner = document.getElementById('submit-error-banner');
                    if (banner) {
                        banner.classList.remove('d-none');
                        banner.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }, 350);
            }

            function sellerBidBuildErrorBanner(items, errorList) {
                if (!errorList) return;
                errorList.innerHTML = '';
                items.forEach(function(item) {
                    var li = document.createElement('li');
                    var a = document.createElement('a');
                    a.href = '#';
                    a.textContent = item.fieldName;
                    a.style.color = 'inherit';
                    a.addEventListener('click', function(ev) {
                        ev.preventDefault();
                        sellerBidNavigateToItem(item);
                    });
                    li.appendChild(a);
                    errorList.appendChild(li);
                });
            }

            function sellerBidAdvanceCorrection() {
                if (!_sellerBidCorrectionMode) return;
                var freshMissing = sellerBidGetInvalidItems();
                var errorList = document.getElementById('submit-error-list');
                var banner = document.getElementById('submit-error-banner');
                sellerBidMarkAllInvalid(freshMissing);
                if (freshMissing.length === 0) {
                    _sellerBidCorrectionMode = false;
                    _sellerBidMissingItems = [];
                    if (banner) banner.classList.add('d-none');
                    return;
                }
                sellerBidBuildErrorBanner(freshMissing, errorList);
                if (banner) banner.classList.remove('d-none');
                _sellerBidMissingItems = freshMissing;
            }

            if (typeof Livewire !== 'undefined') {
                Livewire.hook('message.processed', function() {
                    setTimeout(sellerBidAdvanceCorrection, 350);
                });
            }

            document.addEventListener('submit', function(e) {
                var form = document.getElementById('seller-bid-form');
                if (!form || e.target !== form) return;
                var banner = document.getElementById('submit-error-banner');
                var errorList = document.getElementById('submit-error-list');
                if (banner) banner.classList.add('d-none');
                if (errorList) errorList.innerHTML = '';
                var invalidItems = sellerBidGetInvalidItems();
                if (invalidItems.length > 0) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    sellerBidMarkAllInvalid(invalidItems);
                    sellerBidBuildErrorBanner(invalidItems, errorList);
                    if (banner) banner.classList.remove('d-none');
                    _sellerBidCorrectionMode = true;
                    _sellerBidMissingItems = invalidItems;
                    sellerBidNavigateToItem(invalidItems[0]);
                    return false;
                }
                if (banner) banner.classList.add('d-none');
            }, true);
        })();
    </script>
@endpush
