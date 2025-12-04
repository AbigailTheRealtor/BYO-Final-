<!-- Main Title Card -->
<div class="card bg-info mb-4">
    <div class="card-body">
        <h4 class="card-title text-white">Select Services & Pricing</h4>
        <p class="card-text bg-info text-white">
            Select the services you would like to hire the Broker for. For each selected service,
            you can enter a custom flat fee amount. If a service requires additional payment for
            marketing materials, a separate 'Marketing Materials Fee' will be listed.
        </p>
    </div>
</div>

<!-- Marketing Services Section -->
<div class="card mb-4">
    <div class="card-header bg-info">
        <h5 class="mb-0 text-white">📢 Marketing the Tenant's Rental Criteria</h5>
    </div>
    <div class="card-body">
        <!-- List Criteria -->
        <div class="form-group">
            <label>
                List the Tenant's rental criteria on BidYourOffer.com
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="list_criteria_fee" id="listCriteriaFee" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee amount">
            </div>
            @error('list_criteria_fee')
                <span class="error mt-2">{{ $message }}</span>
            @enderror
        </div>

        <!-- Market Groups -->
        <div class="form-group mt-3">
            <label>
                Market the Tenant's rental criteria across real estate groups & affiliates
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="market_groups_fee" id="marketGroupsFee" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee amount">
            </div>
            @error('market_groups_fee')
                <span class="error mt-2">{{ $message }}</span>
            @enderror
        </div>

        <!-- Social Media Promotion -->
        <div class="form-group mt-3">
            <label>
                Promote the Tenant's rental criteria on social media
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="promote_social_fee" id="promoteSocialFee"
                    class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee amount">
            </div>
            @error('promote_social_fee')
                <span class="error mt-2">{{ $message }}</span>
            @enderror
        </div>

        <!-- Paid Advertising -->
        <div class="form-group mt-3">
            <label>
                Launch a targeted paid online advertising campaign (Facebook Ads, etc.)
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="launch_ads_fee" id="launchAdsFee" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee amount">
            </div>
            @error('launch_ads_fee')
                <span class="error mt-2">{{ $message }}</span>
            @enderror

            <!-- Marketing Materials Fee -->
            <label class="mt-3 d-block">
                Marketing Materials Fee (Total Allowance for Ads)
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="marketing_materials_fee" id="marketingMaterialsFee"
                    class="form-control has-icon" data-icon="fa-solid fa-receipt"
                    placeholder="Marketing materials amount">
            </div>
            @error('marketing_materials_fee')
                <span class="error mt-2">{{ $message }}</span>
            @enderror
        </div>

        <!-- Email Marketing -->
        <div class="form-group mt-3">
            <label>
                Conduct email marketing targeting agents and Landlords
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="email_marketing_fee" id="emailMarketingFee"
                    class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee amount">
            </div>
            @error('email_marketing_fee')
                <span class="error mt-2">{{ $message }}</span>
            @enderror
        </div>

        <!-- Neighborhood Marketing -->
        <div class="form-group mt-3">
            <label>
                Implement neighborhood marketing in the desired area (e.g., direct mailers, online community posts)
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="neighborhood_marketing_fee" id="neighborhoodMarketingFee"
                    class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee amount">
            </div>
            @error('neighborhood_marketing_fee')
                <span class="error mt-2">{{ $message }}</span>
            @enderror

            <!-- Marketing Materials Fee -->
            <label class="mt-3 d-block">
                Marketing Materials Fee (Total Allowance for Mailers, Postage, Promotional Materials, etc.)
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="neighborhood_materials_fee" id="neighborhoodMaterialsFee"
                    class="form-control has-icon" data-icon="fa-solid fa-receipt"
                    placeholder="Marketing materials amount">
            </div>
            @error('neighborhood_materials_fee')
                <span class="error mt-2">{{ $message }}</span>
            @enderror
        </div>
    </div>
</div>

<!-- Search, Alerts & Property Matching Section -->
<div class="card mb-4">
    <div class="card-header bg-info">
        <h5 class="mb-0 text-white">🔍 Search, Alerts & Property Matching</h5>
    </div>
    <div class="card-body">
        <!-- Email Notifications -->
        <div class="form-group">
            <label>
                Send email notifications of new matching properties
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="email_notifications_fee" id="emailNotificationsFee"
                    class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee amount">
            </div>
            @error('email_notifications_fee')
                <span class="error mt-2">{{ $message }}</span>
            @enderror
        </div>

        <!-- Off-Market Search -->
        <div class="form-group mt-3">
            <label>
                Search for off-market & pre-market rental opportunities
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="off_market_search_fee" id="offMarketSearchFee"
                    class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee amount">
            </div>
            @error('off_market_search_fee')
                <span class="error mt-2">{{ $message }}</span>
            @enderror
        </div>

        <!-- MLS Listings -->
        <div class="form-group mt-3">
            <label>
                Filter and analyze listings from MLS & agent-exclusive platforms
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="mls_filter_fee" id="mlsFilterFee" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee amount">
            </div>
            @error('mls_filter_fee')
                <span class="error mt-2">{{ $message }}</span>
            @enderror
        </div>
    </div>
</div>
<!-- Property Showings Section -->
<div class="card mb-4">
    <div class="card-header bg-info">
        <h5 class="mb-0  text-white">🏘 Property Showings</h5>
    </div>
    <div class="card-body">
        <!-- Schedule Showings -->
        <div class="form-group">
            <label>
                Schedule showing appointment(s)
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="number_of_showings_to_schedule" id="numberOfShowingsToSchedule"
                    class="form-control has-icon" data-icon="fa-solid fa-hashtag" placeholder="Number of showings">
            </div>
            <div class="input-cover mt-2">
                <input type="number" wire:model="schedule_showings_fee" id="scheduleShowingsFee"
                    class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee amount">
            </div>
            @error('schedule_showings_fee')
                <span class="error mt-2">{{ $message }}</span>
            @enderror
        </div>

        <!-- Attend Showings -->
        <div class="form-group mt-3">
            <label>
                Attend showing(s) with the Tenant
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="number_of_showings_to_attend" id="numberOfShowingsToAttend"
                    class="form-control has-icon" data-icon="fa-solid fa-hashtag" placeholder="Number of showings">
            </div>
            <div class="input-cover mt-2">
                <input type="number" wire:model="attend_showings_fee" id="attendShowingsFee"
                    class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee amount">
            </div>
            @error('attend_showings_fee')
                <span class="error mt-2">{{ $message }}</span>
            @enderror
        </div>

        <!-- Virtual Tours -->
        <div class="form-group mt-3">
            <label>
                Provide video or virtual tour(s) of a selected property
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="number_of_virtual_tours" id="numberOfVirtualTours"
                    class="form-control has-icon" data-icon="fa-solid fa-hashtag"
                    placeholder="Number of virtual tours">
            </div>
            <div class="input-cover mt-2">
                <input type="number" wire:model="virtual_tours_fee" id="virtualToursFee"
                    class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee amount">
            </div>
            @error('virtual_tours_fee')
                <span class="error mt-2">{{ $message }}</span>
            @enderror
        </div>
    </div>
</div>

<!-- Application & Lease Support Section -->
<div class="card mb-4">
    <div class="card-header bg-info">
        <h5 class="mb-0  text-white">⚖️ Application & Lease Support</h5>
    </div>
    <div class="card-body">
        <!-- Prepare Application -->
        <div class="form-group">
            <label class="form-check-label">
                Assist with preparing one rental application
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="prepare_application_fee" id="prepareApplicationFee"
                       class="form-control has-icon" data-icon="fa-solid fa-file-pen"
                       placeholder="Flat fee amount">
            </div>
            @error('prepare_application_fee') <span class="error mt-2">{{ $message }}</span> @enderror
        </div>

        <!-- Collect Documents -->
        <div class="form-group mt-3">
            <label class="form-check-label">
                Collect & organize supporting documents
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="collect_documents_fee" id="collectDocumentsFee"
                       class="form-control has-icon" data-icon="fa-solid fa-folder-open"
                       placeholder="Flat fee amount">
            </div>
            @error('collect_documents_fee') <span class="error mt-2">{{ $message }}</span> @enderror
        </div>

        <!-- Submit Application -->
        <div class="form-group mt-3">
            <label class="form-check-label">
                Submit one application & follow up
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="submit_application_fee" id="submitApplicationFee"
                       class="form-control has-icon" data-icon="fa-solid fa-paper-plane"
                       placeholder="Flat fee amount">
            </div>
            @error('submit_application_fee') <span class="error mt-2">{{ $message }}</span> @enderror
        </div>

        <!-- Review Lease -->
        <div class="form-group mt-3">
            <label class="form-check-label">
                Review one lease document (non-legal explanation)
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="review_lease_fee" id="reviewLeaseFee"
                       class="form-control has-icon" data-icon="fa-solid fa-file-contract"
                       placeholder="Flat fee amount">
            </div>
            @error('review_lease_fee') <span class="error mt-2">{{ $message }}</span> @enderror
        </div>

        <!-- Provide Lease Form -->
        <div class="form-group mt-3">
            <label class="form-check-label">
                Provide a standard lease form (where permitted)
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="provide_lease_form_fee" id="provideLeaseFormFee"
                       class="form-control has-icon" data-icon="fa-solid fa-file-signature"
                       placeholder="Flat fee amount">
            </div>
            @error('provide_lease_form_fee') <span class="error mt-2">{{ $message }}</span> @enderror
        </div>

        <!-- Coordinate Signing -->
        <div class="form-group mt-3">
            <label class="form-check-label">
                Coordinate one lease signing
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="coordinate_signing_fee" id="coordinateSigningFee"
                       class="form-control has-icon" data-icon="fa-solid fa-handshake"
                       placeholder="Flat fee amount">
            </div>
            @error('coordinate_signing_fee') <span class="error mt-2">{{ $message }}</span> @enderror
        </div>

        <!-- Legal Notice -->
        <div class="alert alert-warning mt-3 p-2">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <span class="small">Note: Lease negotiation requires a formal agency relationship.</span>
        </div>
    </div>
</div>

<!-- Move-In & Transition Support Section -->
<div class="card mb-4">
    <div class="card-header bg-info">
        <h5 class="mb-0  text-white">🚚 Move-In & Transition Support</h5>
    </div>
    <div class="card-body">
        <!-- Move-In Inspection -->
        <div class="form-group">
            <label>
                Coordinate or confirm move-in inspection & key handover
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="move_in_inspection_fee" id="moveInInspectionFee"
                       class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                       placeholder="Flat fee amount">
            </div>
            @error('move_in_inspection_fee') <span class="error mt-2">{{ $message }}</span> @enderror
        </div>

        <!-- Moving Resources -->
        <div class="form-group mt-3">
            <label>
                Provide moving company & utility setup resources
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="moving_resources_fee" id="movingResourcesFee"
                       class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                       placeholder="Flat fee amount">
            </div>
            @error('moving_resources_fee') <span class="error mt-2">{{ $message }}</span> @enderror
        </div>

        <!-- Short-Term Housing -->
        <div class="form-group mt-3">
            <label>
                Assist in locating short-term housing or relocation options
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="short_term_housing_fee" id="shortTermHousingFee"
                       class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                       placeholder="Flat fee amount">
            </div>
            @error('short_term_housing_fee') <span class="error mt-2">{{ $message }}</span> @enderror
        </div>
    </div>
</div>


<!-- Advisory Services Section -->
<div class="card mb-4">
    <div class="card-header bg-info ">
        <h5 class="mb-0 text-white">🧭 Advisory Services</h5>
    </div>
    <div class="card-body">
        <!-- Rental Rights Summary -->
        <div class="form-group">
            <label class="form-check-label">
                Provide a written rental rights summary (state-specific, non-legal)
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="rental_rights_fee" id="rentalRightsFee"
                       class="form-control has-icon" data-icon="fa-solid fa-scale-balanced"
                       placeholder="Flat fee amount">
            </div>
            @error('rental_rights_fee') <span class="error mt-2">{{ $message }}</span> @enderror
        </div>

        <!-- Lease Signing Advice -->
        <div class="form-group mt-3">
            <label class="form-check-label">
                Offer advice on lease signing & move-in prep (e.g., checklists)
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="lease_advice_fee" id="leaseAdviceFee"
                       class="form-control has-icon" data-icon="fa-solid fa-file-circle-check"
                       placeholder="Flat fee amount">
            </div>
            @error('lease_advice_fee') <span class="error mt-2">{{ $message }}</span> @enderror
        </div>

        <!-- Neighborhood Insights -->
        <div class="form-group mt-3">
            <label class="form-check-label">
                Share general insights about neighborhoods, schools, commute, etc.
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="neighborhood_insights_fee" id="neighborhoodInsightsFee"
                       class="form-control has-icon" data-icon="fa-solid fa-location-dot"
                       placeholder="Flat fee amount">
            </div>
            @error('neighborhood_insights_fee') <span class="error mt-2">{{ $message }}</span> @enderror
        </div>

        <!-- Legal Notice -->
        <div class="alert alert-warning mt-3 p-2">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <span class="small">Note: These insights are informational and not legal or financial advice.</span>
        </div>
    </div>
</div>

<!-- Custom or Additional Services Section -->
<div class="card mb-4">
    <div class="card-header bg-info">
        <h5 class="mb-0 text-white">✍️ Custom or Additional Services</h5>
    </div>
    <div class="card-body">
        @foreach($custom_services as $index => $service)
        <div class="form-group" wire:key="service-{{ $index }}">
            <!-- Service Fee -->
            <label class="form-check-label">
                Additional Service {{ $index + 1 }}
            </label>
            <div class="input-cover mt-2">
                <input type="number" wire:model="custom_services.{{ $index }}.fee"
                       class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                       placeholder="Flat fee amount">
            </div>

            <!-- Service Description -->
            <div class="input-cover mt-2">
                <input type="text" wire:model="custom_services.{{ $index }}.description"
                       class="form-control has-icon" data-icon="fa-solid fa-pen-to-square"
                       placeholder="Describe the service">
            </div>

            <!-- Marketing Materials -->
            <div class="mt-3">
                <label class="form-check-label">
                    Marketing Materials Fee
                </label>
                <div class="input-cover mt-2">
                    <input type="number" wire:model="custom_services.{{ $index }}.marketing_fee"
                           class="form-control has-icon" data-icon="fa-solid fa-receipt"
                           placeholder="Marketing materials amount">
                </div>
                <div class="input-cover mt-2">
                    <input type="text" wire:model="custom_services.{{ $index }}.marketing_description"
                           class="form-control has-icon" data-icon="fa-solid fa-list-check"
                           placeholder="Describe marketing materials needed">
                </div>
            </div>

            <!-- Remove Button -->
            @if($index > 0)
            <button type="button" class="btn btn-sm btn-danger mt-2"
                    wire:click="removeService({{ $index }})">
                <i class="fas fa-trash me-1"></i> Remove Service
            </button>
            @endif
        </div>
        @endforeach

        <!-- Add Service Button -->
        <button type="button" class="btn btn-sm btn-primary mt-3"
                wire:click="addService">
            <i class="fas fa-plus me-1"></i> Add Another Service
        </button>

        <!-- Totals Section -->
        <div class="border-top mt-4 pt-3">
            <div class="form-group">
                <label class="fw-bold">
                    Flat Fee Amount (Total):
                    <i class="fas fa-comment ms-2 text-primary"
                       data-bs-toggle="tooltip"
                       title="Enter the total flat fee amount you agree to pay the Broker for the selected service(s)"></i>
                </label>
                <div class="input-cover mt-2">
                    <input type="number" wire:model="total_flat_fee"
                           class="form-control has-icon" data-icon="fa-solid fa-calculator"
                           placeholder="Total" readonly>
                </div>
            </div>

            <div class="form-group mt-3">
                <label class="fw-bold">
                    Marketing Materials Fee (Total):
                    <i class="fas fa-comment ms-2 text-primary"
                       data-bs-toggle="tooltip"
                       title="Enter the total marketing materials fee amount you agree to pay the Broker for the selected service(s). This fee is for additional materials such as paid ads, mailers, postage, promotional materials, etc."></i>
                </label>
                <div class="input-cover mt-2">
                    <input type="number" wire:model="total_marketing_fee"
                           class="form-control has-icon" data-icon="fa-solid fa-calculator"
                           placeholder="Total" readonly>
                </div>
            </div>
        </div>
    </div>
</div>
