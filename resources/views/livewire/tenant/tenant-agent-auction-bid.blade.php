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

        /* Removed: #save-button.disabled - now using Livewire wire:loading for button state */
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
    </style>
@endpush

@php

@endphp
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
                
                {{-- Display validation errors --}}
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <strong>Please fix the following errors:</strong>
                        <ul class="mb-0 mt-2">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <form wire:submit.prevent="submit">
                    <!-- Tab Navigation -->
                    @if ($service_type === 'full_service')

                        @php
                            $user_type = 'tenant';
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

                            $tabs[] = 'Agent Presentation & Marketing Materials';
                            $tabs[] = 'Agent Credentials & Contact Info';
                        @endphp

                        {{-- <ul class="nav nav-tabs" id="myTab" role="tablist">
                            @foreach (['Agent Overview', 'Broker Compensation and Agency Agreement', 'Additional Details', 'Tenant Services', 'Agent Presentation & Marketing Materials', 'Agent Information'] as $index => $tab)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === $index ? 'active' : '' }}"
                                        wire:click="setActiveTab({{ $index }})"
                                        id="{{ str_replace(' ', '-', strtolower($tab)) }}-tab" data-bs-toggle="tab"
                                        data-bs-target="#{{ str_replace(' ', '-', strtolower($tab)) }}" type="button"
                                        role="tab" aria-controls="{{ str_replace(' ', '-', strtolower($tab)) }}"
                                        aria-selected="{{ $activeTab === $index ? 'true' : 'false' }}">
                                        {{ $tab }}
                                    </button>
                                </li>
                            @endforeach
                        </ul> --}}

                        <ul class="nav nav-tabs" id="myTab" role="tablist">
                            @foreach ($tabs as $index => $tab)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === $index ? 'active' : '' }}"
                                        wire:click="setActiveTab({{ $index }})"
                                        id="{{ str_replace(' ', '-', strtolower($tab)) }}-tab" data-bs-toggle="tab"
                                        data-bs-target="#{{ str_replace(' ', '-', strtolower($tab)) }}" type="button"
                                        role="tab" aria-controls="{{ str_replace(' ', '-', strtolower($tab)) }}"
                                        aria-selected="{{ $activeTab === $index ? 'true' : 'false' }}">
                                        {{ $tab }}
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @elseif($service_type === 'limited_service')
                        <ul class="nav nav-tabs" id="myTab" role="tablist">
                            @foreach (['Agent Overview', 'Service Selection and Pricing', 'Additional Details', 'Presentation and Promotional Materials', 'Agent Credentials & Contact Info'] as $index => $tab)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === $index ? 'active' : '' }}"
                                        wire:click="setActiveTab({{ $index }})"
                                        id="{{ str_replace(' ', '-', strtolower($tab)) }}-tab" data-bs-toggle="tab"
                                        data-bs-target="#{{ str_replace(' ', '-', strtolower($tab)) }}" type="button"
                                        role="tab" aria-controls="{{ str_replace(' ', '-', strtolower($tab)) }}"
                                        aria-selected="{{ $activeTab === $index ? 'true' : 'false' }}">
                                        {{ $tab }}
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <!-- Tab Content -->
                    <div class="tab-content mt-3">
                        <!-- Tab 1: Agent Overview & Qualifications -->
                        <div class="tab-pane fade {{ $activeTab === 0 ? 'show active' : '' }}">

                            @include('livewire.tenant-agent-auction-bid-tabs.commission-based.agent-overview')

                        </div>
                        @if ($service_type === 'full_service')
                            <!-- Tab 2: Broker Compensation & Agency Agreement Terms -->
                            <div class="tab-pane fade {{ $activeTab === 1 ? 'show active' : '' }}">
                                @include('livewire.tenant-agent-auction-bid-tabs.commission-based.broker-compensation')

                            </div>
                            <!-- Tab 3: Additional Details -->
                            <div class="tab-pane fade {{ $activeTab === 2 ? 'show active' : '' }}">

                                @include('livewire.tenant-agent-auction-bid-tabs.commission-based.additional-details')

                            </div>

                            <!-- Tab 4: Services to Tenant -->
                            <div class="tab-pane fade {{ $activeTab === 3 ? 'show active' : '' }}" id="services">
                                @include('livewire.tenant-agent-auction-bid-tabs.commission-based.services')
                            </div>

                            <!-- Tab 5: Promotional Materials -->
                            <div class="tab-pane fade {{ $activeTab === 4 ? 'show active' : '' }}" id="promotional-materials">
                                @include('livewire.tenant-agent-auction-bid-tabs.commission-based.agent-presentation')

                            </div>
                            <!-- Tab 6: Agent Information -->
                            <div class="tab-pane fade {{ $activeTab === 5 ? 'show active' : '' }}">

                                @include('livewire.tenant-agent-auction-bid-tabs.commission-based.agent-info')

                            </div>
                        @elseif($service_type === 'limited_service')
                            <div class="tab-pane fade {{ $activeTab === 1 ? 'show active' : '' }}">

                                @include('livewire.tenant-agent-auction-bid-tabs.flat-fee.service')

                            </div>
                            <!-- Tab 3: Additional Details -->
                            <div class="tab-pane fade {{ $activeTab === 2 ? 'show active' : '' }}">

                                @include('livewire.tenant-agent-auction-bid-tabs.commission-based.additional-details')

                            </div>
                            <div class="tab-pane fade {{ $activeTab === 3 ? 'show active' : '' }}" id="promotional-materials">
                                @include('livewire.tenant-agent-auction-bid-tabs.commission-based.agent-presentation')

                            </div>
                            <!-- Tab 4: Services to Tenant -->
                            <div class="tab-pane fade {{ $activeTab === 4 ? 'show active' : '' }}" id="services">
                                @include('livewire.tenant-agent-auction-bid-tabs.commission-based.agent-info')
                            </div>
                        @endif
                    </div>

                    @if($isBiddingPeriodListing)
                    <div class="alert alert-info mt-4 mb-2" style="background: #e3f2fd; border: 1px solid #90caf9;">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Public Bid Notice:</strong> Once submitted, your Broker Compensation & Agency Agreement Terms, Offered Services, and Match Score may be visible to other participating agents in anonymized form during the bidding period.
                    </div>
                    @endif
                    
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
                                <span wire:loading.remove>Submit</span>
                                <span wire:loading>Saving...</span>
                            </button>
                        </div>
                    </div>
                    
                    {{-- Error display for debugging --}}
                    @if ($errors->any())
                        <div class="alert alert-danger mt-2" id="validation-errors">
                            <strong>Please fix the following errors:</strong>
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        // Global array to store tooltip instances
        let tooltipInstances = [];

        function initializeTooltips() {
            // Destroy existing tooltips first to prevent duplicates
            destroyAllTooltips();

            // Initialize new tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));

            tooltipInstances = tooltipTriggerList.map(function(tooltipTriggerEl) {
                // Create new tooltip instance
                const tooltip = new bootstrap.Tooltip(tooltipTriggerEl, {
                    trigger: 'hover focus',
                    html: true
                });

                // Add click handler for mobile/alternative interaction
                tooltipTriggerEl.addEventListener('click', function(e) {
                    e.stopPropagation();
                    tooltip.show();

                    // Auto-hide after 3 seconds
                    setTimeout(() => {
                        tooltip.hide();
                    }, 3000);
                });

                return tooltip;
            });

            // Add global click handler to hide tooltips
            document.addEventListener('click', function(e) {
                if (!e.target.closest('[data-bs-toggle="tooltip"]')) {
                    hideAllTooltips();
                }
            });
        }

        function destroyAllTooltips() {
            tooltipInstances.forEach(tooltip => {
                if (tooltip && typeof tooltip.dispose === 'function') {
                    tooltip.dispose();
                }
            });
            tooltipInstances = [];
        }

        function hideAllTooltips() {
            tooltipInstances.forEach(tooltip => {
                if (tooltip && typeof tooltip.hide === 'function') {
                    tooltip.hide();
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Initial tooltip setup
            initializeTooltips();

            // Reinitialize when tabs are shown
            document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
                tab.addEventListener('shown.bs.tab', function() {
                    // Small delay to ensure tab content is visible
                    setTimeout(initializeTooltips, 50);
                });
            });
        });

        // Livewire hooks
        document.addEventListener('livewire:load', function() {
            initializeTooltips();
        });

        Livewire.hook('message.processed', (message, component) => {
            // Wait for Livewire to finish DOM updates
            setTimeout(initializeTooltips, 10);
        });

        // Handle Turbolinks if you're using it
        document.addEventListener('turbolinks:load', function() {
            initializeTooltips();
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Select2 for services dropdown
            $('#services-dropdown').select2({
                placeholder: "Select services",
                allowClear: true,
            });

            // Add icons to input fields
            function addIconsToInputs() {
                document.querySelectorAll('.has-icon').forEach(input => {
                    const iconClass = input.getAttribute('data-icon');
                    if (iconClass && !input.previousElementSibling?.classList.contains('input-icon')) {
                        const icon = document.createElement('i');
                        icon.className = `input-icon ${iconClass}`;
                        input.parentNode.insertBefore(icon, input);
                    }
                });
            }

            const photoInput = document.getElementById("business-card");
            const photoError = document.getElementById("business-card-error");
            const videoInput = document.getElementById("video-input");
            const videoError = document.getElementById("video-error");
            const videoLoader = document.getElementById("video-loader");
            const photoPreview = document.getElementById("photo-preview");

            const materialInput = document.getElementById("promo-materials");
            const materialError = document.getElementById("promo-materials-error");

            // Error flags
            let photoErrorFlag = false;
            let videoErrorFlag = false;
            let materialErrorFlag = false;

            // Function to validate photo upload
            function validatePhoto(file) {
                if (!file) return true; // No file selected

                if (!file.type.startsWith("image/")) {
                    photoError.textContent = "Please upload a valid image file.";
                    photoError.style.display = "block";
                    photoErrorFlag = true;
                    photoInput.value = "";
                    return false;
                }

                if (file.size > 10 * 1024 * 1024) {
                    photoError.textContent = "Photo size must be less than 10MB.";
                    photoError.style.display = "block";
                    photoErrorFlag = true;
                    photoInput.value = "";
                    return false;
                }

                photoError.textContent = "";
                photoError.style.display = "none";
                photoErrorFlag = false;
                return true;
            }

            // Function to validate video upload
            function validateVideo(file) {
                if (!file) return false; // No file selected

                if (!file.type.startsWith("video/")) {
                    videoError.textContent = "Please upload a valid video file.";
                    videoError.style.display = "block";
                    videoErrorFlag = true;
                    videoInput.value = "";
                    return false;
                }

                if (file.size > 10 * 1024 * 1024) {
                    videoError.textContent = "Video size must be less than 10MB.";
                    videoError.style.display = "block";
                    videoErrorFlag = true;
                    videoInput.value = "";
                    return false;
                }

                videoError.textContent = "";
                videoError.style.display = "none";
                videoErrorFlag = false;
                return true;
            }

            // Function to validate materials upload
            function validateMaterial(files) {
                if (!files || files.length === 0) return true; // No files selected

                const allowedTypes = [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'text/csv'
                ];

                const maxSize = 10 * 1024 * 1024; // 10MB

                for (let i = 0; i < files.length; i++) {
                    const file = files[i];

                    if (!allowedTypes.includes(file.type)) {
                        materialError.textContent = "Please upload valid files (PDF, DOC, PPT, XLS, CSV).";
                        materialError.style.display = "block";
                        materialErrorFlag = true;
                        materialInput.value = "";
                        return false;
                    }

                    if (file.size > maxSize) {
                        materialError.textContent = `File "${file.name}" exceeds 10MB limit.`;
                        materialError.style.display = "block";
                        materialErrorFlag = true;
                        materialInput.value = "";
                        return false;
                    }
                }

                materialError.textContent = "";
                materialError.style.display = "none";
                materialErrorFlag = false;
                return true;
            }

            // Function to show loader for at least 30 seconds
            function showLoaderForMinimumTime() {
                videoLoader.style.visibility = "visible";
                setTimeout(() => {
                    videoLoader.style.visibility = "hidden";
                }, 30000);
            }

            // Function to handle video upload
            function handleVideoUpload(event) {
                const file = event.target.files[0];
                if (!validateVideo(file)) return;
                Livewire.emit("upload:start");
                showLoaderForMinimumTime();
            }

            // Function to handle photo upload
            function handlePhotoUpload(event) {
                const file = event.target.files[0];
                if (!validatePhoto(file)) return;
                Livewire.emit("upload:start");
                showLoaderForMinimumTime();
            }

            // Function to handle materials upload
            function handleMaterialUpload(event) {
                const files = event.target.files;
                if (!validateMaterial(files)) return;
                Livewire.emit("upload:start");
                showLoaderForMinimumTime();
            }

            // Attach event listeners
            if (photoInput) {
                photoInput.addEventListener("change", handlePhotoUpload);
            }

            if (videoInput) {
                videoInput.addEventListener("change", handleVideoUpload);
            }

            if (materialInput) {
                materialInput.addEventListener("change", handleMaterialUpload);
            }

            // Livewire event listeners
            Livewire.on("upload:start", () => {
                showLoaderForMinimumTime();
            });

            Livewire.on("upload:finish", () => {
                setTimeout(() => {
                    videoLoader.style.visibility = "hidden";
                }, 30000);
            });



            // Helper function to check if element is visible (not hidden by d-none, display:none, etc.)
            function isElementVisible(element) {
                if (!element) return false;
                if (element.disabled) return false;
                if (element.type === 'hidden') return false;
                
                // Check if element or any parent has d-none, hidden class, or display:none
                let el = element;
                while (el && el !== document.body) {
                    if (el.classList && (el.classList.contains('d-none') || el.classList.contains('hidden'))) {
                        return false;
                    }
                    const style = window.getComputedStyle(el);
                    if (style.display === 'none' || style.visibility === 'hidden') {
                        return false;
                    }
                    el = el.parentElement;
                }
                return true;
            }

            // Function to validate a single field (without showing errors)
            function checkFieldValidity(field) {
                if (!field.required) return true;
                // Skip hidden or disabled fields
                if (!isElementVisible(field)) return true;

                const value = field.value;

                // Check if field is empty
                if (!value || value === '') {
                    return false;
                }
                // Special validation for number fields
                else if (field.type === 'number' && field.hasAttribute('min')) {
                    const min = parseInt(field.getAttribute('min'));
                    if (parseInt(value) < min) {
                        return false;
                    }
                }
                // Special validation for URLs
                else if (field.type === 'url' && value && !value.startsWith('http')) {
                    return false;
                }
                // Special validation for email
                else if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                    return false;
                }

                return true;
            }

            // Function to validate and show errors for a single field
            function validateFieldWithErrors(field) {
                if (!field.required) return true;
                // Skip hidden or disabled fields
                if (!isElementVisible(field)) {
                    field.classList.remove('is-invalid');
                    return true;
                }

                const isValid = checkFieldValidity(field);
                const errorSpan = document.getElementById(`${field.id}_error`) ||
                    document.createElement('span');

                errorSpan.className = 'error mt-2 text-danger';
                errorSpan.id = `${field.id}_error`;

                if (!field.parentNode.parentNode.querySelector('.error')) {
                    field.parentNode.parentNode.appendChild(errorSpan);
                }

                if (!isValid) {
                    field.classList.add('is-invalid');
                    if (!field.value || field.value === '') {
                        errorSpan.textContent = 'This field is required';
                    } else if (field.type === 'number' && field.hasAttribute('min')) {
                        errorSpan.textContent = `Value must be at least ${field.getAttribute('min')}`;
                    } else if (field.type === 'url' && !field.value.startsWith('http')) {
                        errorSpan.textContent = 'Please enter a valid URL starting with http:// or https://';
                    } else if (field.type === 'email') {
                        errorSpan.textContent = 'Please enter a valid email address';
                    }
                    return false;
                } else {
                    field.classList.remove('is-invalid');
                    errorSpan.textContent = '';
                    return true;
                }
            }
            // Add this function to validate services tab
            // Add this function to validate services tab
            function validateServicesTab(currentTab) {
                if (!currentTab || currentTab.id !== 'services') return true;

                let isValid = true;

                // Check at least one service is selected (excluding "Other" checkbox)
                const hasServices = currentTab.querySelectorAll(
                    'input[type="checkbox"][wire\\:model="services"]:checked:not(#other-services-checkbox)'
                ).length > 0;

                // Check "Other Services" if enabled
                const otherCheckbox = currentTab.querySelector('#other-services-checkbox');
                const otherTextarea = currentTab.querySelector('#other-services-input');
                const hasOtherDescription = otherTextarea && otherTextarea.value.trim() !== '';

                // Clear previous errors
                const existingErrors = currentTab.querySelectorAll('.service-error');
                if (existingErrors) {
                    existingErrors.forEach(el => el.remove());
                }
                if (otherTextarea) otherTextarea.classList.remove('is-invalid');

                // Services validation removed - selecting services is now optional
                // No validation error will be shown if no services are selected

                // if (otherCheckbox && otherCheckbox.checked && (!otherTextarea || !hasOtherDescription)) {
                //     isValid = false;
                //     const errorDiv = document.createElement('div');
                //     errorDiv.className = 'service-error error mt-2';
                //     errorDiv.textContent = 'Please describe the additional services you require.';

                //     if (otherTextarea) {
                //         otherTextarea.classList.add('is-invalid');
                //         const container = otherTextarea.closest('.mb-3') || otherTextarea.parentNode;
                //         if (container) {
                //             container.appendChild(errorDiv);
                //         }
                //     }
                // }

                return isValid;
            }

            // Function to validate all fields in current tab and show errors
            function validateCurrentTabWithErrors() {
                const currentTab = document.querySelector('.tab-pane.active');
                let isValid = true;
                let firstInvalidField = null;

                currentTab.querySelectorAll('[required]').forEach(field => {
                    if (!validateFieldWithErrors(field)) {
                        isValid = false;
                        if (!firstInvalidField) {
                            firstInvalidField = field;
                        }
                    }
                });
                // Special validation for services tab
                if (currentTab.id === 'services') {
                    console.log('services');
                    isValid = isValid && validateServicesTab(currentTab);

                }

                //    // ADD THIS: Validate services tab if it's the current tab
                //    if (currentTabContent.id === 'services') {
                //     console.log('services');
                //     isValid = isValid && validateServicesTab(currentTabContent);
                // }


                // Scroll to first invalid field if any
                if (firstInvalidField) {
                    firstInvalidField.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }

                return isValid;
            }

            // Function to validate all required fields in all tabs (without showing errors)
            function checkAllTabsValidity() {
                let allValid = true;
                const requiredFields = document.querySelectorAll('[required]');

                requiredFields.forEach(field => {
                    if (!checkFieldValidity(field)) {
                        allValid = false;
                    }
                });

                return allValid;
            }

            // Update save button state - REMOVED: Was blocking submit when hidden tabs failed validation
            // Livewire server-side validation now handles this
            function updateSaveButton() {
                // No-op: Allow form submission to reach Livewire for proper server-side validation
                return;
            }
            // Add this function to validate services tab


            // Next button click handler - FIXED TAB NAVIGATION
            document.querySelector('.wizard-step-next')?.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('NEXT CLICKED');
                
                const currentTab = document.querySelector('.tab-pane.active');
                console.log('Current tab ID:', currentTab ? currentTab.id : 'no-id');
                
                // Skip validation for promotional-materials tab (file inputs shouldn't block navigation)
                const skipValidation = currentTab && currentTab.id === 'promotional-materials';
                
                // Validate current tab with error messages (unless skipped)
                if (skipValidation || validateCurrentTabWithErrors()) {
                    console.log('Validation passed or skipped, advancing...');
                    const currentTabIndex = Array.from(document.querySelectorAll('.nav-link')).indexOf(
                        document.querySelector('.nav-link.active')
                    );

                    if (currentTabIndex < 5) { // Assuming 6 tabs (0-5)
                        // Find the next tab button and click it
                        console.log("Advancing from tab index", currentTabIndex, "to", currentTabIndex + 1);
                        const nextTabButton = document.querySelectorAll('.nav-link')[currentTabIndex + 1];
                        if (nextTabButton) {
                            nextTabButton.click();
                        }
                        // Scroll to top of next tab
                        document.querySelector('.tab-content').scrollIntoView({
                            behavior: 'smooth'
                        });
                    }
                } else {
                    console.log('Validation FAILED - Next blocked');
                }
            });

            // Back button click handler - FIXED TAB NAVIGATION
            document.querySelector('.wizard-step-back')?.addEventListener('click', function(e) {
                e.preventDefault();

                const currentTabIndex = Array.from(document.querySelectorAll('.nav-link')).indexOf(
                    document.querySelector('.nav-link.active')
                );

                if (currentTabIndex > 0) {
                    // Find the previous tab button and click it
                    const prevTabButton = document.querySelectorAll('.nav-link')[currentTabIndex - 1];
                    if (prevTabButton) {
                        prevTabButton.click();
                    }
                    // Scroll to top of previous tab
                    document.querySelector('.tab-content').scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });

            // // Toggle "Other" service input visibility
            // function toggleOtherService() {
            //     const servicesDropdown = $('#services-dropdown');
            //     const otherServiceWrapper = document.getElementById('other_service_input_wrapper');
            //     const otherServiceInput = document.getElementById('other_services');

            //     if (servicesDropdown.val() && servicesDropdown.val().includes(
            //             'Other – Specify additional services offered.')) {
            //         otherServiceWrapper.classList.remove('d-none');
            //         otherServiceInput.required = true;
            //     } else {
            //         otherServiceWrapper.classList.add('d-none');
            //         otherServiceInput.required = false;
            //         document.getElementById('other_service_error').textContent = '';
            //     }
            // }

            // // Handle services dropdown changes
            // $('#services-dropdown').on('change', function(e) {
            //     let selectedValues = $(this).val();
            //     @this.set('services', selectedValues);
            //     toggleOtherService();
            //     updateSaveButton();
            // });

            // Validate fields on input/blur (without showing errors)
            document.querySelectorAll('input, textarea, select').forEach(field => {
                field.addEventListener('blur', () => {
                    // Only validate without showing errors during regular interaction
                    checkFieldValidity(field);
                    updateSaveButton();
                });

                field.addEventListener('input', () => {
                    // Only validate without showing errors during regular interaction
                    checkFieldValidity(field);
                    updateSaveButton();
                });
            });

            // Initialize icons and validate on load
            addIconsToInputs();
            updateSaveButton();

            // Livewire hook to reinitialize after updates
            Livewire.hook('message.processed', () => {
                setTimeout(() => {
                    addIconsToInputs();
                    // toggleOtherService();
                    updateSaveButton();

                    // Reinitialize Select2 after Livewire update
                    //     $('#services-dropdown').select2({
                    //         placeholder: "Select services",
                    //         allowClear: true,
                    //     });
                }, 100);
            });
        });
    </script>
    {{--
    <script>
        document.addEventListener('DOMContentLoaded', initPhoneFormatter);
        if (typeof Livewire !== 'undefined') {
            document.addEventListener('livewire:load', initPhoneFormatter);
            Livewire.hook('message.processed', () => setTimeout(initPhoneFormatter, 10));
        }

        function initPhoneFormatter() {
            const input = document.getElementById('phone_number');
            if (!input) return;

            // Clean up previous listeners
            input.removeEventListener('input', handlePhoneInput);
            input.removeEventListener('keydown', preventNonNumericInput);
            input.removeEventListener('blur', handlePhoneBlur);
            input.removeEventListener('paste', handlePhonePaste);

            // Format initial value if exists
            if (input.value) {
                formatPhoneNumber(input);
            }

            // Add new listeners
            input.addEventListener('input', handlePhoneInput);
            input.addEventListener('keydown', preventNonNumericInput);
            input.addEventListener('blur', handlePhoneBlur);
            input.addEventListener('paste', handlePhonePaste);
        }

        function preventNonNumericInput(e) {
            // Allow: backspace, delete, tab, escape, enter
            if ([8, 9, 13, 27, 46].includes(e.keyCode) ||
                // Allow: Ctrl+A, Ctrl+C, Ctrl+X
                (e.ctrlKey && [65, 67, 88].includes(e.keyCode)) ||
                // Allow: home, end, left, right
                (e.keyCode >= 35 && e.keyCode <= 39)) {
                return;
            }

            // Prevent if not a number
            if ((e.keyCode < 48 || e.keyCode > 57) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        }

        function handlePhoneInput(e) {
            const input = e.target;
            const cursorPos = input.selectionStart;
            const prevValue = input.value;

            // Format the number
            formatPhoneNumber(input);

            // Maintain cursor position
            if (prevValue.length === cursorPos) {
                // If cursor was at end, keep it at end
                input.setSelectionRange(input.value.length, input.value.length);
            } else {
                // Otherwise try to maintain relative position
                const diff = input.value.length - prevValue.length;
                input.setSelectionRange(cursorPos + diff, cursorPos + diff);
            }

            // Update Livewire model with raw numbers
            updateLivewireModel(input);
        }

        function handlePhoneBlur(e) {
            const input = e.target;
            formatPhoneNumber(input);
            updateLivewireModel(input);
        }

        function handlePhonePaste(e) {
            e.preventDefault();
            const pasteData = e.clipboardData.getData('text/plain');
            const numbers = pasteData.replace(/\D/g, '');
            document.execCommand('insertText', false, numbers);
        }

        function formatPhoneNumber(input) {
            // Get only digits and limit to 10 characters
            let numbers = input.value.replace(/\D/g, '').substring(0, 10);

            // Format based on length
            let formatted = numbers;
            if (numbers.length > 6) {
                formatted = numbers.replace(/(\d{3})(\d{3})(\d{1,4})/, '$1-$2-$3');
            } else if (numbers.length > 3) {
                formatted = numbers.replace(/(\d{3})(\d{1,3})/, '$1-$2');
            }

            input.value = formatted;
            return formatted;
        }

        function updateLivewireModel(input) {
            if (typeof Livewire === 'undefined') return;

            const rawValue = input.value.replace(/\D/g, '');
            const componentEl = input.closest('[wire\\:id]');
            if (!componentEl) return;

            const component = Livewire.find(componentEl.getAttribute('wire:id'));
            const model = input.getAttribute('wire:model');
            if (component && model) {
                component.set(model, rawValue);
            }
        }
    </script> --}}

    <script>
        function getErrorEl(input) {
            // Prefer explicit linkage via data-error-id; otherwise, fall back to nearest .form-group .error
            const byId = input.dataset.errorId && document.getElementById(input.dataset.errorId);
            if (byId) return byId;
            const group = input.closest('.form-group');
            return group ? group.querySelector('.error') : null;
        }

        // Allow digits, commas, and a single decimal point; format with commas; keep caret stable
        function validateInput(input) {
            const errorEl = getErrorEl(input);
            const oldVal = input.value;
            let caret = input.selectionStart;

            // Count commas before caret for later adjustment
            const commasBefore = (oldVal.slice(0, caret).match(/,/g) || []).length;

            // Keep only digits, commas, periods
            let v = oldVal.replace(/[^0-9.,]/g, '');

            // Only one decimal point
            const firstDot = v.indexOf('.');
            if (firstDot !== -1) {
                // remove any additional dots
                v = v.slice(0, firstDot + 1) + v.slice(firstDot + 1).replace(/\./g, '');
            }

            // No leading dot
            if (v.startsWith('.')) v = v.slice(1);

            // Format with commas
            const formatted = formatNumberWithCommas(v);
            input.value = formatted;

            // Adjust caret by net change in commas before caret
            const commasAfter = (formatted.slice(0, caret).match(/,/g) || []).length;
            const delta = commasAfter - commasBefore;
            const newPos = Math.max(0, Math.min(formatted.length, caret + delta));
            input.setSelectionRange(newPos, newPos);

            // Error message if original had invalid chars
            if (/[^0-9.,]/.test(oldVal)) {
                errorEl && (errorEl.innerText =
                    "Please enter a valid number. Use a period for decimals (e.g., 50,000.50). Letters and special characters are not permitted."
                );
            } else {
                errorEl && (errorEl.innerText = "");
            }
        }

        function formatNumberWithCommas(value) {
            const clean = value.replace(/,/g, '');
            const parts = clean.split('.');
            let intPart = parts[0] || '';
            const decPart = parts[1] !== undefined ? '.' + parts[1] : '';

            // insert commas in integer part
            intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            return intPart + decPart;
        }

        function handlePaste(event) {
            event.preventDefault();
            const input = event.target;
            const errorEl = getErrorEl(input);

            let text = (event.clipboardData || window.clipboardData).getData('text');

            // Strip invalids
            text = text.replace(/[^0-9.,]/g, '');

            // Only one decimal point
            const firstDot = text.indexOf('.');
            if (firstDot !== -1) {
                text = text.slice(0, firstDot + 1) + text.slice(firstDot + 1).replace(/\./g, '');
            }

            // No leading dot
            if (text.startsWith('.')) text = text.slice(1);

            input.value = formatNumberWithCommas(text);
            errorEl && (errorEl.innerText = "");
            // Trigger validation formatting + caret fix once more
            validateInput(input);
        }

        function reformatNumber(input) {
            const errorEl = getErrorEl(input);
            let v = input.value.replace(/,/g, '');
            const parts = v.split('.');
            let intPart = parts[0] || '';
            let decPart = parts[1] || '';

            // Limit to two decimals on blur (optional; remove if you want unlimited)
            if (decPart) decPart = decPart.slice(0, 2);

            intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            input.value = decPart ? `${intPart}.${decPart}` : intPart;

            errorEl && (errorEl.innerText = "");
        }
    </script>
@endpush
