@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.css">
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

                <form wire:submit.prevent="submit">
                    <!-- Tab Navigation -->
                    @php
                        $tabs = [
                            'Agent Overview',
                            'Broker Compensation and Agency Agreement',
                            'Additional Details',
                            'Services the Seller Requests from Their Agent',
                            'Agent Presentation & Marketing Materials',
                            'Agent Credentials & Contact Info',
                        ];
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

                        <!-- Tab 2: Additional Details -->
                        <div class="tab-pane fade {{ $activeTab === 2 ? 'show active' : '' }}">
                            @include('livewire.seller-agent-auction-bid-tabs.commission-based.additional-details')
                        </div>

                        <!-- Tab 3: Services -->
                        <div class="tab-pane fade {{ $activeTab === 3 ? 'show active' : '' }}" id="services">
                            @include('livewire.seller-agent-auction-bid-tabs.commission-based.services')
                        </div>

                        <!-- Tab 4: Promotional Materials -->
                        <div class="tab-pane fade {{ $activeTab === 4 ? 'show active' : '' }}">
                            @include('livewire.seller-agent-auction-bid-tabs.commission-based.agent-presentation')
                        </div>

                        <!-- Tab 5: Agent Credentials -->
                        <div class="tab-pane fade {{ $activeTab === 5 ? 'show active' : '' }}">
                            @include('livewire.seller-agent-auction-bid-tabs.commission-based.agent-info')
                        </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="d-flex justify-content-between form-group mt-4">
                        <div>
                            @if ($activeTab > 0)
                            <button type="button" wire:click="goToPreviousStep"
                                    class="btn btn-secondary wizard-step-back">
                                Previous
                            </button>
                            @endif
                        </div>
                        <div>
                            @if ($activeTab < 5)
                            <button type="button" wire:click="goToNextStep"
                                    class="btn btn-primary wizard-step-next">
                                Next
                            </button>
                            @else
                            <button type="submit"
                                    class="btn btn-success wizard-step-finish"
                                    id="save-button">
                                Submit Bid
                            </button>
                            @endif
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
@endpush
