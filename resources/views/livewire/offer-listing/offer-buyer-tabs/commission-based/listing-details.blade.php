<div class="dashboard-section">
    <h3>Listing Details</h3>
    <div class="alert alert-info bg-light-info border-info mb-4">
        <div class="d-flex align-items-center">
            <div>
                <strong>📌 Provide the key details for this listing, including whether
                    the Buyer is currently represented by a Broker.</strong>
            </div>
        </div>
    </div>
    <!-- Service Type Selection -->

    <div class="form-group mb-4 d-none">
        <div class="row">
            <!-- Full Service (Commission-Based) Option -->
            <div class="col-md-6 mb-3">
                <div class="card service-option-card h-100 {{ $service_type === 'full_service' ? 'active-service border-primary' : '' }}"
                    wire:click="$set('service_type', 'full_service')" style="cursor: pointer;">
                    <div class="card-body p-4">
                        <div class="form-check">
                            <input class="form-check-input card-check" type="radio" wire:model="service_type"
                                id="fullService" value="full_service"
                                {{ $service_type === 'full_service' ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold" for="fullService">
                                <div class="d-flex align-items-center commission-based-agent">
                                    <i class="fa-solid fa-crown text-warning me-2 fs-5"></i>
                                    <h5 class="mb-0">Hire a Commission-Based Agent (Full Service)</h5>
                                    @if ($service_type === 'full_service')
                                        <span class="ms-2 text-success"><i class="fa-solid fa-circle-check"></i></span>
                                    @endif
                                </div>
                                @if ($service_type === 'full_service')
                                    <p class="text-success mt-2">
                                        ✓ Pay only when your transaction closes or lease is signed.
                                        Includes full-service representation from start to finish.
                                    </p>
                                @else
                                    <p class="text-muted mt-2">Pay only when your transaction closes or lease is signed.
                                    </p>
                                @endif
                            </label>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-top-0 text-end">
                        <span class="badge bg-primary bg-opacity-10 text-primary">(Commission-Based)</span>
                    </div>
                </div>
            </div>

            <!-- Limited Service (Flat Fee) Option -->
            <div class="col-md-6 mb-3">
                <div class="card service-option-card h-100 {{ $service_type === 'limited_service' ? 'active-service border-primary' : '' }}"
                    wire:click="$set('service_type', 'limited_service')" style="cursor: pointer;">
                    <div class="card-body p-4">
                        <div class="form-check">
                            <input class="form-check-input card-check" type="radio" wire:model="service_type"
                                id="limitedService" value="limited_service"
                                {{ $service_type === 'limited_service' ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold" for="limitedService">
                                <div class="d-flex align-items-center">
                                    <i class="fa-solid fa-file-invoice-dollar text-info me-2 fs-5"></i>
                                    <h5 class="mb-0">Hire a Flat Fee Agent (Limited Service)</h5>
                                    @if ($service_type === 'limited_service')
                                        <span class="ms-2 text-success"><i class="fa-solid fa-circle-check"></i></span>
                                    @endif
                                </div>
                                @if ($service_type === 'limited_service')
                                    <p class="text-success mt-2">
                                        ✓ Pay a set fee upfront for specific services.
                                        No ongoing commitment or commission.
                                    </p>
                                @else
                                    <p class="text-muted mt-2">Pay a set fee upfront for specific services.</p>
                                @endif
                            </label>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-top-0 text-end">
                        <span class="badge bg-secondary bg-opacity-10 text-secondary">(Flat Fee)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Select Listing Type -->
    <div class="form-group mb-4 text-center">
        <label class="fw-bold mb-3">Select Listing Type:</label>
        <div class="row">
            <!-- Seller Option -->
            <div class="col-md-3 mb-3">
                <a href="{{ route('offer.listing.seller') }}" class="text-decoration-none">
                    <div class="card user-type-card" style="cursor: pointer;">
                        <div class="card-body text-center position-relative">
                            <i class="fa-solid fa-user-tie fa-2x mb-2" style="color: #0ce7ef;"></i>
                            <p class="mb-1 user-selected">Create Seller Listing</p>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Buyer Option -->
            <div class="col-md-3 mb-3">
                <a href="{{ route('offer.listing.buyer') }}" class="text-decoration-none">
                    <div class="card user-type-card active-user-type border-primary" style="cursor: pointer;">
                        <div class="card-body text-center position-relative">
                            <div class="position-absolute top-0 end-0 mt-2 me-2 text-success">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                            <i class="fa-solid fa-user fa-2x mb-2" style="color: #0ce7ef;"></i>
                            <p class="mb-1 user-selected">Create Buyer Listing</p>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Landlord Option -->
            <div class="col-md-3 mb-3">
                <a href="{{ route('offer.listing.landlord') }}" class="text-decoration-none">
                    <div class="card user-type-card" style="cursor: pointer;">
                        <div class="card-body text-center position-relative">
                            <i class="fa-solid fa-user-tie fa-2x mb-2" style="color: #0ce7ef;"></i>
                            <p class="mb-1 user-selected">Create Landlord Listing</p>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Tenant Option -->
            <div class="col-md-3 mb-3">
                <a href="{{ route('offer.listing.tenant') }}" class="text-decoration-none">
                    <div class="card user-type-card" style="cursor: pointer;">
                        <div class="card-body text-center position-relative">
                            <i class="fa-solid fa-user fa-2x mb-2" style="color: #0ce7ef;"></i>
                            <p class="mb-1 user-selected">Create Tenant Listing</p>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    @if ($service_type === 'full_service')
        <div class="form-group mb-4">
            <label class="fw-bold mb-3 d-block">Listing Status:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Choose the current stage of this listing: Active means the listing is open to Agent bids and inquiries; Pending indicates the client is reviewing options and a decision is pending; Hired Agent confirms that an Agent has been selected.">
                    <i class="fa-solid fa-circle-info"></i>
                </span></label>

            </label>

            <div class="btn-group w-100 shadow-sm" role="group" aria-label="Listing status">
                <div class="input-field col-3">
                    <input type="radio" class="btn-check" wire:model="listing_status" id="status-active"
                        value="Active" autocomplete="off" checked>
                    <label class="btn btn-status btn-outline-success px-3 px-md-4 position-relative"
                        for="status-active" data-bs-toggle="tooltip" data-bs-placement="top"
                        title="The listing is open to Agent bids and inquiries.">
                        <span class="status-icon"><i class="fa-solid fa-circle-check me-2"></i></span>
                        <span class="status-text">Active</span>

                        <span
                            class="status-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success">
                            <i class="fa-solid fa-bolt"></i>
                        </span>
                    </label>
                </div>
                <div class="input-field col-3">

                    <input type="radio" class="btn-check" wire:model="listing_status" id="status-pending"
                        value="Pending" autocomplete="off">
                    <label class="btn btn-status btn-outline-warning px-3 px-md-4 position-relative"
                        for="status-pending" data-bs-toggle="tooltip" data-bs-placement="top"
                        title="The client is reviewing bids or negotiating with Agents. New bids cannot be submitted.">
                        <span class="status-icon"><i class="fa-solid fa-clock me-2"></i></span>
                        <span class="status-text">Pending</span>
                        <span
                            class="status-badge position-absolute top-0 start-100 translate-middle badge rounded-pill" style="background-color:#d97706;color:#fff;">
                            <i class="fa-solid fa-exclamation"></i>
                        </span>
                    </label>
                </div>
                <div class="input-field col-3">

                    <input type="radio" class="btn-check" wire:model="listing_status" id="status-hired"
                        value="Hired Agent" autocomplete="off">
                    <label class="btn btn-status btn-outline-primary px-3 px-md-4 position-relative"
                        for="status-hired" data-bs-toggle="tooltip" data-bs-placement="top"
                        title="An Agent has been selected and the listing is closed to new bids.">
                        <span class="status-icon"><i class="fa-solid fa-user-tie me-2"></i></span>
                        <span class="status-text">Hired Agent</span>
                        <span
                            class="status-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary">
                            <i class="fa-solid fa-handshake"></i>
                        </span>
                    </label>
                </div>
                <div id="expired_tooltip" class="input-field col-3">
                    <!-- Expired Status -->
                    <input type="radio" class="btn-check" wire:model="listing_status" id="status-expired"
                        value="Expired" autocomplete="off" disabled>
                    <label class="btn btn-status btn-outline-secondary px-3 px-md-4 position-relative"
                        for="status-expired">
                        <span class="status-icon"><i class="fa-solid fa-calendar-xmark me-2"></i></span>
                        <span class="status-text">Expired</span>
                        <span
                            class="status-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-secondary">
                            <i class="fa-solid fa-calendar-xmark"></i>
                        </span>
                    </label>
                    <span class="expired_tooltip">
                        The listing has ended without a hired Agent. You cannot select this status — it is
                        applied automatically when the listing expires.

                    </span>
                </div>

            </div>
        </div>
    @endif

    @if ($service_type === 'full_service')
        <div class="alert alert-warning mt-3 p-2 small">

            Fields marked with <span class="text-danger">*</span> are required. You do not need to fill in all other
            fields; however, providing additional information will help Agents better serve you.

        </div>
    @endif
    <!-- Listing Title -->
    <div class="form-group">
        <label class="fw-bold">Listing Title: <span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter a clear title describing the Buyer's property search criteria. Focus on property type, size, and target location.">
            <i class="fa-solid fa-circle-info"></i> </span>

        <div class="input-cover">
            <input type="text" wire:model="listing_title" id="listing_title" class="form-control has-icon"
                data-icon="fa-solid fa-tag"
                placeholder="Enter listing title (e.g., Buyer Seeking 3-Bedroom Home in Pinellas County, FL)"
                required>
        </div>
        <span class="error mt-2" id="listing_title_error"></span>
    </div>

    <!-- Listing Date -->
    <div class="form-group">
        <label class="fw-bold">Listing Date:<span class="text-danger">*</span>

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select today’s date to make your request live and visible to Agents on the platform.">
                <i class="fa-solid fa-circle-info"></i> </span>
        </label>
        <div class="input-cover">
            <input type="date" wire:model="listing_date" class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days" required>
        </div>
        <span class="error mt-2" id="listing_date_error"></span>
    </div>

    <!-- Expiration Date -->
    <div class="form-group">
        <label class="fw-bold">Expiration Date:<span class="text-danger">*</span>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="The date your Agent request will expire. To keep it active, extend the expiration before this date.">
                <i class="fa-solid fa-circle-info"></i> </span>
        </label>


        <div class="input-cover">
            <input type="date" wire:model="expiration_date" class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days" required>
        </div>
        <span class="error mt-2" id="expiration_date_error"></span>
    </div>


     <div class="form-group">
        <label class="fw-bold">Listing Type:<span class="text-danger">*</span>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select how Agents submit bids for this listing. &lt;br&gt;&lt;br&gt;&lt;strong&gt;Bidding Period:&lt;/strong&gt; Agents may submit bids until the bidding deadline expires. The timer creates a structured window to encourage competitive offers. You may review, accept, counter, or reject bids at any time. &lt;br&gt;&lt;br&gt;&lt;strong&gt;Traditional:&lt;/strong&gt; Agents may submit bids at any time while the listing remains active. There is no structured bidding countdown, and bids may be reviewed and acted on as they are received.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        @if (!empty($isEditMode))
            <div class="input-cover locked-field-wrapper position-relative" data-lock-msg="Listing Type cannot be edited once the listing is created.">
                <i class="input-icon fa-solid fa-lock"></i>
                <input type="text" class="form-control has-icon" value="{{ $auction_type }}" disabled style="background:#f8f9fa; cursor:not-allowed;">
                <div class="locked-field-overlay" style="position:absolute;inset:0;cursor:not-allowed;z-index:2;"></div>
            </div>
            <p class="text-danger small mt-2 mb-0"><i class="fa-solid fa-lock me-1"></i> Listing Type cannot be changed after the listing has been created.</p>
        @else
            <div class="input-cover">
                <select wire:model="auction_type" id="auction_type" class="form-control has-icon"
                    data-icon="fa-solid fa-file-lines" required>
                    <option value="">Select</option>
                    <option value="Bidding Period" title="Agents may submit bids until the bidding deadline expires. The timer creates a structured window to encourage competitive offers. You may review, accept, counter, or reject bids at any time.">Bidding Period</option>
                    <option value="Traditional">Traditional</option>
                </select>
            </div>
            <span class="error mt-2" id="auction_type_error"></span>
        @endif
    </div>


    <div class="form-group mt-3" @if ($auction_type !== 'Bidding Period') style="display: none;" @endif>
        <label class="fw-bold">Bidding Period Length: <span class="text-danger">*</span>

        </label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select how long Agents have to submit bids. The timer defines the active bidding window but does not restrict when you can review or respond to bids.">
            <i class="fa-solid fa-circle-info"></i> </span>
        <div class="input-cover">
            <select wire:model="auction_time" id="auction_time" class="form-control has-icon"
                data-icon="fa-regular fa-clock" @if ($auction_type === 'Bidding Period') required @endif>
                <option value="">Select</option>
                {{-- @foreach ($auction_lengths as $row_pt)
                    <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
                @endforeach --}}

                  <option value="1 Day">1 Day</option>
                <option value="3 Days">3 Days</option>
                <option value="5 Days">5 Days</option>
                <option value="7 Days">7 Days</option>
                <option value="10 Days">10 Days</option>
                <option value="14 Days">14 Days</option>
            </select>
        </div>
        <span class="error mt-2" id="auction_time_error"></span>
    </div>

    {{-- Bidding Period Advisory Notice (shown only when Bidding Period is selected) --}}
    <div class="alert alert-info small py-2 mt-2 mb-3" @if ($auction_type !== 'Bidding Period') style="display: none;" @endif wire:key="bp-notice-buyer">
        <i class="fa-solid fa-circle-info me-1"></i>
        <strong>Bidding Period:</strong> The timer creates a structured window for Agents to submit competitive bids. You may review, accept, counter, or reject bids at any time during or after the bidding period.
    </div>

</div>

<style>
    .user-type-icon {
        color: #0ce7ef;
    }

    .service-option-card {
        border: 2px solid #e9ecef;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .service-option-card:hover {
        border-color: #0d6efd;
    }

    .active-service {
        border-color: #0d6efd;
        background-color: #f8f9fa;
    }

    .user-type-card {
        border: 2px solid #e9ecef;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .user-type-card:hover {
        border-color: #0d6efd;
    }

    .active-user-type {
        border-color: #0d6efd;
        background-color: #f8f9fa;
    }

    .total-fee-display {
        background-color: #f8f9fa;
        padding: 20px;
        border-radius: 5px;
        text-align: center;
    }

    .card-footer span.badge {
        font-size: 0.85rem;
    }

    .btn-status {
        position: relative;
        overflow: hidden;
    }

    .status-badge {
        font-size: 0.6rem;
        padding: 0.25em 0.4em;
    }

    .status-icon {
        margin-right: 5px;
    }

    .active-user-type {
        border-color: #0d6efd !important;
        background-color: #f8f9fa;
    }

    .card-check {
        position: absolute;
        opacity: 0;
    }

    .form-check-label {
        cursor: pointer;
    }

    /* Hide emoji spans when not in dropdown */
    select option>.dropdown-only {
        display: none;
    }

    /* Show emoji spans only in dropdown */
    select:focus option>.dropdown-only,
    select:active option>.dropdown-only {
        display: inline;
    }

    /* Tooltip box styling */
    .expired_tooltip {
        display: none;
        position: absolute;
        bottom: calc(100% + 12px);
        left: 50%;
        transform: translateX(-50%);
        padding: 14px;
        background-color: #000000c9;
        color: #fff;
        border-radius: 5px;
        font-size: 12px;
        width: 250px;
        z-index: 10000 !important;
        box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
        text-align: center;
    }
    
    #expired_tooltip {
        position: relative;
    }

    /* Tooltip arrow */
    .expired_tooltip::after {
        content: '';
        position: absolute;
        bottom: -5px;
        /* Adjust based on the position of your tooltip */
        left: 50%;
        transform: translateX(-50%);
        border-left: 6px solid transparent;
        border-right: 6px solid transparent;
        border-top: 6px solid #000000c9;
        background-color: #000000ab
            /* Arrow color matching the tooltip */
    }

    /* Show tooltip when hovering over the label or the parent div */
    #status-expired:hover+.expired_tooltip,
    #expired_tooltip:hover .expired_tooltip {
        display: block !important;
    }

    /* Custom Listing Type Dropdown Styles */
    .listing-type-custom-dropdown {
        position: relative;
        width: 100%;
    }

    .listing-type-selected {
        display: flex;
        align-items: center;
        padding: 0.625rem 0.75rem;
        padding-left: 2.5rem;
        min-height: calc(1.5em + 1.25rem + 2px);
        font-size: 1rem;
        font-weight: 400;
        line-height: 1.5;
        color: #212529;
        background-color: #fff;
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
        cursor: pointer;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .listing-type-selected:focus {
        border-color: #86b7fe;
        outline: 0;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    .listing-type-icon {
        color: #11b7cf;
        position: absolute;
        left: 0.75rem;
        pointer-events: none;
    }

    .listing-type-text {
        flex: 1;
        font-family: 'Poppins', sans-serif;
        font-size: 1rem;
        font-weight: 400;
    }

    .listing-type-arrow {
        color: #6c757d;
        transition: transform 0.2s ease;
    }

    .listing-type-custom-dropdown.open .listing-type-arrow {
        transform: rotate(180deg);
    }

    .listing-type-options {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid #ced4da;
        border-top: none;
        border-radius: 0 0 0.375rem 0.375rem;
        z-index: 1000;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .listing-type-custom-dropdown.open .listing-type-options {
        display: block;
    }

    .listing-type-option {
        position: relative;
        padding: 10px 15px;
        cursor: pointer;
        transition: background-color 0.15s ease;
        font-family: 'Poppins', sans-serif;
        font-size: 1rem;
        font-weight: 400;
        color: #212529;
    }

    .listing-type-option:hover {
        background-color: #f8f9fa;
    }

    .listing-type-option:last-child {
        border-radius: 0 0 0.375rem 0.375rem;
    }

    .listing-type-option.selected {
        background-color: #e7f1ff;
    }

    .listing-type-option-tooltip {
        display: none;
        position: absolute;
        left: calc(100% + 10px);
        top: 50%;
        transform: translateY(-50%);
        background-color: rgba(0, 0, 0, 0.9);
        color: #fff;
        padding: 12px 16px;
        border-radius: 6px;
        font-size: 13px;
        width: 320px;
        z-index: 99999;
        line-height: 1.5;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        pointer-events: none;
    }

    .listing-type-option-tooltip::before {
        content: '';
        position: absolute;
        left: -6px;
        top: 50%;
        transform: translateY(-50%);
        border-top: 6px solid transparent;
        border-bottom: 6px solid transparent;
        border-right: 6px solid rgba(0, 0, 0, 0.9);
    }

    .listing-type-option:hover .listing-type-option-tooltip {
        display: block;
    }

    @media (max-width: 768px) {
        .listing-type-option-tooltip {
            left: 0;
            right: 0;
            top: 100%;
            transform: none;
            width: auto;
            margin-top: 5px;
        }

        .listing-type-option-tooltip::before {
            display: none;
        }
    }
</style>

<script>
    (function() {
        // Custom Listing Type Dropdown - use event delegation for reliability
        function initListingTypeDropdown() {
            const dropdowns = document.querySelectorAll('.listing-type-custom-dropdown');
            
            dropdowns.forEach(dropdown => {
                // Remove old initialized flag to re-attach handlers after Livewire updates
                delete dropdown.dataset.initialized;
                
                const selected = dropdown.querySelector('.listing-type-selected');
                const options = dropdown.querySelectorAll('.listing-type-option');
                const textSpan = dropdown.querySelector('.listing-type-text');
                const hiddenInput = dropdown.closest('.input-cover')?.querySelector('input[type="hidden"]');
                
                if (!selected || !textSpan) return;
                
                // Clone and replace to remove old event listeners
                const newSelected = selected.cloneNode(true);
                selected.parentNode.replaceChild(newSelected, selected);
                
                // Toggle dropdown on click
                newSelected.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const wasOpen = dropdown.classList.contains('open');
                    document.querySelectorAll('.listing-type-custom-dropdown.open').forEach(d => d.classList.remove('open'));
                    if (!wasOpen) {
                        dropdown.classList.add('open');
                    }
                });

                // Handle keyboard navigation
                newSelected.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        e.stopPropagation();
                        const wasOpen = dropdown.classList.contains('open');
                        document.querySelectorAll('.listing-type-custom-dropdown.open').forEach(d => d.classList.remove('open'));
                        if (!wasOpen) {
                            dropdown.classList.add('open');
                        }
                    }
                });

                // Option selection - clone and replace each option
                options.forEach(option => {
                    const newOption = option.cloneNode(true);
                    option.parentNode.replaceChild(newOption, option);
                    
                    newOption.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const value = this.dataset.value;
                        const currentTextSpan = dropdown.querySelector('.listing-type-text');
                        if (currentTextSpan) {
                            currentTextSpan.textContent = value;
                        }
                        
                        dropdown.querySelectorAll('.listing-type-option').forEach(o => o.classList.remove('selected'));
                        this.classList.add('selected');
                        
                        if (hiddenInput) {
                            hiddenInput.value = value;
                            hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                        
                        dropdown.classList.remove('open');
                        
                        // Trigger Livewire update
                        if (window.Livewire) {
                            const componentEl = dropdown.closest('[wire\\:id]');
                            if (componentEl) {
                                const component = Livewire.find(componentEl.getAttribute('wire:id'));
                                if (component) {
                                    component.set('auction_type', value);
                                }
                            }
                        }
                    });
                });

                // Mark currently selected option
                const currentValue = hiddenInput?.value || dropdown.querySelector('.listing-type-text')?.textContent;
                dropdown.querySelectorAll('.listing-type-option').forEach(opt => {
                    if (opt.dataset.value === currentValue) {
                        opt.classList.add('selected');
                    }
                });
            });
        }

        // Sync dropdown display with Livewire state
        function syncDropdownDisplay() {
            document.querySelectorAll('.listing-type-custom-dropdown').forEach(dropdown => {
                const hiddenInput = dropdown.closest('.input-cover')?.querySelector('input[type="hidden"]');
                const textSpan = dropdown.querySelector('.listing-type-text');
                if (hiddenInput && textSpan) {
                    const value = hiddenInput.value;
                    textSpan.textContent = value || 'Select';
                    dropdown.querySelectorAll('.listing-type-option').forEach(opt => {
                        opt.classList.toggle('selected', opt.dataset.value === value);
                    });
                }
            });
        }

        // Close dropdown on outside click (only attach once)
        if (!window._listingTypeOutsideClickAttached) {
            window._listingTypeOutsideClickAttached = true;
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.listing-type-custom-dropdown')) {
                    document.querySelectorAll('.listing-type-custom-dropdown.open').forEach(d => d.classList.remove('open'));
                }
            });
        }

        // Robust initialization with delay for Livewire DOM updates
        function safeInit() {
            setTimeout(function() {
                initListingTypeDropdown();
                syncDropdownDisplay();
            }, 50);
        }

        // Initialize immediately
        safeInit();

        // Also initialize on DOMContentLoaded if not ready yet
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', safeInit);
        }

        // Initialize on Livewire load event
        document.addEventListener('livewire:load', function() {
            safeInit();
            
            if (window.Livewire) {
                Livewire.hook('message.processed', safeInit);
            }
        });
        
        // Also listen for Livewire updated events
        window.addEventListener('livewire:updated', safeInit);
    })();
</script>

