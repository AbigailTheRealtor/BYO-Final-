@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.css">
    <style>
        /* Your custom styles here */
        .wizard-steps-progress {
            height: 5px;
            width: 100%;
            background-color: #CCC;
            position: absolute;
            top: 0;
            left: 0;
        }

        .steps-progress-percent {
            height: 100%;
            width: 0%;
            background-color: #11b7cf;
        }

        .wizard-step {
            display: none;
        }

        .wizard-step.active {
            display: block;
        }

        .tab-content {
            padding: 20px;
            border: 1px solid #ddd;
            border-top: none;
        }

        .nav-tabs .nav-link {
            border: 1px solid #ddd;
            border-bottom: none;
            margin-right: 5px;
            padding: 10px 20px;
            background-color: #f8f9fa;
        }

        .nav-tabs .nav-link.active {
            background-color: #fff;
            border-bottom: 1px solid #fff;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            font-weight: bold;
        }

        .form-control {
            min-height: 50px;
        }

        .input-cover .form-control {
            padding-left: 50px;
            /* Ensure the input text doesn't overlap the icon */
            width: 100%;
            /* Ensure the input field takes full width */
        }

        #bio,
        #why_hire_you,
        #what_sets_you_apart,
        #marketing_plan {
            padding: 10px !important;
        }

        .nav-tabs .nav-link.active {
            background-color: #049399 !important;
            color: white !important;
            border-color: #049399 !important;
        }

        .input-cover {
            position: relative;
            display: flex;
            align-items: center;
            /* Center the icon vertically */
        }

        .input-cover .input-icon {
            position: absolute;
            left: 10px;
            font-size: 25px;
            color: #11b7cf;
            pointer-events: none;
            top: 50%;
            transform: translateY(-50%);
            /* Center the icon vertically */
        }

        .has-icon {
            padding-left: 40px;
        }

        .error {
            display: block;
            color: red;
            font-size: 14px;
            margin-top: 5px;
            /* Add space between input-cover and error message */
            width: 100%;
            /* Ensure the error message takes full width */
        }

        .d-none {
            display: none;
        }

        .hidden {
            display: none;
        }

        .badge {
            font-size: 0.9rem;
            padding: 0.5em 0.75em;
            display: inline-flex;
            align-items: center;
        }

        .badge a {
            opacity: 0.7;
        }

        .badge a:hover {
            opacity: 1;
            text-decoration: none;
        }

        .autocomplete-dropdown {
            position: absolute;
            z-index: 1000;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 0 0 4px 4px;
            background: white;
        }

        .autocomplete-dropdown .list-group-item {
            cursor: pointer;
            border: none;
            border-bottom: 1px solid #eee;
        }

        .autocomplete-dropdown .list-group-item:hover {
            background-color: #f8f9fa;
        }

        #save-button.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
            /* This prevents clicks */
        }

        .fee-option-card {
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .fee-option-card:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .input-group-text {
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #049399;
            box-shadow: 0 0 0 0.2rem rgba(4, 147, 153, 0.25);
        }
         .input-group-text-seller {
        display: flex;
        align-items: center;
        padding: 0.7rem 0.75rem;
        font-size: 1rem;
        font-weight: 400;
        line-height: 1.5;
        color: #212529;
        text-align: center;
        white-space: nowrap;
        background-color: #e9ecef;
        border: 1px solid #ced4da;
        border-radius: .375rem;
    }

    .input-group-text-seller+input.form-control {
        padding-left: 8px;
    }
    </style>
@endpush


<div class="container pt-5 pb-5">
    <div class="card">
        <div class="row">
            <div class="col-12 p-4">
                @if (session()->has('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session()->has('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}
                    </div>
                @endif
                <form wire:submit.prevent="submit" novalidate id="landlord-bid-form">
                    <div id="submit-error-banner" class="alert alert-danger d-none" role="alert" style="position: sticky; top: 0; z-index: 1050;">
                        <strong>Please complete the required fields before submitting:</strong>
                        <ul id="submit-error-list" class="mb-0 mt-2"></ul>
                    </div>
                    <!-- Tab Navigation -->

                        @php
                        $user_type="tenant";
                            $tabs = [
                                'Agent Overview',
                                'Broker Compensation and Agency Agreement',
                                'Additional Details',
                            ];

                            // Dynamic services tab based on $user_type
                            $tabs[] = match (strtolower($user_type)) {
                                'tenant' => 'Offered Services',
                                'landlord' => 'Services the Landlord Requests from Their Agent',
                                'seller' => 'Services the Seller Requests from Their Agent',
                                'buyer' => 'Services the Buyer Requests from Their Agent',
                                default => 'Services',
                            };

                            $tabs[] = 'Agent Presentation And Marketing Materials';
                            $tabs[] = 'Agent Credentials & Contact Info';
                        @endphp


                        <ul class="nav nav-tabs" id="myTab" role="tablist">
                            @foreach ($tabs as $index => $tab)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === $index ? 'active' : '' }}"
                                        wire:click="setActiveTab({{ $index }})"
                                        id="{{ str_replace(' ', '-', strtolower($tab)) }}" data-bs-toggle="tab"
                                        data-bs-target="#{{ str_replace(' ', '-', strtolower($tab)) }}" type="button"
                                        role="tab" aria-controls="{{ str_replace(' ', '-', strtolower($tab)) }}"
                                        aria-selected="{{ $activeTab === $index ? 'true' : 'false' }}">
                                        {{ $tab }}
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    <!-- Tab Content -->

                    <div class="tab-content mt-3">
                        <!-- Tab 1: Agent Overview & Qualifications -->
                        <div class="tab-pane fade {{ $activeTab === 0 ? 'show active' : '' }}">

                            @include('livewire.landlord-agent-auction-bid-tabs.commission-based.agent-overview')

                        </div>
                            <!-- Tab 2: Broker Compensation & Agency Agreement Terms -->
                            <div class="tab-pane fade {{ $activeTab === 1 ? 'show active' : '' }}">
                                {{-- @if ($property_type == 'Residential Property')
                                    @include('livewire.landlord-agent-auction-bid-tabs.commission-based.broker-compensation-residential')
                                @elseif ($property_type == 'Commercial Property')
                                    @include('livewire.landlord-agent-auction-bid-tabs.commission-based.broker-compensation-commerical')
                                @endif --}}
                            @include('livewire.landlord-agent-auction-bid-tabs.commission-based.broker-compensation')



                            </div>
                            <!-- Tab 3: Additional Details -->
                            <div class="tab-pane fade {{ $activeTab === 2 ? 'show active' : '' }}">

                                @include('livewire.landlord-agent-auction-bid-tabs.commission-based.additional-details')

                            </div>

                            <!-- Tab 4: Services to Tenant -->
                            <div class="tab-pane fade {{ $activeTab === 3 ? 'show active' : '' }}" id="services">
                                @include('livewire.landlord-agent-auction-bid-tabs.commission-based.services')
                            </div>

                            <!-- Tab 5: Promotional Materials -->
                            <div class="tab-pane fade {{ $activeTab === 4 ? 'show active' : '' }}">
                                @include('livewire.landlord-agent-auction-bid-tabs.commission-based.agent-presentation')

                            </div>
                            <!-- Tab 6: Agent Information -->
                            <div class="tab-pane fade {{ $activeTab === 5 ? 'show active' : '' }}">

                                @include('livewire.landlord-agent-auction-bid-tabs.commission-based.agent-info')

                            </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="d-flex justify-content-between form-group mt-4">
                        <div>
                            <button type="button" class="btn btn-secondary wizard-step-back" wire:click="goToPreviousStep">
                                Previous
                            </button>
                        </div>
                        <div>
                            <button type="button" class="btn btn-primary wizard-step-next" wire:click="goToNextStep">
                                Next
                            </button>

                            <button type="submit" class="btn btn-success wizard-step-finish" id="save-button" wire:loading.attr="disabled">
                                Submit
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
document.addEventListener('DOMContentLoaded', function() {

    // ============================
    // TOOLTIP MANAGEMENT
    // ============================
    let tooltipInstances = [];

    function initializeTooltips() {
        destroyAllTooltips();
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipInstances = tooltipTriggerList.map(el => {
            const tooltip = new bootstrap.Tooltip(el, { trigger: 'hover focus', html: true });

            el.addEventListener('click', e => {
                e.stopPropagation();
                tooltip.show();
                setTimeout(() => tooltip.hide(), 3000);
            });

            return tooltip;
        });

        document.addEventListener('click', e => {
            if (!e.target.closest('[data-bs-toggle="tooltip"]')) hideAllTooltips();
        });
    }

    function destroyAllTooltips() {
        tooltipInstances.forEach(t => t?.dispose?.());
        tooltipInstances = [];
    }

    function hideAllTooltips() {
        tooltipInstances.forEach(t => t?.hide?.());
    }

    initializeTooltips();
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', () => setTimeout(initializeTooltips, 50));
    });

    document.addEventListener('livewire:load', initializeTooltips);
    Livewire.hook('message.processed', () => setTimeout(initializeTooltips, 50));
    document.addEventListener('turbolinks:load', initializeTooltips);

    // ============================
    // SELECT2 INITIALIZATION
    // ============================
    $('#services-dropdown').select2({ placeholder: "Select services", allowClear: true });

    // ============================
    // INPUT ICONS
    // ============================
    function addIconsToInputs() {
        document.querySelectorAll('.has-icon').forEach(input => {
            const iconClass = input.dataset.icon;
            if (iconClass && !input.previousElementSibling?.classList.contains('input-icon')) {
                const icon = document.createElement('i');
                icon.className = `input-icon ${iconClass}`;
                input.parentNode.insertBefore(icon, input);
            }
        });
    }

    addIconsToInputs();

    // ============================
    // FILE UPLOAD VALIDATION
    // ============================
    const photoInput = document.getElementById("business-card");
    const photoError = document.getElementById("business-card-error");
    const videoInput = document.getElementById("video-input");
    const videoError = document.getElementById("video-error");
    const videoLoader = document.getElementById("video-loader");
    const materialInput = document.getElementById("promo-materials");
    const materialError = document.getElementById("promo-materials-error");

    function showLoaderForMinimumTime() {
        if (videoLoader) {
            videoLoader.style.visibility = "visible";
            setTimeout(() => videoLoader.style.visibility = "hidden", 30000);
        }
    }

    function validateFile(file, type, maxSizeMB = 10) {
        if (!file) return false;
        if (!file.type.startsWith(type)) return false;
        if (file.size > maxSizeMB * 1024 * 1024) return false;
        return true;
    }

    function handlePhotoUpload(e) {
        const file = e.target.files[0];
        if (!validateFile(file, 'image')) {
            photoError.textContent = "Upload valid image (max 10MB)";
            photoError.style.display = "block";
            photoInput.value = "";
            return;
        }
        photoError.textContent = "";
        Livewire.emit("upload:start");
        showLoaderForMinimumTime();
    }

    function handleVideoUpload(e) {
        const file = e.target.files[0];
        if (!validateFile(file, 'video')) {
            videoError.textContent = "Upload valid video (max 10MB)";
            videoError.style.display = "block";
            videoInput.value = "";
            return;
        }
        videoError.textContent = "";
        Livewire.emit("upload:start");
        showLoaderForMinimumTime();
    }

    function handleMaterialUpload(e) {
        const files = e.target.files;
        if (!files || files.length === 0) return;

        const allowedTypes = [
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv'
        ];

        for (let f of files) {
            if (!allowedTypes.includes(f.type) || f.size > 10*1024*1024) {
                materialError.textContent = `Invalid file "${f.name}"`;
                materialError.style.display = "block";
                materialInput.value = "";
                return;
            }
        }

        materialError.textContent = "";
        Livewire.emit("upload:start");
        showLoaderForMinimumTime();
    }

    photoInput?.addEventListener("change", handlePhotoUpload);
    videoInput?.addEventListener("change", handleVideoUpload);
    materialInput?.addEventListener("change", handleMaterialUpload);

    Livewire.on("upload:start", showLoaderForMinimumTime);
    Livewire.on("upload:finish", () => setTimeout(() => { if(videoLoader) videoLoader.style.visibility="hidden"; }, 30000));

    // ============================
    // NUMBER INPUT FORMATTING
    // ============================
    function getErrorEl(input) {
        const byId = input.dataset.errorId && document.getElementById(input.dataset.errorId);
        if (byId) return byId;
        const group = input.closest('.form-group');
        return group ? group.querySelector('.error') : null;
    }

    function formatNumberWithCommas(value) {
        const parts = value.replace(/,/g,'').split('.');
        let intPart = parts[0], decPart = parts[1] || '';
        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return decPart ? `${intPart}.${decPart}` : intPart;
    }

    function validateInput(input) {
        const errorEl = getErrorEl(input);
        const oldVal = input.value, caret = input.selectionStart;
        const commasBefore = (oldVal.slice(0, caret).match(/,/g)||[]).length;
        let v = oldVal.replace(/[^0-9.,]/g,'');
        const firstDot = v.indexOf('.');
        if(firstDot!==-1) v = v.slice(0, firstDot+1) + v.slice(firstDot+1).replace(/\./g,'');
        if(v.startsWith('.')) v=v.slice(1);
        input.value = formatNumberWithCommas(v);
        const commasAfter = (input.value.slice(0, caret).match(/,/g)||[]).length;
        const delta = commasAfter - commasBefore;
        input.setSelectionRange(caret+delta, caret+delta);
        errorEl && (errorEl.innerText = /[^0-9.,]/.test(oldVal)?"Invalid number format":"");
    }

    function handlePaste(event) {
        event.preventDefault();
        const input = event.target;
        let text = (event.clipboardData||window.clipboardData).getData('text');
        text = text.replace(/[^0-9.,]/g,'');
        const firstDot = text.indexOf('.');
        if(firstDot!==-1) text=text.slice(0,firstDot+1)+text.slice(firstDot+1).replace(/\./g,'');
        if(text.startsWith('.')) text=text.slice(1);
        input.value = formatNumberWithCommas(text);
        validateInput(input);
    }

    document.querySelectorAll('input[data-number]').forEach(input=>{
        input.addEventListener('input',()=>validateInput(input));
        input.addEventListener('blur',()=>validateInput(input));
        input.addEventListener('paste', handlePaste);
    });

    // ============================
    // WIZARD NAVIGATION & VALIDATION
    // ============================
    function checkFieldValidity(field){
        if(!field.required) return true;
        const v=field.value;
        if(!v || v==='') return false;
        if(field.type==='number' && field.hasAttribute('min') && parseInt(v)<parseInt(field.getAttribute('min'))) return false;
        if(field.type==='url' && !v.startsWith('http')) return false;
        if(field.type==='email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return false;
        return true;
    }

    function validateFieldWithErrors(field){
        const errorEl = getErrorEl(field);
        const isValid = checkFieldValidity(field);
        if(!isValid){ field.classList.add('is-invalid'); if(errorEl) errorEl.innerText='This field is required'; }
        else{ field.classList.remove('is-invalid'); if(errorEl) errorEl.innerText=''; }
        return isValid;
    }

    function validateServicesTab(tab){
        if(!tab || tab.id!=='services') return true;
        // Services validation removed - selecting services is now optional
        tab.querySelectorAll('.service-error').forEach(e=>e.remove());
        return true;
    }

    function validateCurrentTabWithErrors(){
        const tab=document.querySelector('.tab-pane.active');
        let valid=true;
        let firstInvalid=null;
        tab.querySelectorAll('[required]').forEach(f=>{
            if(!validateFieldWithErrors(f)) { valid=false; if(!firstInvalid) firstInvalid=f; }
        });
        if(tab.id==='services') valid=valid && validateServicesTab(tab);
        if(firstInvalid) firstInvalid.scrollIntoView({behavior:'smooth', block:'center'});
        return valid;
    }

    // Navigation is handled by Livewire wire:click="goToNextStep" / goToPreviousStep

    Livewire.hook('message.processed', ()=>{
        setTimeout(()=>{ addIconsToInputs(); },100);
    });

    addIconsToInputs();
});
</script>
    <script>
        (function() {
            var _landlordBidCorrectionMode = false;
            var _landlordBidMissingItems = [];

            function landlordBidGetInvalidItems() {
                var allTabPanes = Array.from(document.querySelectorAll('#landlord-bid-form .tab-content .tab-pane'));
                var items = [];
                document.querySelectorAll('#landlord-bid-form [required]').forEach(function(field) {
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

            function landlordBidMarkAllInvalid(items) {
                document.querySelectorAll('#landlord-bid-form .is-invalid').forEach(function(f) { f.classList.remove('is-invalid'); });
                items.forEach(function(item) {
                    if (item.field) item.field.classList.add('is-invalid');
                });
            }

            function landlordBidNavigateToItem(item) {
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

            function landlordBidBuildErrorBanner(items, errorList) {
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
                        landlordBidNavigateToItem(item);
                    });
                    li.appendChild(a);
                    errorList.appendChild(li);
                });
            }

            function landlordBidAdvanceCorrection() {
                if (!_landlordBidCorrectionMode) return;
                var freshMissing = landlordBidGetInvalidItems();
                var errorList = document.getElementById('submit-error-list');
                var banner = document.getElementById('submit-error-banner');
                landlordBidMarkAllInvalid(freshMissing);
                if (freshMissing.length === 0) {
                    _landlordBidCorrectionMode = false;
                    _landlordBidMissingItems = [];
                    if (banner) banner.classList.add('d-none');
                    return;
                }
                landlordBidBuildErrorBanner(freshMissing, errorList);
                if (banner) banner.classList.remove('d-none');
                _landlordBidMissingItems = freshMissing;
            }

            if (typeof Livewire !== 'undefined') {
                Livewire.hook('message.processed', function() {
                    setTimeout(landlordBidAdvanceCorrection, 350);
                });
            }

            document.addEventListener('submit', function(e) {
                var form = document.getElementById('landlord-bid-form');
                if (!form || e.target !== form) return;
                var banner = document.getElementById('submit-error-banner');
                var errorList = document.getElementById('submit-error-list');
                if (banner) banner.classList.add('d-none');
                if (errorList) errorList.innerHTML = '';
                var invalidItems = landlordBidGetInvalidItems();
                if (invalidItems.length > 0) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    landlordBidMarkAllInvalid(invalidItems);
                    landlordBidBuildErrorBanner(invalidItems, errorList);
                    if (banner) banner.classList.remove('d-none');
                    _landlordBidCorrectionMode = true;
                    _landlordBidMissingItems = invalidItems;
                    landlordBidNavigateToItem(invalidItems[0]);
                    return false;
                }
                if (banner) banner.classList.add('d-none');
            }, true);
        })();
    </script>
@endpush
