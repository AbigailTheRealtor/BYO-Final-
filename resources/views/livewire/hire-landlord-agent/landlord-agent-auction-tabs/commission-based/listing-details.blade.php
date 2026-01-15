<div class="dashboard-section">
    <h3>Listing Details</h3>
    <div class="alert alert-info bg-light-info border-info mb-4">
        <div class="d-flex align-items-center">
            <div>
                <strong>📌 Provide the key details for this request, including the preferred Agent hire date and whether
                    the Landlord is currently represented by a Broker.</strong>
            </div>
        </div>
    </div>

    <!-- Service Type Selection -->

    <div class="form-group mb-4 d-none">
        <div class="row container-sm">
            <!-- Full Service (Commission-Based) Option -->
            <div class="col-md-12 mb-3">
                <div class="card service-option-card h-100 {{ $service_type === 'full_service' ? 'active-service border-primary' : '' }}"
                    wire:click="$set('service_type', 'full_service')" style="cursor: pointer;">
                    <div class="card-body p-4">
                        <div class="form-check">
                            <input class="form-check-input card-check" type="radio" wire:model="service_type"
                                id="fullService" value="full_service"
                                {{ $service_type === 'full_service' ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold" for="fullService">
                                <div class="d-flex align-items-center commission-based-agent">
                                    <i class="fas fa-crown text-warning me-2 fs-5"></i>
                                    <h5 class="mb-0">Hire a Commission-Based Agent (Full Service)</h5>
                                    @if ($service_type === 'full_service')
                                        <span class="ms-2 text-success"><i class="fas fa-check-circle"></i></span>
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
            {{-- <div class="col-md-6 mb-3">
                <div class="card service-option-card h-100 {{ $service_type === 'limited_service' ? 'active-service border-primary' : '' }}"
                    wire:click="$set('service_type', 'limited_service')" style="cursor: pointer;">
                    <div class="card-body p-4">
                        <div class="form-check">
                            <input class="form-check-input card-check" type="radio" wire:model="service_type"
                                id="limitedService" value="limited_service"
                                {{ $service_type === 'limited_service' ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold" for="limitedService">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-file-invoice-dollar text-info me-2 fs-5"></i>
                                    <h5 class="mb-0">Hire a Flat Fee Agent (Limited Service)</h5>
                                    @if ($service_type === 'limited_service')
                                        <span class="ms-2 text-success"><i class="fas fa-check-circle"></i></span>
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
            </div> --}}
        </div>
    </div>

    {{-- @isset($user_type)

        <!-- User Type Selection -->
        <!-- User Type Selection -->
        <div class="form-group mb-4">
            <label class="fw-bold mb-3">I am a:</label>
            <div class="row">
                <!-- Seller Option -->
                <div class="col-md-3 mb-3">
                    <div class="card user-type-card {{ $user_type === 'seller' ? 'active-user-type border-primary' : '' }}"
                        wire:click="$set('user_type', 'seller')" style="cursor: pointer;">
                        <div class="card-body text-center position-relative">
                            @if ($user_type === 'seller')
                                <div class="position-absolute top-0 end-0 mt-2 me-2 text-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            @endif
                            <div class="form-check">
                                <input class="form-check-input card-check" type="radio" wire:model="user_type"
                                    id="userTypeSeller" value="seller" {{ $user_type === 'seller' ? 'checked' : '' }}>
                                <label class="form-check-label" for="userTypeSeller">
                                    <i class="fas fa-user-tie fa-2x mb-2" style="color: #0ce7ef;"></i>
                                    <h5 class="mb-1">Seller</h5>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Buyer Option -->
                <div class="col-md-3 mb-3">
                    <div class="card user-type-card {{ $user_type === 'buyer' ? 'active-user-type border-primary' : '' }}"
                        wire:click="$set('user_type', 'buyer')" style="cursor: pointer;">
                        <div class="card-body text-center position-relative">
                            @if ($user_type === 'buyer')
                                <div class="position-absolute top-0 end-0 mt-2 me-2 text-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            @endif
                            <div class="form-check">
                                <input class="form-check-input card-check" type="radio" wire:model="user_type"
                                    id="userTypeBuyer" value="buyer" {{ $user_type === 'buyer' ? 'checked' : '' }}>
                                <label class="form-check-label" for="userTypeBuyer">
                                    <i class="fas fa-user fa-2x mb-2" style="color: #0ce7ef;"></i>
                                    <h5 class="mb-1">Buyer</h5>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Landlord Option -->
                <div class="col-md-3 mb-3">
                    <div class="card user-type-card {{ $user_type === 'landlord' ? 'active-user-type border-primary' : '' }}"
                        wire:click="$set('user_type', 'landlord')" style="cursor: pointer;">
                        <div class="card-body text-center position-relative">
                            @if ($user_type === 'landlord')
                                <div class="position-absolute top-0 end-0 mt-2 me-2 text-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            @endif
                            <div class="form-check">
                                <input class="form-check-input card-check" type="radio" wire:model="user_type"
                                    id="userTypeLandlord" value="landlord"
                                    {{ $user_type === 'landlord' ? 'checked' : '' }}>
                                <label class="form-check-label" for="userTypeLandlord">
                                    <i class="fas fa-user-tie fa-2x mb-2" style="color: #0ce7ef;"></i>
                                    <h5 class="mb-1">Landlord</h5>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tenant Option -->
                <div class="col-md-3 mb-3">
                    <div class="card user-type-card {{ $user_type === 'tenant' ? 'active-user-type border-primary' : '' }}"
                        wire:click="$set('user_type', 'tenant')" style="cursor: pointer;">
                        <div class="card-body text-center position-relative">
                            @if ($user_type === 'tenant')
                                <div class="position-absolute top-0 end-0 mt-2 me-2 text-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            @endif
                            <div class="form-check">
                                <input class="form-check-input card-check" type="radio" wire:model="user_type"
                                    id="userTypeTenant" value="tenant" {{ $user_type === 'tenant' ? 'checked' : '' }}>
                                <label class="form-check-label" for="userTypeTenant">
                                    <i class="fas fa-user fa-2x mb-2" style="color: #0ce7ef;"></i>
                                    <h5 class="mb-1">Tenant</h5>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endisset --}}

    @isset($user_type)

        <!-- User Type Selection -->
        <div class="form-group mb-4 text-center">
            <label class="fw-bold mb-3">Select Agent Type:</label>
            <div class="row">
                <!-- Seller Option -->
                <div class="col-md-3 mb-3">
                    <div class="card user-type-card {{ $user_type === 'seller' ? 'active-user-type border-primary' : '' }}"
                        wire:click="$set('user_type', 'seller')" style="cursor: pointer;">
                        <div class="card-body text-center position-relative">
                            @if ($user_type === 'seller')
                                <div class="position-absolute top-0 end-0 mt-2 me-2 text-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            @endif
                            <div class="form-check p-0">
                                <input class="form-check-input card-check" type="radio" wire:model="user_type"
                                    id="userTypeSeller" value="seller" {{ $user_type === 'seller' ? 'checked' : '' }}>
                                <label class="form-check-label" for="userTypeSeller">
                                    <i class="fas fa-user-tie fa-2x mb-2" style="color: #0ce7ef;"></i>
                                    <p class="mb-1 user-selected">Hire a Seller’s Agent </p>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Buyer Option -->
                <div class="col-md-3 mb-3">
                    <div class="card user-type-card {{ $user_type === 'buyer' ? 'active-user-type border-primary' : '' }}"
                        wire:click="$set('user_type', 'buyer')" style="cursor: pointer;">
                        <div class="card-body text-center position-relative">
                            @if ($user_type === 'buyer')
                                <div class="position-absolute top-0 end-0 mt-2 me-2 text-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            @endif
                            <div class="form-check p-0">
                                <input class="form-check-input card-check" type="radio" wire:model="user_type"
                                    id="userTypeBuyer" value="buyer" {{ $user_type === 'buyer' ? 'checked' : '' }}>
                                <label class="form-check-label" for="userTypeBuyer">
                                    <i class="fas fa-user fa-2x mb-2" style="color: #0ce7ef;"></i>
                                    <p class="mb-1 user-selected">Hire a Buyer’s Agent</p>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Landlord Option -->
                <div class="col-md-3 mb-3">
                    <div class="card user-type-card {{ $user_type === 'landlord' ? 'active-user-type border-primary' : '' }}"
                        wire:click="$set('user_type', 'landlord')" style="cursor: pointer;">
                        <div class="card-body text-center position-relative">
                            @if ($user_type === 'landlord')
                                <div class="position-absolute top-0 end-0 mt-2 me-2 text-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            @endif
                            <div class="form-check p-0">
                                <input class="form-check-input card-check" type="radio" wire:model="user_type"
                                    id="userTypeLandlord" value="landlord" {{ $user_type === 'landlord' ? 'checked' : '' }}>
                                <label class="form-check-label" for="userTypeLandlord">
                                    <i class="fas fa-user-tie fa-2x mb-2" style="color: #0ce7ef;"></i>
                                    <p class="mb-1 user-selected">Hire a Landlord’s Agent</p>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tenant Option -->
                <div class="col-md-3 mb-3">
                    <div class="card user-type-card {{ $user_type === 'tenant' ? 'active-user-type border-primary' : '' }}"
                        wire:click="$set('user_type', 'tenant')" style="cursor: pointer;">
                        <div class="card-body text-center position-relative">
                            @if ($user_type === 'tenant')
                                <div class="position-absolute top-0 end-0 mt-2 me-2 text-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            @endif
                            <div class="form-check p-0">
                                <input class="form-check-input card-check" type="radio" wire:model="user_type"
                                    id="userTypeTenant" value="tenant" {{ $user_type === 'tenant' ? 'checked' : '' }}>
                                <label class="form-check-label" for="userTypeTenant">
                                    <i class="fas fa-user fa-2x mb-2" style="color: #0ce7ef;"></i>
                                    <p class="mb-1 user-selected">Hire a Tenant’s Agent</p>
                                </label>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endisset

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
                        <span class="status-icon"><i class="fas fa-check-circle me-2"></i></span>
                        <span class="status-text">Active</span>

                        <span
                            class="status-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success">
                            <i class="fas fa-bolt"></i>
                        </span>
                    </label>
                </div>
                <div class="input-field col-3">

                    <input type="radio" class="btn-check" wire:model="listing_status" id="status-pending"
                        value="Pending" autocomplete="off">
                    <label class="btn btn-status btn-outline-warning px-3 px-md-4 position-relative"
                        for="status-pending" data-bs-toggle="tooltip" data-bs-placement="top"
                        title="The client is reviewing bids or negotiating with Agents. New bids cannot be submitted.">
                        <span class="status-icon"><i class="fas fa-clock me-2"></i></span>
                        <span class="status-text">Pending</span>
                        <span
                            class="status-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark">
                            <i class="fas fa-exclamation"></i>
                        </span>
                    </label>
                </div>
                <div class="input-field col-3">

                    <input type="radio" class="btn-check" wire:model="listing_status" id="status-hired"
                        value="Hired Agent" autocomplete="off">
                    <label class="btn btn-status btn-outline-primary px-3 px-md-4 position-relative"
                        for="status-hired" data-bs-toggle="tooltip" data-bs-placement="top"
                        title="An Agent has been selected and the listing is closed to new bids.">
                        <span class="status-icon"><i class="fas fa-user-tie me-2"></i></span>
                        <span class="status-text">Hired Agent</span>
                        <span
                            class="status-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary">
                            <i class="fas fa-handshake"></i>
                        </span>
                    </label>
                </div>
                <div id="expired_tooltip" class="input-field col-3">
                    <!-- Expired Status -->
                    <input type="radio" class="btn-check" wire:model="listing_status" id="status-expired"
                        value="Expired" autocomplete="off" disabled>
                    <label class="btn btn-status btn-outline-secondary px-3 px-md-4 position-relative"
                        for="status-expired">
                        <span class="status-icon"><i class="fas fa-calendar-times me-2"></i></span>
                        <span class="status-text">Expired</span>
                        <span
                            class="status-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-secondary">
                            <i class="fas fa-calendar-times"></i>
                        </span>
                    </label>
                    <span class="expired_tooltip">
                        The listing has ended without a hired Agent. You cannot select this status — it is
                        applied automatically when the listing expires.
                    </span>
                </div>

            </div>
        </div>

        <div class="alert alert-warning mt-3 p-2 small">

            Fields marked with <span class="text-danger">*</span> are required. You do not need to fill in all other
            fields; however, providing additional information will help Agents better serve you.

        </div>
    @endif
    <!-- Listing Title -->
    <div class="form-group">
        <label class="fw-bold">Listing Title: <span class="text-danger">*</span>
        </label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter a short, clear title describing the type of Agent the Landlord needs and the location.">
            <i class="fa-solid fa-circle-info"></i> </span>
        <div class="input-cover">
            <input type="text" wire:model="listing_title" id="listing_title" class="form-control has-icon"
                data-icon="fa-solid fa-tag"
                placeholder="Enter listing title (e.g., Need a Leasing Agent in Tampa, FL to Rent Out My Property)"
                required>
        </div>
        <span class="error mt-2" id="listing_title_error"></span>
    </div>

    <!-- Current Representation Agreement Status with Broker -->
    <div class="form-group">
        <label class="fw-bold">Current Representation Status with Broker:<span class="text-danger">*</span>
        </label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate whether the Landlord is currently under a signed agreement with a Broker. “Represented” means the Landlord has signed a Landlord representation agreement; “Not Represented” means no agreement has been signed, and the Landlord is free to hire a Broker.">
            <i class="fa-solid fa-circle-info"></i> </span>
        <div class="input-cover">
            <select wire:model="working_with_agent" id="working_with_agent" class="form-control has-icon"
                data-icon="fa-solid fa-handshake" required>
                <option value="">Select</option>
                <option value="Represented">Represented</option>
                <option value="Not Represented">Not Represented</option>
            </select>

        </div>
        <span class="error mt-2" id="working_with_agent_error"></span>
        <!-- Representation Notice (hidden by default) -->
        <div id="representation_notice" class="alert alert-danger mt-2 d-none">
            This service is only available to Landlords who are not currently represented by a Broker.
        </div>
    </div>
    @if ($service_type === 'full_service')
        <!--Desired Agent Hire Date -->
        <div class="form-group">
            <label class="fw-bold">Desired Agent Hire Date:<span class="text-danger">*</span>

            </label>

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select the date the Landlord needs to hire an Agent.">
                <i class="fa-solid fa-circle-info"></i> </span>
            <div class="input-cover">
                <input type="date" wire:model="desired_agent_hire_date" class="form-control has-icon"
                    data-icon="fa-regular fa-calendar-days" required>
            </div>
            <span class="error mt-2" id="desired_agent_hire_date_error"></span>
        </div>
    @endif
    <!-- Listing Date -->
    <div class="form-group">
        <label class="fw-bold">Listing Date:<span class="text-danger">*</span>

        </label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select today’s date to make your request live and visible to Agents on the platform.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="date" wire:model="listing_date" class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days" required>
        </div>
        <span class="error mt-2" id="listing_date_error"></span>
    </div>

    <!-- Expiration Date -->
    <div class="form-group">
        <label class="fw-bold">Expiration Date:<span class="text-danger">*</span>

        </label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="The date your Agent request will expire. To keep it active, extend the expiration before this date.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="date" wire:model="expiration_date" class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days" required>
        </div>
        <span class="error mt-2" id="expiration_date_error"></span>
    </div>

    {{-- <div class="form-group">
        <label class="fw-bold">Listing Type:<span class="text-danger">*</span></label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select how Agents submit bids for this listing. This choice controls timing, bid visibility, and how Agent bids are reviewed.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="auction_type" id="auction_type" class="form-control has-icon"
                data-icon="fa-solid fa-file-alt" required>
                <option value="">Select</option>
                <option value="Auction">Auction</option>
                <option value="Traditional">Traditional</option>
            </select>
        </div>
        <span class="error mt-2" id="auction_type_error"></span>
    </div>

    <!-- Auction Length (Only for Auction) -->
    <div class="form-group mt-3" @if ($auction_type !== 'Auction') style="display: none;" @endif>
        <label class="fw-bold">Auction Length: <span class="text-danger">*</span>

        </label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Agents can submit bids until the countdown ends. Each bid will display the Agent's Offered Services and Broker Compensation &amp; Agency Agreement Terms. Once the timer expires, you may select the Agent who best meets your needs—you are not required to choose the highest or lowest bid.">
            <i class="fa-solid fa-circle-info"></i> </span>
        <div class="input-cover">
            <select wire:model="auction_time" id="auction_time" class="form-control has-icon"
                data-icon="fa-regular fa-clock" @if ($auction_type === 'Auction') required @endif>
                <option value="">Select</option>
                @foreach ($auction_lengths_seller as $row_pt)
                    <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="auction_time_error"></span>
    </div>

    <!-- Agent Bid Visibility (Only for Traditional) -->
    <div class="form-group mt-3" @if ($auction_type !== 'Traditional') style="display: none;" @endif>
        <label class="fw-bold">Agent Bid Visibility Preference:<span class="text-danger">*</span></label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Choose whether Agent bids will be public or private. Public bids encourage competition; private bids are only visible to the listing creator.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="agent_bid_visibility" id="agent_bid_visibility" class="form-control has-icon"
                data-icon="fa-solid fa-eye" @if ($auction_type === 'Traditional') required @endif>
                <option value="">Select</option>
                <option value="public">Agent bids will be visible to other Agents</option>
                <option value="private">Agent bids will remain private</option>
            </select>
        </div>
        <span class="error mt-2" id="agent_bid_visibility_error"></span>
    </div> --}}

<div class="form-group">
        <label class="fw-bold">Listing Type:<span class="text-danger">*</span>

        </label>
       <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select how Agents submit bids for this listing. This choice controls timing, bid visibility, and how Agent bids are reviewed.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="hidden" wire:model="auction_type" id="auction_type_hidden" required>
            <div class="listing-type-custom-dropdown" id="listing_type_dropdown_landlord">
                <div class="listing-type-selected" tabindex="0">
                    <i class="fa-solid fa-file-alt listing-type-icon"></i>
                    <span class="listing-type-text">{{ $auction_type ?: 'Select' }}</span>
                    <i class="fa-solid fa-chevron-down listing-type-arrow"></i>
                </div>
                <div class="listing-type-options">
                    <div class="listing-type-option" data-value="">
                        <span>Select</span>
                    </div>
                    <div class="listing-type-option" data-value="Bidding Period">
                        <span>Bidding Period</span>
                        <div class="listing-type-option-tooltip">
                            Agents may submit bids until the bidding timer ends.<br><br>
                            During the bidding period, bids cannot be accepted or finalized. Once the timer ends, all Agent bids are revealed for review and comparison, including Services Offered, Broker Compensation & Agency Agreement Terms, and Match Scores.<br><br>
                            After the bidding period closes, the listing continues in Traditional mode for reviewing, countering, and accepting bids.
                        </div>
                    </div>
                    <div class="listing-type-option" data-value="Traditional">
                        <span>Traditional</span>
                        <div class="listing-type-option-tooltip">
                            Agents may submit bids at any time.<br><br>
                            Agent bids are visible as they are received and may be reviewed, accepted, countered, or rejected immediately. There is no bidding timer, and bids are handled on a rolling basis.
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <span class="error mt-2" id="auction_type_error"></span>
    </div>

    @if ($auction_type === 'Bidding Period')
    <div class="alert alert-info mt-3" style="background: #e3f2fd; border: 1px solid #90caf9;">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Bidding Period Transparency Notice:</strong> During the active bidding period, participating agents may view anonymized competing bids limited to Broker Compensation & Agency Agreement Terms, Offered Services, and Match Scores only. Agent identities and all other bid information remain confidential.
    </div>
    @endif

    <div class="form-group mt-3" @if ($auction_type !== 'Bidding Period') style="display: none;" @endif>
        <label class="fw-bold">Bidding Period Length: <span class="text-danger">*</span>

        </label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select how long Agents have to submit bids on this listing. Once the bidding period ends, you may review all submitted bids and hire an Agent.">
            <i class="fa-solid fa-circle-info"></i> </span>
        <div class="input-cover">
            <select wire:model="auction_time" id="auction_time" class="form-control has-icon"
                data-icon="fa-regular fa-clock" @if ($auction_type === 'Bidding Period') required @endif>
                <option value="">Select</option>
                @foreach ($auction_lengths_seller as $row_pt)
                    <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
                @endforeach

           
            </select>
        </div>
        <span class="error mt-2" id="auction_time_error"></span>
    </div>

    <div class="form-group">
        <label class="fw-bold">Meeting Preference:<span class="text-danger">*</span></label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select how you'd prefer to meet or communicate with the Agent after they are selected. This helps Agents know your expectations in advance.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="meeting_Preference" class="form-control has-icon" data-icon="fa-solid fa-list"
                required>
                <option value="">Select</option>
                <option value="In-Person Meeting">In-Person Meeting</option>
                <option value="Virtual/Phone Meeting">Virtual/Phone Meeting</option>
                <option value="Either (In-Person or Virtual/Phone)">Either (In-Person or Virtual/Phone)</option>
            </select>
        </div>
        <span class="error mt-2" id="meeting_Preference_error"></span>
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

    #expired_tooltip {
        position: relative;
    }

    /* Tooltip box styling */
    .expired_tooltip {
        display: none;
        position: absolute;
        top: -104px;
        /* Adjust as needed */
        left: 10%;
        /* Adjust as needed */
        padding: 14px;
        background-color: #000000c9;
        color: #fff;
        border-radius: 5px;
        font-size: 12px;
        width: 250px;
        z-index: 10000 !important;
        box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
        text-align: center;
        position: absolute;
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
{{-- <script>
    document.addEventListener('livewire:load', function() {
        const select = document.getElementById('auction_type');
        let isDropdownOpen = false;

        // Store original options with emojis
        const originalOptions = {
            'Auction': '🔨 Auction',
            'Traditional': '📝 Traditional'
        };

        // When clicking the select (before dropdown opens)
        select.addEventListener('mousedown', function(e) {
            isDropdownOpen = true;

            // Temporarily show options with emojis
            Array.from(this.options).forEach(option => {
                if (option.value && originalOptions[option.value]) {
                    option.text = originalOptions[option.value];
                }
            });
        });

        // When selection changes or dropdown closes
        select.addEventListener('change', function() {
            isDropdownOpen = false;

            // Revert to text without emojis
            Array.from(this.options).forEach(option => {
                if (option.value && option.hasAttribute('data-display')) {
                    option.text = option.getAttribute('data-display');
                }
            });
        });

        // Handle cases where user clicks away without selecting
        document.addEventListener('click', function(e) {
            if (isDropdownOpen && e.target !== select) {
                isDropdownOpen = false;

                Array.from(select.options).forEach(option => {
                    if (option.value && option.hasAttribute('data-display')) {
                        option.text = option.getAttribute('data-display');
                    }
                });
            }
        });

        // Initialize with correct display
        Array.from(select.options).forEach(option => {
            if (option.value && option.hasAttribute('data-display')) {
                option.text = option.getAttribute('data-display');
            }
        });
    });
</script> --}}

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

        // Function to toggle "auction time" input field
        function toggleAuctionTime1(selectElement1) {
            const auctionTimeDiv1 = document.querySelector('.auction_time1');

            if (!auctionTimeDiv1) {
                return;
            }

            if (selectElement1.value === 'Bidding Period') {
                auctionTimeDiv1.classList.remove('d-none');
            } else {
                auctionTimeDiv1.classList.add('d-none');
            }
        }

        // Function to attach the event listener to the auction type dropdown
        function attachAuctionDropdownListener1() {
            const auctionDropdown1 = document.getElementById('auction_type1');
            if (auctionDropdown1) {
                auctionDropdown1.addEventListener('change', function() {
                    toggleAuctionTime1(this);
                });
                toggleAuctionTime1(auctionDropdown1);
            }
        }

        // Attach the event listener initially
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', attachAuctionDropdownListener1);
        } else {
            attachAuctionDropdownListener1();
        }

        // Re-attach the event listener after Livewire re-renders the DOM
        document.addEventListener('livewire:load', function() {
            if (window.Livewire) {
                Livewire.hook('message.processed', attachAuctionDropdownListener1);
            }
        });
    })();
</script>
