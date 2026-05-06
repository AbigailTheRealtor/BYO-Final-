@push('styles')
<style>
    .wizard-steps-progress{height:5px;width:100%;background:#CCC;position:absolute;top:0;left:0}
    .steps-progress-percent{height:100%;width:0%;background:#11b7cf}
    .wizard-step{display:none}.wizard-step.active{display:block}
    .tab-content{padding:20px;border:1px solid #ddd;border-top:none}
    .nav-tabs .nav-link{border:1px solid #ddd;border-bottom:none;margin-right:5px;padding:10px 20px;background:#f8f9fa}
    .nav-tabs .nav-link.active{background:#049399!important;color:#fff!important;border-color:#049399!important}
    .form-group{margin-bottom:15px}.form-group label{font-weight:bold}.form-control{min-height:50px}
    .input-cover{position:relative;display:flex;align-items:center}
    .input-cover .input-icon{position:absolute;left:10px;font-size:25px;color:#11b7cf;pointer-events:none;top:50%;transform:translateY(-50%)}
    .has-icon{padding-left:40px}
    .error{display:block;color:red;font-size:14px;margin-top:5px;width:100%}
    .d-none{display:none}.hidden{display:none}
    .service-section{margin-bottom:1.5rem}
    .section-header{font-size:1rem;border-radius:4px}
    .form-check-label{cursor:pointer}
    /* Submit disabled look — matches Tenant/Buyer/Landlord */
    #save-button.disabled{opacity:.5;cursor:not-allowed;pointer-events:none}
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

                <h4 class="mb-4" style="color:#049399;">Counter Agent Bid</h4>

                <form wire:submit.prevent="submit">
                    @php
                        $tabs = ['Services', 'Broker Compensation & Agency Agreement', 'Additional Terms'];
                        if ($isListingCreatedByAgent) {
                            $tabs[] = 'Referral Fee & Cooperation Terms';
                        }
                        $tabs[] = 'Counter Terms';
                        $counterTermsTabIndex = $isListingCreatedByAgent ? 4 : 3;
                    @endphp

                    <ul class="nav nav-tabs" id="sellerCounterTab" role="tablist">
                        @foreach ($tabs as $index => $tab)
                            <li class="nav-item" role="presentation">
                                <button
                                    class="nav-link {{ $activeTab === $index ? 'active' : '' }}"
                                    wire:click="setActiveTab({{ $index }})"
                                    type="button"
                                    role="tab">
                                    {{ $tab }}
                                </button>
                            </li>
                        @endforeach
                    </ul>

                    <div class="tab-content mt-3">
                        <div class="tab-pane fade {{ $activeTab === 0 ? 'show active' : '' }}">
                            @include('livewire.seller-agent-auction-counter-tabs.services', ['isCounterMode' => true])
                        </div>
                        <div class="tab-pane fade {{ $activeTab === 1 ? 'show active' : '' }}">
                            @include('livewire.seller-agent-auction-counter-tabs.broker-compensation', ['isCounterMode' => true])
                        </div>
                        <div class="tab-pane fade {{ $activeTab === 2 ? 'show active' : '' }}">
                            @include('livewire.seller-agent-auction-counter-tabs.additional-details')
                        </div>
                        @if ($isListingCreatedByAgent)
                        <div class="tab-pane fade {{ $activeTab === 3 ? 'show active' : '' }}">
                            @include('livewire.seller-agent-auction-counter-tabs.referral-fee')
                        </div>
                        @endif
                        <div class="tab-pane fade {{ $activeTab === $counterTermsTabIndex ? 'show active' : '' }}">
                            @include('livewire.seller-agent-auction-counter-tabs.counter-terms')
                        </div>
                    </div>

                    {{-- Navigation footer — mirrors Tenant / Buyer / Landlord wizard pattern exactly --}}
                    <div class="d-flex justify-content-between form-group mt-4">
                        <div>
                            <button type="button" class="btn btn-secondary wizard-step-back">Previous</button>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary wizard-step-next"
                                style="background:#049399;border-color:#049399;">Next</button>

                            <button type="submit"
                                    id="save-button"
                                    class="btn btn-success wizard-step-finish disabled"
                                    wire:loading.attr="disabled">
                                {{ $counterTermId ? 'Update Counter' : 'Submit Counter' }}
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
// =============== Icon Injection ===============
window.addIconsToInputs = function (root) {
    root = root || document;
    root.querySelectorAll('.has-icon').forEach(function (input) {
        const iconClass = input.getAttribute('data-icon');
        const parent = input.parentNode;
        if (!iconClass || !parent || !parent.classList || !parent.classList.contains('input-cover')) return;
        if (parent.querySelector(':scope > .input-icon')) return;
        const icon = document.createElement('i');
        icon.className = 'input-icon ' + iconClass;
        parent.insertBefore(icon, input);
    });
};

// =============== Validation ===============
function checkFieldValidity(field) {
    if (!field.required) return true;
    const value = field.value;
    if (!value) return false;
    if (field.type === 'number' && field.hasAttribute('min') && parseInt(value) < parseInt(field.getAttribute('min'))) return false;
    if (field.type === 'url' && value && !value.startsWith('http')) return false;
    if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return false;
    return true;
}
function validateFieldWithErrors(field) {
    if (!field.required) return true;
    const isValid = checkFieldValidity(field);
    let errorSpan = document.getElementById(field.id + '_error');
    if (!errorSpan) {
        errorSpan = document.createElement('span');
        errorSpan.className = 'error mt-2 text-danger';
        errorSpan.id = field.id + '_error';
        const container = field.closest('.form-group') || field.parentNode;
        (container || field.parentNode).appendChild(errorSpan);
    }
    if (!isValid) {
        field.classList.add('is-invalid');
        errorSpan.textContent = 'This field is required';
        return false;
    } else {
        field.classList.remove('is-invalid');
        errorSpan.textContent = '';
        return true;
    }
}
function validateCurrentTabWithErrors() {
    const currentTab = document.querySelector('.tab-pane.active');
    if (!currentTab) return true;
    let isValid = true, firstInvalid = null;
    currentTab.querySelectorAll('[required]').forEach(function (field) {
        if (!validateFieldWithErrors(field)) { isValid = false; if (!firstInvalid) firstInvalid = field; }
    });
    if (firstInvalid) firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return isValid;
}
function checkAllTabsValidity() {
    return Array.from(document.querySelectorAll('[required]')).every(checkFieldValidity);
}
function updateSaveButton() {
    const saveButton = document.getElementById('save-button');
    if (!saveButton) return;
    if (checkAllTabsValidity()) {
        saveButton.classList.remove('disabled'); saveButton.disabled = false;
    } else {
        saveButton.classList.add('disabled'); saveButton.disabled = true;
    }
}

// =============== Tab Button Visibility ===============
function updateTabButtons() {
    const links = [...document.querySelectorAll('.nav-link')];
    const current = document.querySelector('.nav-link.active');
    if (!current) return;
    const isLastTab = links.indexOf(current) === links.length - 1;
    const saveBtn = document.getElementById('save-button');
    const nextBtn = document.querySelector('.wizard-step-next');
    if (saveBtn) saveBtn.style.display = isLastTab ? '' : 'none';
    if (nextBtn) nextBtn.style.display = isLastTab ? 'none' : '';
    if (isLastTab) updateSaveButton();
}

// =============== Init ===============
document.addEventListener('DOMContentLoaded', function () {
    window.addIconsToInputs();
    updateSaveButton();
    updateTabButtons();
});
document.addEventListener('livewire:load', function () {
    window.addIconsToInputs();
    updateSaveButton();
    updateTabButtons();
});
if (typeof Livewire !== 'undefined') {
    Livewire.hook('message.processed', function () {
        setTimeout(function () {
            window.addIconsToInputs();
            updateSaveButton();
            updateTabButtons();
        }, 10);
    });
}

// =============== Event Delegation (survives Livewire re-renders) ===============
document.addEventListener('click', function (e) {
    // Next
    var nextBtn = e.target.closest('.wizard-step-next');
    if (nextBtn) {
        e.preventDefault();
        if (validateCurrentTabWithErrors()) {
            var links = Array.from(document.querySelectorAll('.nav-link'));
            var current = document.querySelector('.nav-link.active');
            var idx = links.indexOf(current);
            var next = links[idx + 1];
            if (next) { next.click(); setTimeout(updateTabButtons, 50); }
            var tc = document.querySelector('.tab-content');
            if (tc) tc.scrollIntoView({ behavior: 'smooth' });
        }
        return;
    }
    // Back
    var backBtn = e.target.closest('.wizard-step-back');
    if (backBtn) {
        e.preventDefault();
        var links = Array.from(document.querySelectorAll('.nav-link'));
        var current = document.querySelector('.nav-link.active');
        var idx = links.indexOf(current);
        var prev = links[idx - 1];
        if (prev) { prev.click(); setTimeout(updateTabButtons, 50); }
        var tc = document.querySelector('.tab-content');
        if (tc) tc.scrollIntoView({ behavior: 'smooth' });
        return;
    }
});

// Re-check save button state on any input change
document.addEventListener('input', function (e) {
    if (e.target.matches('input, textarea, select')) { checkFieldValidity(e.target); updateSaveButton(); }
});
document.addEventListener('blur', function (e) {
    if (e.target.matches('input, textarea, select')) { checkFieldValidity(e.target); updateSaveButton(); }
}, true);
</script>
@endpush
