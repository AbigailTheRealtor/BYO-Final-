<div class="card bg-info mb-4">
    <div class="card-body">
        <h4 class="card-title text-white">Select Services & Pricing</h4>
        <p class="card-text text-white">
            Select the services you'd like to hire the Agent for. You can either enter a flat fee for each individual
            service,
            or enter a single total flat fee at the bottom to cover all selected services. Each amount should reflect
            the full
            compensation you're offering for the Agent's time, expertise, and any associated marketing-related expenses.
        </p>
        <div class="alert alert-warning mt-3">
            <i class="fa-solid fa-exclamation-triangle me-2"></i>
            <strong>Important Service Disclaimer:</strong> These services are offered under a limited flat-fee
            arrangement and do not create a
            brokerage or agency relationship unless otherwise agreed to in writing. Agents may provide administrative or
            informational support only and may not negotiate, screen applicants, or offer legal, financial, or fiduciary
            advice without a signed agency agreement.
        </div>
        <div class="mt-3">
            <a href="#" class="text-white d-flex align-items-center">
                If you prefer full representation, you may choose to Hire a
                Full-Service Agent
            </a>
        </div>
    </div>
</div>

@if ($user_type === 'tenant')
<div class="services-container">
    <!-- Tenant Criteria Marketing & Promotion -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">📢 Tenant Criteria Marketing & Promotion</h5>
        </div>
        <div class="card-body">
            <!-- List Criteria -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.list_criteria" id="listCriteria">
                <label class="form-check-label" for="listCriteria">
                    List the tenant's rental criteria on BidYourOffer.com
                </label>
                @if($enable['list_criteria'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.list_criteria" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.list_criteria') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Social Media Promotion -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.social_media" id="socialMedia">
                <label class="form-check-label" for="socialMedia">
                    Promote the tenant's rental criteria on social media platforms — including real estate groups,
                    pages, and affiliate networks — with a direct link to the listing on BidYourOffer.com
                </label>
                @if($enable['social_media'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.social_media" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.social_media') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Paid Advertising -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.paid_ads" id="paidAds">
                <label class="form-check-label" for="paidAds">
                    Launch a targeted paid advertising campaign — including Facebook and Instagram — with a direct link
                    to the listing on BidYourOffer.com
                </label>
                @if($enable['paid_ads'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.paid_ads" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.paid_ads') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Email Marketing -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.email_marketing" id="emailMarketing">
                <label class="form-check-label" for="emailMarketing">
                    Create and launch a mass email marketing campaign with a direct link to the listing on
                    BidYourOffer.com
                </label>
                @if($enable['email_marketing'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.email_marketing" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.email_marketing') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Local Mailers -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.local_mailers" id="localMailers">
                <label class="form-check-label" for="localMailers">
                    Distribute local mailers in the tenant's preferred neighborhood, featuring a QR code that links
                    directly to the listing on BidYourOffer.com
                </label>
                @if($enable['local_mailers'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.local_mailers" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.local_mailers') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Hyperlocal Ads -->
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="enable.hyperlocal_ads" id="hyperlocalAds">
                <label class="form-check-label" for="hyperlocalAds">
                    Launch hyperlocal digital ads targeting the tenant's preferred neighborhood, with a direct link to
                    the listing on BidYourOffer.com
                </label>
                @if($enable['hyperlocal_ads'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.hyperlocal_ads" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.hyperlocal_ads') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Property Search, Alerts & Matching -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">🔍 Property Search, Alerts & Matching</h5>
        </div>
        <div class="card-body">
            <!-- Email Alerts -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.email_alerts" id="emailAlerts">
                <label class="form-check-label" for="emailAlerts">
                    Send email alerts with new listings from the local Multiple Listing Service (MLS) that match the
                    tenant's rental criteria, providing timely access to current market opportunities
                </label>
                @if($enable['email_alerts'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.email_alerts" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.email_alerts') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Off-Market Search -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.off_market" id="offMarket">
                <label class="form-check-label" for="offMarket">
                    Search for off-market and pre-market rental properties based on the tenant's rental criteria
                </label>
                @if($enable['off_market'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.off_market" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.off_market') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Filter Listings -->
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="enable.filter_listings" id="filterListings">
                <label class="form-check-label" for="filterListings">
                    Filter and analyze active listings from the MLS, public websites, and agent-exclusive platforms
                </label>
                @if($enable['filter_listings'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.filter_listings" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.filter_listings') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Property Showings & Virtual Tours -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">🏘 Property Showings & Virtual Tours</h5>
        </div>
        <div class="card-body">
            <!-- Schedule Showings -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.schedule_showings" id="scheduleShowings">
                <label class="form-check-label" for="scheduleShowings">
                    Schedule showing appointment(s)
                </label>
                @if($enable['schedule_showings'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="showings_count" class="form-control has-icon"
                        data-icon="fa-solid fa-hashtag" placeholder="Number of showings to schedule">
                    @error('showings_count') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.schedule_showings" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.schedule_showings') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Attend Showings -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.attend_showings" id="attendShowings">
                <label class="form-check-label" for="attendShowings">
                    Attend showing(s) with the tenant
                </label>
                @if($enable['attend_showings'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="attend_showings_count" class="form-control has-icon"
                        data-icon="fa-solid fa-hashtag" placeholder="Number of showings to attend">
                    @error('attend_showings_count') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.attend_showings" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.attend_showings') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Virtual Tours -->
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="enable.virtual_tours" id="virtualTours">
                <label class="form-check-label" for="virtualTours">
                    Provide video or virtual tours of selected properties
                </label>
                @if($enable['virtual_tours'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="virtual_tours_count" class="form-control has-icon"
                        data-icon="fa-solid fa-hashtag" placeholder="Number of virtual tours">
                    @error('virtual_tours_count') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.virtual_tours" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.virtual_tours') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Application Support -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">📝 Application Support</h5>
        </div>
        <div class="card-body">
            <!-- Prepare Application -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.prepare_application" id="prepareApp">
                <label class="form-check-label" for="prepareApp">
                    Assist with preparing one rental application (administrative support only)
                </label>
                @if($enable['prepare_application'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.prepare_application" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.prepare_application') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Submit Documents -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.submit_documents" id="submitDocs">
                <label class="form-check-label" for="submitDocs">
                    Collect, organize, and submit supporting documents to the listing agent or landlord for the rental
                    application (administrative delivery only; no screening or evaluation)
                </label>
                @if($enable['submit_documents'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.submit_documents" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.submit_documents') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Follow Up -->
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="enable.follow_up" id="followUp">
                <label class="form-check-label" for="followUp">
                    Follow up with the landlord or listing agent regarding the status of any submitted rental
                    application (informational only; no negotiation or representation involved)
                </label>
                @if($enable['follow_up'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.follow_up" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.follow_up') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Lease Support -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">📜 Lease Support</h5>
        </div>
        <div class="card-body">
            <!-- State Lease Form -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.state_lease" id="stateLease">
                <label class="form-check-label" for="stateLease">
                    Provide a state-specific lease form (where permitted by law)
                </label>
                @if($enable['state_lease'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.state_lease" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.state_lease') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Lease Overview -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.lease_overview" id="leaseOverview">
                <label class="form-check-label" for="leaseOverview">
                    Provide a general overview of one lease document (non-legal explanation only)
                </label>
                @if($enable['lease_overview'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.lease_overview" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.lease_overview') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Lease Disclosures -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.lease_disclosures" id="leaseDisclosures">
                <label class="form-check-label" for="leaseDisclosures">
                    Assist with completion and submission of required state-specific lease disclosures (non-legal,
                    administrative guidance only)
                </label>
                @if($enable['lease_disclosures'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.lease_disclosures" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.lease_disclosures') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Lease Signing -->
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="enable.lease_signing" id="leaseSigning">
                <label class="form-check-label" for="leaseSigning">
                    Coordinate lease signing, including scheduling, e-signature setup, and secure document delivery
                    (administrative only; no legal review or negotiation)
                </label>
                @if($enable['lease_signing'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.lease_signing" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.lease_signing') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Move-In Coordination -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">🚚 Move-In Coordination</h5>
        </div>
        <div class="card-body">
            <!-- Key Handover -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.key_handover" id="keyHandover">
                <label class="form-check-label" for="keyHandover">
                    Coordinate exchange of funds and key handover (administrative support only; no financial handling)
                </label>
                @if($enable['key_handover'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.key_handover" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.key_handover') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Utility Setup -->
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="enable.utility_setup" id="utilitySetup">
                <label class="form-check-label" for="utilitySetup">
                    Provide utility setup instructions (e.g., water, electricity, internet)
                </label>
                @if($enable['utility_setup'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.utility_setup" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.utility_setup') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Rental Strategy & Advisory Support -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">📊 Rental Strategy & Advisory Support (Non-Agency Services)</h5>
        </div>
        <div class="card-body">
            <!-- Rental Market Analysis -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.rental_analysis" id="rentalAnalysis">
                <label class="form-check-label" for="rentalAnalysis">
                    Conduct a Rental Market Analysis (RMA) to help assess rental pricing and area trends (non-binding
                    guidance only)
                </label>
                @if($enable['rental_analysis'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.rental_analysis" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.rental_analysis') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Rental Laws -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.rental_laws" id="rentalLaws">
                <label class="form-check-label" for="rentalLaws">
                    Provide a written summary of local rental laws (state-specific, non-legal guidance only)
                </label>
                @if($enable['rental_laws'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.rental_laws" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.rental_laws') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Lease Options -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.lease_options" id="leaseOptions">
                <label class="form-check-label" for="leaseOptions">
                    Help compare lease options (e.g., traditional lease, lease-option, short-term, furnished rentals)
                </label>
                @if($enable['lease_options'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.lease_options" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.lease_options') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Short-Term Housing -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.short_term_housing" id="shortTermHousing">
                <label class="form-check-label" for="shortTermHousing">
                    Assist in locating short-term housing or relocation options (informational support only; no booking
                    or negotiation)
                </label>
                @if($enable['short_term_housing'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.short_term_housing" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.short_term_housing') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- General Guidance -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.general_guidance" id="generalGuidance">
                <label class="form-check-label" for="generalGuidance">
                    Answer common questions and provide general guidance (non-agency support only)
                </label>
                @if($enable['general_guidance'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.general_guidance" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.general_guidance') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Move-In Costs -->
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="enable.move_in_costs" id="moveInCosts">
                <label class="form-check-label" for="moveInCosts">
                    Provide a summary of typical move-in costs and income-to-rent ratio expectations based on standard
                    lease terms and current market trends (non-binding, informational only)
                </label>
                @if($enable['move_in_costs'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.move_in_costs" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.move_in_costs') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
        </div>
    </div>

    <!--  Additional Services Section -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">✍️ Additional Services:</h5>
        </div>
        <div class="card-body">
            @foreach ($custom_services as $index => $service)
                <div class="form-group" wire:key="service-{{ $index }}">
                    <!-- Service Description -->
                    <div class="input-cover mt-2">
                        <input type="text" wire:model="custom_services.{{ $index }}.description" 
                            class="form-control has-icon" data-icon="fa-solid fa-pen-to-square"
                            placeholder="Describe the service">
                        @error("custom_services.{$index}.description") 
                            <span class="text-danger">{{ $message }}</span> 
                        @enderror
                    </div>

                    <!-- Service Fee -->
                    <div class="input-cover mt-2">
                        <input type="number" wire:model="custom_services.{{ $index }}.fee" 
                            class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                            placeholder="Flat Fee for this service">
                        @error("custom_services.{$index}.fee") 
                            <span class="text-danger">{{ $message }}</span> 
                        @enderror
                    </div>

                    <!-- Remove Button -->
                    @if ($index > 0)
                        <button type="button" class="btn btn-sm btn-danger mt-2"
                            wire:click="removeService({{ $index }})">
                            <i class="fa-solid fa-trash me-1"></i> Remove Service
                        </button>
                    @endif
                </div>
            @endforeach

            <!-- Add Service Button -->
            <button type="button" class="btn btn-sm btn-primary mt-3" wire:click="addService">
                <i class="fa-solid fa-plus me-1"></i> Add Another Service
            </button>

            <!-- Totals Section -->
            <div class="border-top mt-4 pt-3">
                <div class="form-group">
                    <label class="fw-bold">
                        Flat Fee Amount (Total):
                        <i class="fa-solid fa-comment-dots text-primary" data-bs-toggle="tooltip"
                            title="This total auto-calculates as you enter amounts for individual services. You may
also manually enter one total flat fee to cover all selected services instead."></i>
                    </label>
                    <div class="input-cover mt-2">
                        <input type="number" wire:model="total_flat_fee" class="form-control has-icon"
                            data-icon="fa-solid fa-dollar-sign" placeholder="Total" readonly>
                    </div>
                </div>

                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" wire:model="understand_terms"
                        id="understandTerms" @error('understand_terms') is-invalid @enderror>
                    <label class="form-check-label" for="understandTerms">
                        I understand that the selected services are administrative or informational in nature and do not
                        include legal advice,
                        lease negotiation, or representation unless a signed agency agreement is in place.
                    </label>
                    @error('understand_terms')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

          
            </div>
        </div>
    </div>
</div>
@elseif($user_type === 'buyer')
<div class="services-container">
    <!-- Buyer Criteria Marketing & Promotion -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">📢 Buyer Criteria Marketing & Promotion</h5>
        </div>
        <div class="card-body">
            <!-- List criteria on BidYourOffer -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.list_criteria" id="listCriteria">
                <label class="form-check-label" for="listCriteria">
                    List the buyer's purchase criteria on BidYourOffer.com
                </label>
                @if($enable['list_criteria'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.list_criteria" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.list_criteria') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Social media promotion -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.social_media" id="socialMedia">
                <label class="form-check-label" for="socialMedia">
                    Promote the buyer's purchase criteria on social media platforms — including real estate groups, pages, and affiliate networks — with a direct link to the listing on BidYourOffer.com
                </label>
                @if($enable['social_media'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.social_media" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.social_media') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Paid advertising -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.paid_ads" id="paidAds">
                <label class="form-check-label" for="paidAds">
                    Launch a targeted paid advertising campaign — including Facebook and Instagram — with a direct link to the listing on BidYourOffer.com
                </label>
                @if($enable['paid_ads'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.paid_ads" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.paid_ads') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Email marketing -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.email_marketing" id="emailMarketing">
                <label class="form-check-label" for="emailMarketing">
                    Create and launch a mass email marketing campaign with a direct link to the listing on BidYourOffer.com
                </label>
                @if($enable['email_marketing'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.email_marketing" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.email_marketing') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Local mailers -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.local_mailers" id="localMailers">
                <label class="form-check-label" for="localMailers">
                    Distribute local mailers in the buyer's preferred neighborhood, featuring a QR code that links directly to the listing on BidYourOffer.com
                </label>
                @if($enable['local_mailers'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.local_mailers" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.local_mailers') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Hyperlocal ads -->
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="enable.hyperlocal_ads" id="hyperlocalAds">
                <label class="form-check-label" for="hyperlocalAds">
                    Launch hyperlocal digital ads targeting the buyer's preferred neighborhood, with a direct link to the listing on BidYourOffer.com
                </label>
                @if($enable['hyperlocal_ads'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.hyperlocal_ads" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.hyperlocal_ads') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Property Search, Alerts & Matching -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">🔍 Property Search, Alerts & Matching</h5>
        </div>
        <div class="card-body">
            <!-- Email alerts -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.email_alerts" id="emailAlerts">
                <label class="form-check-label" for="emailAlerts">
                    Send email alerts with new listings from the local Multiple Listing Service (MLS) that match the buyer's purchase criteria, providing timely access to current market opportunities
                </label>
                @if($enable['email_alerts'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.email_alerts" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.email_alerts') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Off-market search -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.off_market" id="offMarket">
                <label class="form-check-label" for="offMarket">
                    Search for off-market and pre-market properties based on the buyer's purchase criteria
                </label>
                @if($enable['off_market'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.off_market" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.off_market') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Filter listings -->
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="enable.filter_listings" id="filterListings">
                <label class="form-check-label" for="filterListings">
                    Filter and analyze active listings from the MLS, public websites, and agent-exclusive platforms
                </label>
                @if($enable['filter_listings'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.filter_listings" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.filter_listings') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Property Showings & Virtual Tours -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">🏘 Property Showings & Virtual Tours</h5>
        </div>
        <div class="card-body">
            <!-- Schedule showings -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.schedule_showings" id="scheduleShowings">
                <label class="form-check-label" for="scheduleShowings">
                    Schedule showing appointment(s)
                </label>
                @if($enable['schedule_showings'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="showings_count" class="form-control has-icon"
                        data-icon="fa-solid fa-hashtag" placeholder="Number of showings to schedule">
                    @error('showings_count') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.schedule_showings" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.schedule_showings') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Attend showings -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.attend_showings" id="attendShowings">
                <label class="form-check-label" for="attendShowings">
                    Attend showing(s) with the buyer
                </label>
                @if($enable['attend_showings'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="attend_showings_count" class="form-control has-icon"
                        data-icon="fa-solid fa-hashtag" placeholder="Number of showings to attend">
                    @error('attend_showings_count') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.attend_showings" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.attend_showings') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Virtual tours -->
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="enable.virtual_tours" id="virtualTours">
                <label class="form-check-label" for="virtualTours">
                    Provide video or virtual tours of selected properties
                </label>
                @if($enable['virtual_tours'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="virtual_tours_count" class="form-control has-icon"
                        data-icon="fa-solid fa-hashtag" placeholder="Number of virtual tours">
                    @error('virtual_tours_count') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.virtual_tours" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.virtual_tours') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Offer & Contract Support -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">📝 Offer & Contract Support</h5>
        </div>
        <div class="card-body">
            <!-- Prepare offer -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.prepare_offer" id="prepareOffer">
                <label class="form-check-label" for="prepareOffer">
                    Assist with preparing and submitting one offer using a state-approved purchase contract (non-legal, non-representative guidance only)
                </label>
                @if($enable['prepare_offer'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.prepare_offer" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.prepare_offer') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Complete disclosures -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.complete_disclosures" id="completeDisclosures">
                <label class="form-check-label" for="completeDisclosures">
                    Assist with the completion and submission of required state-specific disclosure documents (non-legal, administrative guidance only)
                </label>
                @if($enable['complete_disclosures'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.complete_disclosures" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.complete_disclosures') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Follow up on offer -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.follow_up_offer" id="followUpOffer">
                <label class="form-check-label" for="followUpOffer">
                    Follow up with the listing agent or seller regarding the status of a submitted offer (informational follow-up only — no negotiation)
                </label>
                @if($enable['follow_up_offer'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.follow_up_offer" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.follow_up_offer') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Overview of agreement -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.overview_agreement" id="overviewAgreement">
                <label class="form-check-label" for="overviewAgreement">
                    Provide a general overview of the purchase agreement terms and contingencies (non-legal explanation only)
                </label>
                @if($enable['overview_agreement'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.overview_agreement" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.overview_agreement') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Coordinate counteroffer -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.coordinate_counteroffer" id="coordinateCounteroffer">
                <label class="form-check-label" for="coordinateCounteroffer">
                    Coordinate delivery of counteroffer or response documents and confirm receipt with all applicable parties (administrative handling only)
                </label>
                @if($enable['coordinate_counteroffer'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.coordinate_counteroffer" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.coordinate_counteroffer') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- E-signature setup -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.esignature_setup" id="esignatureSetup">
                <label class="form-check-label" for="esignatureSetup">
                    Coordinate electronic signature setup, secure document delivery, and confirmation of receipt with all applicable parties (administrative handling only; no legal review or negotiation)
                </label>
                @if($enable['esignature_setup'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.esignature_setup" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.esignature_setup') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Track deadlines -->
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="enable.track_deadlines" id="trackDeadlines">
                <label class="form-check-label" for="trackDeadlines">
                    Track contract deadlines and contingency periods, and provide a checklist of key documents (non-legal, informational only)
                </label>
                @if($enable['track_deadlines'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.track_deadlines" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.track_deadlines') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Closing Coordination & Transition Support -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">🏁 Closing Coordination & Transition Support</h5>
        </div>
        <div class="card-body">
            <!-- Contract-to-close coordination -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.contract_to_close" id="contractToClose">
                <label class="form-check-label" for="contractToClose">
                    Manage contract-to-close coordination (includes scheduling inspections, appraisal, and key milestones)
                </label>
                @if($enable['contract_to_close'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.contract_to_close" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.contract_to_close') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Vendor referrals -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.vendor_referrals" id="vendorReferrals">
                <label class="form-check-label" for="vendorReferrals">
                    Provide referrals to inspectors, lenders, title/escrow companies, or related vendors (referrals only; no endorsements or guarantees)
                </label>
                @if($enable['vendor_referrals'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.vendor_referrals" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.vendor_referrals') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Escrow coordination -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.escrow_coordination" id="escrowCoordination">
                <label class="form-check-label" for="escrowCoordination">
                    Coordinate communication with escrow/title company regarding delivery of final documents and funding updates (administrative only; no financial handling)
                </label>
                @if($enable['escrow_coordination'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.escrow_coordination" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.escrow_coordination') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Final walkthrough -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.final_walkthrough" id="finalWalkthrough">
                <label class="form-check-label" for="finalWalkthrough">
                    Coordinate final walk-through appointment with the listing agent or seller (scheduling only)
                </label>
                @if($enable['final_walkthrough'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.final_walkthrough" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.final_walkthrough') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Closing appointment -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.closing_appointment" id="closingAppointment">
                <label class="form-check-label" for="closingAppointment">
                    Coordinate closing appointment with title company or attorney's office (scheduling and administrative support only)
                </label>
                @if($enable['closing_appointment'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.closing_appointment" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.closing_appointment') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Key handover -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.key_handover" id="keyHandover">
                <label class="form-check-label" for="keyHandover">
                    Confirm post-closing instructions and key handover logistics (administrative coordination only; no physical delivery)
                </label>
                @if($enable['key_handover'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.key_handover" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.key_handover') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Move-in checklist -->
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="enable.move_in_checklist" id="moveInChecklist">
                <label class="form-check-label" for="moveInChecklist">
                    Provide a move-in checklist and utility setup/changeover resources (informational only)
                </label>
                @if($enable['move_in_checklist'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.move_in_checklist" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.move_in_checklist') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Purchasing Strategy & Advisory Support -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">📊 Purchasing Strategy & Advisory Support (Non-Agency Advisory Services)</h5>
        </div>
        <div class="card-body">
            <!-- CMA -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.cma" id="cma">
                <label class="form-check-label" for="cma">
                    Conduct a Comparative Market Analysis (CMA) to help assess market value and pricing trends (non-binding, informational only)
                </label>
                @if($enable['cma'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.cma" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.cma') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Homebuying process -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.homebuying_process" id="homebuyingProcess">
                <label class="form-check-label" for="homebuyingProcess">
                    Provide a written summary of the homebuying process (state-specific, non-legal guidance only)
                </label>
                @if($enable['homebuying_process'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.homebuying_process" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.homebuying_process') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Buyer preparation -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.buyer_preparation" id="buyerPreparation">
                <label class="form-check-label" for="buyerPreparation">
                    Offer buyer preparation tips related to financing, credit, insurance, and down payment planning (general, non-legal guidance only)
                </label>
                @if($enable['buyer_preparation'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.buyer_preparation" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.buyer_preparation') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- General questions -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.general_questions" id="generalQuestions">
                <label class="form-check-label" for="generalQuestions">
                    Answer common questions and provide general guidance (non-agency support only)
                </label>
                @if($enable['general_questions'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.general_questions" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.general_questions') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Closing costs -->
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="enable.closing_costs" id="closingCosts">
                <label class="form-check-label" for="closingCosts">
                    Summarize estimated closing costs based on general transaction terms and market trends (non-binding, informational only)
                </label>
                @if($enable['closing_costs'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.closing_costs" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.closing_costs') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Additional Services Section -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">✍️ Additional Services</h5>
        </div>
        <div class="card-body">
            @foreach ($custom_services as $index => $service)
                <div class="form-group" wire:key="service-{{ $index }}">
                    <!-- Service Description -->
                    <div class="input-cover mt-2">
                        <input type="text" wire:model="custom_services.{{ $index }}.description" 
                            class="form-control has-icon" data-icon="fa-solid fa-pen-to-square"
                            placeholder="Describe the service">
                        @error("custom_services.{$index}.description") 
                            <span class="text-danger">{{ $message }}</span> 
                        @enderror
                    </div>

                    <!-- Service Fee -->
                    <div class="input-cover mt-2">
                        <input type="number" wire:model="custom_services.{{ $index }}.fee" 
                            class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                            placeholder="Flat Fee for this service">
                        @error("custom_services.{$index}.fee") 
                            <span class="text-danger">{{ $message }}</span> 
                        @enderror
                    </div>

                    <!-- Remove Button -->
                    @if ($index > 0)
                        <button type="button" class="btn btn-sm btn-danger mt-2"
                            wire:click="removeService({{ $index }})">
                            <i class="fa-solid fa-trash me-1"></i> Remove Service
                        </button>
                    @endif
                </div>
            @endforeach

            <!-- Add Service Button -->
            <button type="button" class="btn btn-sm btn-primary mt-3" wire:click="addService">
                <i class="fa-solid fa-plus me-1"></i> Add Another Service
            </button>

            <!-- Totals Section -->
            <div class="border-top mt-4 pt-3">
                <div class="form-group">
                    <label class="fw-bold">
                        Flat Fee Amount (Total):
                        <i class="fa-solid fa-comment-dots text-primary" data-bs-toggle="tooltip"
                            title="This total auto-calculates as you enter amounts for individual services. You may
also manually enter one total flat fee to cover all selected services instead."></i>
                    </label>
                    <div class="input-cover mt-2">
                        <input type="number" wire:model="total_flat_fee" class="form-control has-icon"
                            data-icon="fa-solid fa-dollar-sign" placeholder="Total" readonly>
                    </div>
                </div>

                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" wire:model="understand_terms"
                        id="understandTerms" @error('understand_terms') is-invalid @enderror>
                    <label class="form-check-label" for="understandTerms">
                        I understand that the selected services do not include negotiation, contract review, or
                        agent representation unless a signed agency agreement is in place. All guidance is
                        informational only and not legal or financial advice.
                    </label>
                    @error('understand_terms')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

              
            </div>
        </div>
    </div>
</div>
@elseif ($user_type === 'seller')
<div class="services-container">
    <!-- Property Marketing & Listing Promotion -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">📢 Property Marketing & Listing Promotion</h5>
        </div>
        <div class="card-body">
            <!-- List on BidYourOffer.com -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.list_bidyouroffer" id="listBidYourOffer">
                <label class="form-check-label" for="listBidYourOffer">
                    List the property on BidYourOffer.com
                </label>
                @if($enable['list_bidyouroffer'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.list_bidyouroffer" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.list_bidyouroffer') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- List on MLS -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.list_mls" id="listMLS">
                <label class="form-check-label" for="listMLS">
                    List the property on the local Multiple Listing Service (MLS)
                </label>
                @if($enable['list_mls'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.list_mls" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.list_mls') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Syndicate to third-party sites -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.syndicate_listing" id="syndicateListing">
                <label class="form-check-label" for="syndicateListing">
                    Syndicate the listing to third-party residential websites (e.g., Zillow, Trulia, Realtor.com)
                </label>
                @if($enable['syndicate_listing'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.syndicate_listing" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.syndicate_listing') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- List on Crexi -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.list_crexi" id="listCrexi">
                <label class="form-check-label" for="listCrexi">
                    List the property on Crexi (Commercial Listings)
                </label>
                @if($enable['list_crexi'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.list_crexi" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.list_crexi') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- List on LoopNet -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.list_loopnet" id="listLoopNet">
                <label class="form-check-label" for="listLoopNet">
                    List the property on LoopNet (Commercial Listings)
                </label>
                @if($enable['list_loopnet'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.list_loopnet" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.list_loopnet') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Social media promotion -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.social_media" id="socialMedia">
                <label class="form-check-label" for="socialMedia">
                    Promote the listing across social media platforms — including real estate groups, pages, and affiliate networks — with a direct link to the listing on BidYourOffer.com
                </label>
                @if($enable['social_media'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.social_media" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.social_media') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Paid advertising -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.paid_ads" id="paidAds">
                <label class="form-check-label" for="paidAds">
                    Launch a targeted paid advertising campaign — including Facebook and Instagram — with a direct link to the listing on BidYourOffer.com
                </label>
                @if($enable['paid_ads'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.paid_ads" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.paid_ads') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Email marketing -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.email_marketing" id="emailMarketing">
                <label class="form-check-label" for="emailMarketing">
                    Create and launch a mass email marketing campaign with a direct link to the listing on BidYourOffer.com
                </label>
                @if($enable['email_marketing'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.email_marketing" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.email_marketing') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Local mailers -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.local_mailers" id="localMailers">
                <label class="form-check-label" for="localMailers">
                    Distribute local mailers to promote the listing in the surrounding neighborhood, featuring a QR code that links directly to the listing on BidYourOffer.com
                </label>
                @if($enable['local_mailers'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.local_mailers" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.local_mailers') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Hyperlocal ads -->
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="enable.hyperlocal_ads" id="hyperlocalAds">
                <label class="form-check-label" for="hyperlocalAds">
                    Launch hyperlocal digital ads targeting nearby residents with a direct link to the listing on BidYourOffer.com
                </label>
                @if($enable['hyperlocal_ads'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.hyperlocal_ads" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.hyperlocal_ads') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Listing Presentation & Preparation -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">📋 Listing Presentation & Preparation</h5>
        </div>
        <div class="card-body">
            <!-- CMA -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.cma" id="cma">
                <label class="form-check-label" for="cma">
                    Provide a Comparative Market Analysis (CMA) for informational purposes, along with general, non-binding pricing guidance.
                </label>
                @if($enable['cma'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.cma" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.cma') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Staging consultation -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.staging_consultation" id="stagingConsultation">
                <label class="form-check-label" for="stagingConsultation">
                    Offer a home staging consultation (visual recommendations only; no physical staging provided)
                </label>
                @if($enable['staging_consultation'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.staging_consultation" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.staging_consultation') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Coordinate staging -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.coordinate_staging" id="coordinateStaging">
                <label class="form-check-label" for="coordinateStaging">
                    Coordinate professional home staging services (includes vendor referral and scheduling only; third-party staging fees billed separately)
                </label>
                @if($enable['coordinate_staging'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.coordinate_staging" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.coordinate_staging') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="input-cover mt-2">
                    <input type="number" wire:model="staging_duration" class="form-control has-icon"
                        data-icon="fa-solid fa-calendar" placeholder="Staging Duration (months)">
                    @error('staging_duration') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Curb appeal consultation -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.curb_appeal_consultation" id="curbAppealConsultation">
                <label class="form-check-label" for="curbAppealConsultation">
                    Offer a curb appeal consultation (visual recommendations only; no physical work or vendor coordination provided)
                </label>
                @if($enable['curb_appeal_consultation'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.curb_appeal_consultation" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.curb_appeal_consultation') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Coordinate curb appeal -->
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="enable.coordinate_curb_appeal" id="coordinateCurbAppeal">
                <label class="form-check-label" for="coordinateCurbAppeal">
                    Coordinate curb appeal improvements with third-party vendors (e.g., landscaping, exterior cleaning; agent provides scheduling and referral only — vendor costs billed separately)
                </label>
                @if($enable['coordinate_curb_appeal'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.coordinate_curb_appeal" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.coordinate_curb_appeal') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Photography & Visual Enhancements -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">📸 Photography & Visual Enhancements</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fa-solid fa-info-circle"></i> Note: Services may be provided by the Agent or a third-party vendor. Flat fee includes service and coordination. Any use of a vendor will be disclosed.
            </div>

            <!-- Professional photography -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.professional_photography" id="professionalPhotography">
                <label class="form-check-label" for="professionalPhotography">
                    Provide professional photography
                </label>
                @if($enable['professional_photography'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.professional_photography" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.professional_photography') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Drone photos -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.drone_photos" id="dronePhotos">
                <label class="form-check-label" for="dronePhotos">
                    Provide drone photos
                </label>
                @if($enable['drone_photos'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.drone_photos" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.drone_photos') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Video walkthrough -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.video_walkthrough" id="videoWalkthrough">
                <label class="form-check-label" for="videoWalkthrough">
                    Provide video walkthrough tour
                </label>
                @if($enable['video_walkthrough'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.video_walkthrough" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.video_walkthrough') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- 3D virtual tour -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.virtual_tour" id="virtualTour">
                <label class="form-check-label" for="virtualTour">
                    Provide 3D virtual tour creation
                </label>
                @if($enable['virtual_tour'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.virtual_tour" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.virtual_tour') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Virtual staging -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.virtual_staging" id="virtualStaging">
                <label class="form-check-label" for="virtualStaging">
                    Provide virtual staging
                </label>
                @if($enable['virtual_staging'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.virtual_staging" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.virtual_staging') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Digital enhancement -->
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="enable.digital_enhancement" id="digitalEnhancement">
                <label class="form-check-label" for="digitalEnhancement">
                    Provide digital enhancement of photos
                </label>
                @if($enable['digital_enhancement'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.digital_enhancement" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.digital_enhancement') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Property Showings & Access Support -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">🏘 Property Showings & Access Support</h5>
        </div>
        <div class="card-body">
            <!-- Schedule showings -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.schedule_showings" id="scheduleShowings">
                <label class="form-check-label" for="scheduleShowings">
                    Schedule showings with prospective buyers
                </label>
                @if($enable['schedule_showings'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="showings_count" class="form-control has-icon"
                        data-icon="fa-solid fa-hashtag" placeholder="Number of showings to schedule">
                    @error('showings_count') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.schedule_showings" class="form-control has-icon"
                        placeholder="Flat Fee for this service">
                    @error('fees.schedule_showings') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Attend showings -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.attend_showings" id="attendShowings">
                <label class="form-check-label" for="attendShowings">
                    Attend showings with prospective buyers
                </label>
                @if($enable['attend_showings'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="attend_showings_count" class="form-control has-icon"
                        data-icon="fa-solid fa-hashtag" placeholder="Number of showings to attend">
                    @error('attend_showings_count') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.attend_showings" class="form-control has-icon"
                        placeholder="Flat Fee for this service">
                    @error('fees.attend_showings') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Virtual showings -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.virtual_showings" id="virtualShowings">
                <label class="form-check-label" for="virtualShowings">
                    Provide virtual showings to out-of-area buyers (e.g., live streaming or pre-recorded tours)
                </label>
                @if($enable['virtual_showings'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="virtual_showings_count" class="form-control has-icon"
                        data-icon="fa-solid fa-hashtag" placeholder="Number of virtual showings">
                    @error('virtual_showings_count') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.virtual_showings" class="form-control has-icon"
                         placeholder="Flat Fee for this service">
                    @error('fees.virtual_showings') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Open house -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.open_house" id="openHouse">
                <label class="form-check-label" for="openHouse">
                    Number of in-person open houses to host:
                </label>
                @if($enable['open_house'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="open_house_count" class="form-control has-icon"
                        data-icon="fa-solid fa-hashtag" placeholder="Number of in-person open houses to host">
                    @error('open_house_count') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.open_house" class="form-control has-icon"
                        placeholder="Flat Fee for this service">
                    @error('fees.open_house') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Lockbox -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.lockbox" id="lockbox">
                <label class="form-check-label" for="lockbox">
                    Provide a lockbox for agent access
                </label>
                @if($enable['lockbox'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.lockbox" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.lockbox') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Yard sign -->
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="enable.yard_sign" id="yardSign">
                <label class="form-check-label" for="yardSign">
                    Install a yard sign or directional signage
                </label>
                @if($enable['yard_sign'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.yard_sign" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.yard_sign') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Offer & Contract Support -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">📝 Offer & Contract Support</h5>
        </div>
        <div class="card-body">
            <!-- Organize offers -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.organize_offers" id="organizeOffers">
                <label class="form-check-label" for="organizeOffers">
                    Assist with organizing submitted offer documents and preparing response materials (non-legal, non-representative support only)
                </label>
                @if($enable['organize_offers'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.organize_offers" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.organize_offers') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Complete disclosures -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.complete_disclosures" id="completeDisclosures">
                <label class="form-check-label" for="completeDisclosures">
                    Assist with the completion and submission of required state-specific disclosure documents (non-legal, administrative guidance only)
                </label>
                @if($enable['complete_disclosures'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.complete_disclosures" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.complete_disclosures') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Follow up on offers -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.follow_up_offers" id="followUpOffers">
                <label class="form-check-label" for="followUpOffers">
                    Follow up with the buyer's agent or buyer regarding the status of submitted offers (informational follow-up only; no negotiation)
                </label>
                @if($enable['follow_up_offers'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.follow_up_offers" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.follow_up_offers') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Overview of agreement -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.overview_agreement" id="overviewAgreement">
                <label class="form-check-label" for="overviewAgreement">
                    Provide a general overview of purchase agreement terms and contingencies (non-legal explanation only)
                </label>
                @if($enable['overview_agreement'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.overview_agreement" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.overview_agreement') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Coordinate counteroffer -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.coordinate_counteroffer" id="coordinateCounteroffer">
                <label class="form-check-label" for="coordinateCounteroffer">
                    Coordinate delivery of counteroffer or response documents and confirm receipt with all applicable parties (administrative handling only)
                </label>
                @if($enable['coordinate_counteroffer'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.coordinate_counteroffer" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.coordinate_counteroffer') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- E-signature setup -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.esignature_setup" id="esignatureSetup">
                <label class="form-check-label" for="esignatureSetup">
                    Coordinate electronic signature setup, secure document delivery, and confirmation of receipt with all applicable parties (administrative handling only; no legal review or negotiation)
                </label>
                @if($enable['esignature_setup'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.esignature_setup" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.esignature_setup') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Track deadlines -->
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="enable.track_deadlines" id="trackDeadlines">
                <label class="form-check-label" for="trackDeadlines">
                    Track contract deadlines and contingency periods, and provide a checklist of key documents (informational only; no legal guidance)
                </label>
                @if($enable['track_deadlines'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.track_deadlines" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.track_deadlines') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Closing Coordination & Transition -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">🏁 Closing Coordination & Transition</h5>
        </div>
        <div class="card-body">
            <!-- Contract to close -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.contract_to_close" id="contractToClose">
                <label class="form-check-label" for="contractToClose">
                    Manage contract-to-close coordination (includes scheduling inspections, appraisal, and key milestones)
                </label>
                @if($enable['contract_to_close'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.contract_to_close" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.contract_to_close') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Vendor referrals -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.vendor_referrals" id="vendorReferrals">
                <label class="form-check-label" for="vendorReferrals">
                    Provide referrals to inspectors, contractors, escrow/title professionals, or related vendors (referrals only; no endorsements or guarantees)
                </label>
                @if($enable['vendor_referrals'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.vendor_referrals" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.vendor_referrals') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Escrow coordination -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.escrow_coordination" id="escrowCoordination">
                <label class="form-check-label" for="escrowCoordination">
                    Coordinate communication with escrow/title company regarding delivery of final documents and funding updates (administrative only; no financial handling)
                </label>
                @if($enable['escrow_coordination'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.escrow_coordination" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.escrow_coordination') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Final walkthrough -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.final_walkthrough" id="finalWalkthrough">
                <label class="form-check-label" for="finalWalkthrough">
                    Coordinate final walk-through appointment with the listing agent or seller (scheduling only)
                </label>
                @if($enable['final_walkthrough'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.final_walkthrough" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.final_walkthrough') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Closing appointment -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.closing_appointment" id="closingAppointment">
                <label class="form-check-label" for="closingAppointment">
                    Coordinate closing appointment with title company or attorney's office (scheduling and administrative support only)
                </label>
                @if($enable['closing_appointment'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.closing_appointment" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.closing_appointment') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Key handover -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.key_handover" id="keyHandover">
                <label class="form-check-label" for="keyHandover">
                    Confirm post-closing instructions and key handover logistics (administrative coordination only; no physical delivery)
                </label>
                @if($enable['key_handover'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.key_handover" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.key_handover') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Move-out checklist -->
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="enable.move_out_checklist" id="moveOutChecklist">
                <label class="form-check-label" for="moveOutChecklist">
                    Provide a move-out checklist and utility shutoff/changeover resources (informational only)
                </label>
                @if($enable['move_out_checklist'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.move_out_checklist" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.move_out_checklist') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Selling Strategy & Advisory Support -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">📊 Selling Strategy & Advisory Support (Non-Agency Advisory Services)</h5>
        </div>
        <div class="card-body">
            <!-- Selling process explanation -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.selling_process" id="sellingProcess">
                <label class="form-check-label" for="sellingProcess">
                    Provide a written explanation of the selling process (state-specific, non-legal guidance only)
                </label>
                @if($enable['selling_process'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.selling_process" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.selling_process') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Property improvements -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.property_improvements" id="propertyImprovements">
                <label class="form-check-label" for="propertyImprovements">
                    Offer general advice on property improvements that may enhance value or appeal (non-binding, informational only)
                </label>
                @if($enable['property_improvements'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.property_improvements" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.property_improvements') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- General questions -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.general_questions" id="generalQuestions">
                <label class="form-check-label" for="generalQuestions">
                    Answer general questions about preparing the property for sale (non-agency, administrative support only)
                </label>
                @if($enable['general_questions'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.general_questions" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.general_questions') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            <!-- Closing costs -->
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="enable.closing_costs" id="closingCosts">
                <label class="form-check-label" for="closingCosts">
                    Summarize estimated seller closing costs based on typical transaction terms and market trends (non-binding, informational only)
                </label>
                @if($enable['closing_costs'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.closing_costs" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.closing_costs') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Additional Services: Section -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">✍️ Additional Services:</h5>
        </div>
        <div class="card-body">
            @foreach ($custom_services as $index => $service)
                <div class="form-group" wire:key="service-{{ $index }}">
                    <!-- Service Description -->
                    <div class="input-cover mt-2">
                        <input type="text" wire:model="custom_services.{{ $index }}.description" 
                            class="form-control has-icon" data-icon="fa-solid fa-pen-to-square"
                            placeholder="Describe the service">
                        @error("custom_services.{$index}.description") 
                            <span class="text-danger">{{ $message }}</span> 
                        @enderror
                    </div>

                    <!-- Service Fee -->
                    <div class="input-cover mt-2">
                        <input type="number" wire:model="custom_services.{{ $index }}.fee" 
                            class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                            placeholder="Flat Fee for this service">
                        @error("custom_services.{$index}.fee") 
                            <span class="text-danger">{{ $message }}</span> 
                        @enderror
                    </div>

                    <!-- Remove Button -->
                    @if ($index > 0)
                        <button type="button" class="btn btn-sm btn-danger mt-2"
                            wire:click="removeService({{ $index }})">
                            <i class="fa-solid fa-trash me-1"></i> Remove Service
                        </button>
                    @endif
                </div>
            @endforeach

            <!-- Add Service Button -->
            <button type="button" class="btn btn-sm btn-primary mt-3" wire:click="addService">
                <i class="fa-solid fa-plus me-1"></i> Add Another Service
            </button>

            <!-- Totals Section -->
            <div class="border-top mt-4 pt-3">
                <div class="form-group">
                    <label class="fw-bold">
                        Flat Fee Amount (Total):
                        <i class="fa-solid fa-comment-dots text-primary" data-bs-toggle="tooltip"
                            title="This total auto-calculates as you enter amounts for individual services. You may
also manually enter one total flat fee to cover all selected services instead."></i>
                    </label>
                    <div class="input-cover mt-2">
                        <input type="number" wire:model="total_flat_fee" class="form-control has-icon"
                            data-icon="fa-solid fa-dollar-sign" placeholder="Total" readonly>
                    </div>
                </div>

                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" wire:model="understand_terms"
                        id="understandTerms" @error('understand_terms') is-invalid @enderror>
                    <label class="form-check-label" for="understandTerms">
                        I understand that these flat-fee services are limited to administrative or marketing support. Pricing strategy, negotiations, and legal or fiduciary representation are only provided with a signed agency agreement.
                    </label>
                    @error('understand_terms')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                
            </div>
        </div>
    </div>
</div>





@elseif($user_type === 'landlord')


<div class="container">
    <!-- Rental Marketing & Listing Promotion -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">📢 Rental Marketing & Listing Promotion</h5>
        </div>
        <div class="card-body">
            <!-- List on BidYourOffer.com -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.list_bidyouroffer" id="listBidYourOffer">
                <label class="form-check-label" for="listBidYourOffer">
                    List the property on BidYourOffer.com
                </label>
                @if($enable['list_bidyouroffer'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.list_bidyouroffer" class="form-control has-icon"
                           data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.list_bidyouroffer') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
    
            <!-- List on MLS -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.list_mls" id="listMLS">
                <label class="form-check-label" for="listMLS">
                    List the property on the local Multiple Listing Service (MLS)
                </label>
                @if($enable['list_mls'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.list_mls" class="form-control has-icon"
                           data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.list_mls') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
    
            <!-- Syndicate to third-party sites -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.syndicate_listing" id="syndicateListing">
                <label class="form-check-label" for="syndicateListing">
                    Syndicate the listing to third-party residential websites (e.g., Zillow, Trulia, Realtor.com)
                </label>
                @if($enable['syndicate_listing'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.syndicate_listing" class="form-control has-icon"
                           data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.syndicate_listing') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
    
            <!-- List on Crexi -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.list_crexi" id="listCrexi">
                <label class="form-check-label" for="listCrexi">
                    List the property on Crexi (Commercial Listings)
                </label>
                @if($enable['list_crexi'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.list_crexi" class="form-control has-icon"
                           data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.list_crexi') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
    
            <!-- List on LoopNet -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.list_loopnet" id="listLoopNet">
                <label class="form-check-label" for="listLoopNet">
                    List the property on LoopNet (Commercial Listings)
                </label>
                @if($enable['list_loopnet'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.list_loopnet" class="form-control has-icon"
                           data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.list_loopnet') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
    
            <!-- Social media promotion -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.social_media" id="socialMedia">
                <label class="form-check-label" for="socialMedia">
                    Promote the listing across social media platforms — including real estate groups, pages, and affiliate networks — with a direct link to the listing on BidYourOffer.com
                </label>
                @if($enable['social_media'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.social_media" class="form-control has-icon"
                           data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.social_media') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
    
            <!-- Paid advertising -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.paid_ads" id="paidAds">
                <label class="form-check-label" for="paidAds">
                    Launch a targeted paid advertising campaign — including Facebook and Instagram — with a direct link to the listing on BidYourOffer.com
                </label>
                @if($enable['paid_ads'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.paid_ads" class="form-control has-icon"
                           data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.paid_ads') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
    
            <!-- Email marketing -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.email_marketing" id="emailMarketing">
                <label class="form-check-label" for="emailMarketing">
                    Create and launch a mass email marketing campaign with a direct link to the listing on BidYourOffer.com
                </label>
                @if($enable['email_marketing'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.email_marketing" class="form-control has-icon"
                           data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.email_marketing') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
    
            <!-- Local mailers -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="enable.local_mailers" id="localMailers">
                <label class="form-check-label" for="localMailers">
                    Distribute local mailers to promote the listing in the surrounding neighborhood, featuring a QR code that links directly to the listing on BidYourOffer.com
                </label>
                @if($enable['local_mailers'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.local_mailers" class="form-control has-icon"
                           data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.local_mailers') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
    
            <!-- Hyperlocal ads -->
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="enable.hyperlocal_ads" id="hyperlocalAds">
                <label class="form-check-label" for="hyperlocalAds">
                    Launch hyperlocal digital ads targeting nearby residents with a direct link to the listing on BidYourOffer.com
                </label>
                @if($enable['hyperlocal_ads'])
                <div class="input-cover mt-2">
                    <input type="number" wire:model="fees.hyperlocal_ads" class="form-control has-icon"
                           data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                    @error('fees.hyperlocal_ads') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>
        </div>
    </div>

  <!-- Listing Presentation & Preparation -->
<div class="card mb-4">
    <div class="card-header bg-info">
        <h5 class="mb-0 text-white">📋 Listing Presentation & Preparation</h5>
    </div>
    <div class="card-body">
        <!-- Rental Market Analysis -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.rma" id="rma">
            <label class="form-check-label" for="rma">
                Conduct a Rental Market Analysis (RMA) to help assess rental pricing and area trends (non-binding guidance only)
            </label>
            @if($enable['rma'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.rma" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.rma') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- Curb appeal consultation -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.curb_appeal_consultation" id="curbAppealConsultation">
            <label class="form-check-label" for="curbAppealConsultation">
                Offer a curb appeal consultation (visual recommendations only; no physical work or vendor coordination provided)
            </label>
            @if($enable['curb_appeal_consultation'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.curb_appeal_consultation" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.curb_appeal_consultation') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- Coordinate curb appeal -->
        <div class="form-check">
            <input class="form-check-input" type="checkbox" wire:model="enable.coordinate_curb_appeal" id="coordinateCurbAppeal">
            <label class="form-check-label" for="coordinateCurbAppeal">
                Coordinate curb appeal improvements with third-party vendors (e.g., landscaping, exterior cleaning; agent provides scheduling and referral only — vendor costs billed separately)
            </label>
            @if($enable['coordinate_curb_appeal'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.coordinate_curb_appeal" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.coordinate_curb_appeal') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Photography & Visual Enhancements -->
<div class="card mb-4">
    <div class="card-header bg-info">
        <h5 class="mb-0 text-white">📸 Photography & Visual Enhancements</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-4">
            <i class="fa-solid fa-info-circle me-2"></i>
            Note: Services may be provided by the Agent or a third-party vendor. Flat fee includes service and coordination. Any use of a vendor will be disclosed.
        </div>

        <!-- Professional photography -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.professional_photography" id="professionalPhotography">
            <label class="form-check-label" for="professionalPhotography">
                Provide professional photography
            </label>
            @if($enable['professional_photography'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.professional_photography" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.professional_photography') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- Drone photos -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.drone_photos" id="dronePhotos">
            <label class="form-check-label" for="dronePhotos">
                Provide drone photos
            </label>
            @if($enable['drone_photos'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.drone_photos" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.drone_photos') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- Video walkthrough -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.video_walkthrough" id="videoWalkthrough">
            <label class="form-check-label" for="videoWalkthrough">
                Provide video walkthrough tour
            </label>
            @if($enable['video_walkthrough'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.video_walkthrough" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.video_walkthrough') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- 3D virtual tour -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.virtual_tour" id="virtualTour">
            <label class="form-check-label" for="virtualTour">
                Provide 3D virtual tour creation
            </label>
            @if($enable['virtual_tour'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.virtual_tour" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.virtual_tour') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- Virtual staging -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.virtual_staging" id="virtualStaging">
            <label class="form-check-label" for="virtualStaging">
                Provide virtual staging
            </label>
            @if($enable['virtual_staging'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.virtual_staging" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.virtual_staging') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- Digital enhancement -->
        <div class="form-check">
            <input class="form-check-input" type="checkbox" wire:model="enable.digital_enhancement" id="digitalEnhancement">
            <label class="form-check-label" for="digitalEnhancement">
                Provide digital enhancement of photos
            </label>
            @if($enable['digital_enhancement'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.digital_enhancement" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.digital_enhancement') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Property Showings & Access Support -->
<div class="card mb-4">
    <div class="card-header bg-info">
        <h5 class="mb-0 text-white">🏘 Property Showings & Access Support</h5>
    </div>
    <div class="card-body">
        <!-- Schedule showings -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.schedule_showings" id="scheduleShowings">
            <label class="form-check-label" for="scheduleShowings">
                Schedule showings with prospective tenants
            </label>
            @if($enable['schedule_showings'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="showings_count" class="form-control has-icon"
                       data-icon="fa-solid fa-hashtag" placeholder="Number of showings to schedule">
                @error('showings_count') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.schedule_showings" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.schedule_showings') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- Attend showings -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.attend_showings" id="attendShowings">
            <label class="form-check-label" for="attendShowings">
                Attend showings with prospective tenants
            </label>
            @if($enable['attend_showings'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="attend_showings_count" class="form-control has-icon"
                       data-icon="fa-solid fa-hashtag" placeholder="Number of showings to attend">
                @error('attend_showings_count') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.attend_showings" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.attend_showings') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- Virtual showings -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.virtual_showings" id="virtualShowings">
            <label class="form-check-label" for="virtualShowings">
                Provide virtual showings to out-of-area applicants (e.g., live streaming or pre-recorded tours)
            </label>
            @if($enable['virtual_showings'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="virtual_showings_count" class="form-control has-icon"
                       data-icon="fa-solid fa-hashtag" placeholder="Number of virtual showings">
                @error('virtual_showings_count') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.virtual_showings" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.virtual_showings') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- Open house -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.open_house" id="openHouse">
            <label class="form-check-label" for="openHouse">
                Host in-person open house
            </label>
            @if($enable['open_house'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="open_house_count" class="form-control has-icon"
                       data-icon="fa-solid fa-hashtag" placeholder="Number of in-person open houses to host">
                @error('open_house_count') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.open_house" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.open_house') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- Lockbox -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.lockbox" id="lockbox">
            <label class="form-check-label" for="lockbox">
                Provide a lockbox for agent access
            </label>
            @if($enable['lockbox'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.lockbox" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.lockbox') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- Yard sign -->
        <div class="form-check">
            <input class="form-check-input" type="checkbox" wire:model="enable.yard_sign" id="yardSign">
            <label class="form-check-label" for="yardSign">
                Install a yard sign or directional signage
            </label>
            @if($enable['yard_sign'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.yard_sign" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.yard_sign') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Application Support -->
<div class="card mb-4">
    <div class="card-header bg-info">
        <h5 class="mb-0 text-white">📝 Application Support</h5>
    </div>
    <div class="card-body">
        <!-- Standard rental application -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.standard_application" id="standardApplication">
            <label class="form-check-label" for="standardApplication">
                Provide a standard rental application and submission instructions
            </label>
            @if($enable['standard_application'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.standard_application" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.standard_application') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- Submit documents -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.submit_documents" id="submitDocuments">
            <label class="form-check-label" for="submitDocuments">
                Collect, organize, and submit supporting documents provided by the tenant or tenant's agent for the rental application (administrative delivery only; no screening or evaluation)
            </label>
            @if($enable['submit_documents'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.submit_documents" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.submit_documents') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- Follow up on application -->
        <div class="form-check">
            <input class="form-check-input" type="checkbox" wire:model="enable.follow_up_application" id="followUpApplication">
            <label class="form-check-label" for="followUpApplication">
                Follow up with the tenant or tenant's agent regarding the status of a submitted rental application (informational only; no negotiation or representation involved)
            </label>
            @if($enable['follow_up_application'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.follow_up_application" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.follow_up_application') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>
    </div>
</div>
   
<!-- Property Showings & Access Support -->
<div class="card mb-4">
    <div class="card-header bg-info">
        <h5 class="mb-0 text-white">🏘 Property Showings & Access Support</h5>
    </div>
    <div class="card-body">
        <!-- Schedule showings -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.schedule_showings" id="scheduleShowings">
            <label class="form-check-label" for="scheduleShowings">
                Schedule showings with prospective tenants
            </label>
            @if($enable['schedule_showings'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="showings_count" class="form-control has-icon"
                       data-icon="fa-solid fa-hashtag" placeholder="Number of showings to schedule">
                @error('showings_count') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.schedule_showings" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.schedule_showings') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- Attend showings -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.attend_showings" id="attendShowings">
            <label class="form-check-label" for="attendShowings">
                Attend showings with prospective tenants
            </label>
            @if($enable['attend_showings'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="attend_showings_count" class="form-control has-icon"
                       data-icon="fa-solid fa-hashtag" placeholder="Number of showings to attend">
                @error('attend_showings_count') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.attend_showings" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.attend_showings') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- Virtual showings -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.virtual_showings" id="virtualShowings">
            <label class="form-check-label" for="virtualShowings">
                Provide virtual showings to out-of-area applicants (e.g., live streaming or pre-recorded tours)
            </label>
            @if($enable['virtual_showings'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="virtual_showings_count" class="form-control has-icon"
                       data-icon="fa-solid fa-hashtag" placeholder="Number of virtual showings">
                @error('virtual_showings_count') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.virtual_showings" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.virtual_showings') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- Open house -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.open_house" id="openHouse">
            <label class="form-check-label" for="openHouse">
                Host in-person open house
            </label>
            @if($enable['open_house'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="open_house_count" class="form-control has-icon"
                       data-icon="fa-solid fa-hashtag" placeholder="Number of in-person open houses to host">
                @error('open_house_count') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.open_house" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.open_house') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- Lockbox -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.lockbox" id="lockbox">
            <label class="form-check-label" for="lockbox">
                Provide a lockbox for agent access
            </label>
            @if($enable['lockbox'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.lockbox" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.lockbox') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- Yard sign -->
        <div class="form-check">
            <input class="form-check-input" type="checkbox" wire:model="enable.yard_sign" id="yardSign">
            <label class="form-check-label" for="yardSign">
                Install a yard sign or directional signage
            </label>
            @if($enable['yard_sign'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.yard_sign" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.yard_sign') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Application Support -->
<div class="card mb-4">
    <div class="card-header bg-info">
        <h5 class="mb-0 text-white">📝 Application Support</h5>
    </div>
    <div class="card-body">
        <!-- Standard rental application -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.standard_application" id="standardApplication">
            <label class="form-check-label" for="standardApplication">
                Provide a standard rental application and submission instructions
            </label>
            @if($enable['standard_application'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.standard_application" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.standard_application') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- Submit documents -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.submit_documents" id="submitDocuments">
            <label class="form-check-label" for="submitDocuments">
                Collect, organize, and submit supporting documents provided by the tenant or tenant's agent for the rental application (administrative delivery only; no screening or evaluation)
            </label>
            @if($enable['submit_documents'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.submit_documents" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.submit_documents') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- Follow up on application -->
        <div class="form-check">
            <input class="form-check-input" type="checkbox" wire:model="enable.follow_up_application" id="followUpApplication">
            <label class="form-check-label" for="followUpApplication">
                Follow up with the tenant or tenant's agent regarding the status of a submitted rental application (informational only; no negotiation or representation involved)
            </label>
            @if($enable['follow_up_application'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.follow_up_application" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.follow_up_application') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>
    </div>
</div>


<!-- Rental Strategy & Advisory Support -->
<div class="card mb-4">
    <div class="card-header bg-info">
        <h5 class="mb-0 text-white">📊 Rental Strategy & Advisory Support (Non-Agency Services)</h5>
    </div>
    <div class="card-body">
        <!-- Rental laws -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.rental_laws" id="rentalLaws">
            <label class="form-check-label" for="rentalLaws">
                Provide a written summary of local rental laws (state-specific, non-legal guidance only)
            </label>
            @if($enable['rental_laws'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.rental_laws" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.rental_laws') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- Property improvements -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.property_improvements" id="propertyImprovements">
            <label class="form-check-label" for="propertyImprovements">
                Offer general advice on property improvements that may enhance value or appeal (non-binding, informational only)
            </label>
            @if($enable['property_improvements'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.property_improvements" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.property_improvements') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- Lease options -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.lease_options" id="leaseOptions">
            <label class="form-check-label" for="leaseOptions">
                Help compare lease options (e.g., traditional lease, lease-option, short-term, furnished rentals)
            </label>
            @if($enable['lease_options'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.lease_options" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.lease_options') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- General questions -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" wire:model="enable.general_questions" id="generalQuestions">
            <label class="form-check-label" for="generalQuestions">
                Answer general questions about preparing a property to lease (non-agency, administrative support only)
            </label>
            @if($enable['general_questions'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.general_questions" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.general_questions') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>

        <!-- Move-in costs -->
        <div class="form-check">
            <input class="form-check-input" type="checkbox" wire:model="enable.move_in_costs" id="moveInCosts">
            <label class="form-check-label" for="moveInCosts">
                Provide a summary of typical move-in costs and income-to-rent ratio expectations based on standard lease terms and current market trends (non-binding, informational only)
            </label>
            @if($enable['move_in_costs'])
            <div class="input-cover mt-2">
                <input type="number" wire:model="fees.move_in_costs" class="form-control has-icon"
                       data-icon="fa-solid fa-dollar-sign" placeholder="Flat fee for this service">
                @error('fees.move_in_costs') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            @endif
        </div>
    </div>
</div>

    <!-- Additional Services -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0 text-white">✍️ Additional Services:</h5>
        </div>
        <div class="card-body">
            @foreach ($custom_services as $index => $service)
                <div class="form-group" wire:key="service-{{ $index }}">
                    <label class="form-check-label">
                        Additional Service {{ $index + 1 }}
                    </label>
                    <div class="input-cover mt-2">
                        <input type="text" wire:model="custom_services.{{ $index }}.description"
                            class="form-control has-icon" data-icon="fa-solid fa-pen-to-square"
                            placeholder="Describe the service">
                    </div>
                    <div class="input-cover mt-2">
                        <input type="number" wire:model="custom_services.{{ $index }}.fee"
                            class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                            placeholder="Flat Fee for this service">
                    </div>
                    @if ($index > 0)
                        <button type="button" class="btn btn-sm btn-danger mt-2"
                            wire:click="removeService({{ $index }})">
                            <i class="fa-solid fa-trash me-1"></i> Remove Service
                        </button>
                    @endif
                </div>
            @endforeach

            <button type="button" class="btn btn-sm btn-primary mt-3" wire:click="addService">
                <i class="fa-solid fa-plus me-1"></i> Add Another Service
            </button>

            <!-- Totals Section -->
            <div class="border-top mt-4 pt-3">
                <div class="form-group">
                    <label class="fw-bold">
                        Flat Fee Amount (Total):
                        <i class="fa-solid fa-comment-dots text-primary" data-bs-toggle="tooltip"
                            title="This total auto-calculates as you enter amounts for individual services. You may
also manually enter one total flat fee to cover all selected services instead."></i>
                    </label>
                    <div class="input-cover mt-2">
                        <input type="number" wire:model="total_flat_fee" class="form-control has-icon"
                            data-icon="fa-solid fa-dollar-sign" placeholder="Total" readonly>
                    </div>
                </div>

                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" wire:model="understand_terms"
                        id="understandTerms"  @error('understand_terms') is-invalid @enderror >
                    <label class="form-check-label" for="understandTerms">
                        I understand that the selected services are administrative or informational only and do not include tenant screening, lease negotiation, or legal advice unless a signed agency agreement is in place.
                    </label>
                </div>
                @error('understand_terms')
                <div class="invalid-feedback">
                    You must accept the terms to continue
                </div>
            @enderror
            </div>
        </div>
    </div>
</div>


@endif
