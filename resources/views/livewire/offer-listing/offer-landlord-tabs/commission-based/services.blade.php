<h3>Services the Landlord Requests from Their Agent</h3>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>🤝 Select the services the Landlord would like the Agent to provide throughout the leasing process.
                Services are offered under a commission-based, full-service agreement, with the brokerage relationship
                type determined in accordance with state law. Selections here are for guidance only; the signed
                brokerage agreement governs the final scope of representation and compensation.
            </strong>
        </div>
    </div>
</div>
@if ($property_type == 'Residential Property')
    <!-- 📢 Rental Marketing & Listing Promotion -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📢 Rental Marketing & Listing Promotion</h5>
        <div class="service-options">
            @foreach (['List the property on the local Multiple Listing Service (MLS)', 'Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)', 'Create a branded flyer featuring the property’s key highlights', 'Post the property on Facebook Marketplace', 'Post the property on Craigslist in the appropriate “Homes for Rent” category', 'Share the listing on Nextdoor in Neighborhood or Community Groups', 'Promote the listing on Facebook in Housing or Rental Groups', 'Share the listing on Instagram using posts, stories, or reels', 'Promote the listing on LinkedIn in Professional or Real Estate Groups', 'Upload a TikTok video walkthrough of the property', 'Upload a YouTube video walkthrough of the property', 'Launch a mass email campaign promoting the listing', 'Distribute printed flyers or postcards in target geographic areas', 'Launch hyperlocal or interest-based digital ad campaigns promoting the listing'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="marketing-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="marketing-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <!-- 📋 Listing Presentation & Preparation -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📋 Listing Presentation & Preparation</h5>
        <div class="service-options">
            @foreach (['Conduct a property walkthrough and provide recommendations for listing readiness', 'Provide a custom listing preparation checklist', 'Collect property details and prepare MLS remarks and a public listing description', 'Provide a visual consultation for interior layout, cleanliness, and presentation', 'Provide a curb appeal consultation focused on exterior presentation', 'Provide referrals to third-party vendors (e.g., cleaners, handypeople, electricians, landscapers). Vendor fees billed separately. Referrals only — no endorsement or warranty is made'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="prep-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="prep-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <!-- 📸 Photography, Video & Virtual Media -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📸 Photography, Video & Virtual Media</h5>
        <div class="service-options">
            @foreach (['Provide professional property photography', 'Provide aerial (drone) photography (subject to FAA Part 107 compliance)', 'Provide a video walkthrough tour', 'Provide a 3D virtual tour', 'Provide virtual staging (digital enhancements only; no physical staging)', 'Provide digital photo enhancements', 'Create a basic schematic floor plan (non-certified; for marketing purposes only)'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input service-checkbox" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="media-{{ Str::slug($service) }}"
                        @if ($service === 'Provide digital photo enhancements') wire:click="$set('showEnhancements', !$showEnhancements)" data-enhancement-trigger @endif>
                    <label class="form-check-label" for="media-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>

                @if ($service === 'Provide digital photo enhancements')
                    <div class="enhancement-options ms-4 mt-2 mb-3"
                        style="display: {{ $showEnhancements ? 'block' : 'none' }};">
                        <div class="form-text mb-2">Select enhancement types:</div>

                        @foreach (['Basic edits (brightness, contrast, cropping)', 'Twilight conversion (convert daytime photo to sunset look)', 'Object removal (e.g., cars, trash cans, furniture, etc.)', 'Virtual twilight photography', 'Color correction or sky replacement', 'Other'] as $enhancement)
                            <div class="form-check">
                                <input class="form-check-input enhancement-checkbox" type="checkbox"
                                    wire:model="photo_enhancements" value="{{ $enhancement }}"
                                    id="enhancement-{{ Str::slug($enhancement) }}"
                                    @if ($enhancement === 'Other') wire:click="$set('showCustomEnhancement', !$showCustomEnhancement)" @endif>
                                <label class="form-check-label" for="enhancement-{{ Str::slug($enhancement) }}">
                                    {{ $enhancement }}
                                </label>
                            </div>
                        @endforeach

                        <div class="mt-2" style="display: {{ $showCustomEnhancement ? 'block' : 'none' }};">
                            <input type="text" class="form-control" wire:model="custom_enhancement"
                                placeholder="Enter photo enhancement requests">
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        {{-- <div class="alert alert-warning mt-3 p-2 small">
            <strong> Note: </strong> These services may be provided by the Agent or through a third-party vendor.
        </div> --}}
    </div>

    <!-- 🏡 Showings & Access Coordination -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">🏡 Showings & Access Coordination</h5>
        <div class="service-options">
            @foreach (['Ensure proper notice is given if the property is occupied', 'Install a real estate sign on the property', 'Install a lockbox for Agent access', 'Schedule and attend showings with prospective Tenants', 'Coordinate showings with Tenant’s Agents', 'Collect and relay feedback to the Landlord after showings'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="showing-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="showing-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>

        {{-- <div class="alert alert-warning mt-3 p-2 small">
            <strong> Note : </strong> All services, including showings, lockbox access, signage, and marketing, are
            subject to applicable MLS, association, and/or local regulations.
        </div> --}}

    </div>

    <!-- 📝 Tenant Application Support -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📝 Tenant Application Support</h5>
        <div class="service-options">
            @foreach (['Provide a link to an online application platform with third-party screening tools (e.g., credit, background, and eviction checks)', 'Ensure compliance with Fair Housing laws and screening regulations throughout the application process', 'Collect and organize application documents submitted by prospective Tenants', 'Verify basic information provided in the application (e.g., employment, income, and references)', 'Present complete and organized application packages to the Landlord for review and final selection'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="application-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="application-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <!-- 📃 Lease Preparation & Execution -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📃 Lease Preparation & Execution</h5>
        <div class="service-options">
            @foreach (['Review lease offers submitted by prospective Tenants and summarize key terms', 'Coordinate lease negotiation with the Tenant or Tenant’s Agent', 'Prepare a state-specific lease agreement using approved forms or templates', 'Assist with completing required lease disclosures and reviewing key lease terms', 'Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties', 'Confirm receipt of required move-in funds and assist the Landlord in verifying amounts due, payment deadlines, and accepted payment methods'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="lease-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="lease-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <!-- 🚚 Move-In Support & Coordination -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">🚚 Move-In Support & Coordination</h5>
        <div class="service-options">
            @foreach (['Coordinate move-in date and key handoff logistics with the Tenant or Tenant’s Agent', 'Confirm completion of any agreed-upon pre-move-in cleaning or repairs', 'Verify receipt of all required move-in funds prior to occupancy (e.g., deposit, rent, pet fees)', 'Provide a utility setup checklist and local provider resources for the Tenant', 'Share a move-in checklist for documentation and property condition review'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="move-in-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="move-in-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📑 Property Management </h5>
    
        <div class="service-options">

            @php

                $PropertyManagement = [
                    'Provide ongoing property management services throughout the lease term (rent collection, maintenance coordination, Tenant communications, lease enforcement, renewals, etc.)',
                ];

            @endphp
            @foreach ($PropertyManagement as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="Property-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="Property-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
        <div class="alert alert-warning mt-3 p-2 small">
            <strong>⚖️ Note: </strong> Property management services are separate from leasing services. Agents
            typically charge an additional fee (monthly flat fee or percentage of rent) under a separate property
            management agreement. Availability and terms may vary by Agent and are subject to brokerage policies and
            state law.
        </div>
    </div>

    <!-- 💡 Leasing Strategy & Guidance -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">💡 Leasing Strategy & Guidance</h5>
        <div class="service-options">
            @foreach (['Provide a Rental Market Analysis (RMA) with pricing insights based on comparable rentals, neighborhood trends, and current market conditions ', 'Advise on lease types and structures (e.g., month-to-month, annual, furnished, corporate, lease-option)', 'Provide general guidance on Landlord obligations and Tenant rights under state law', 'Provide general guidance on rental demand, local market conditions, and Tenant expectations'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="strategy-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="strategy-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

@endif

@if ($property_type == 'Commercial Property')

    <!-- 📢 Rental Marketing & Listing Promotion Section -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📢 Rental Marketing & Listing Promotion</h5>
        <div class="service-options">
            @foreach (['List the property on the local Multiple Listing Service (MLS)', 'List the property on Crexi.com', 'List the property on LoopNet.com', 'Create a branded flyer featuring the property’s key highlights', 'Post the property on Craigslist under the “Office/Commercial” category', 'Promote the listing on Facebook in Commercial Leasing or Business Startup Groups', 'Share the listing on Instagram using photos, stories, or reels', 'Promote the listing on LinkedIn in Professional, Real Estate, or Commercial Investment Groups', 'Upload a TikTok video walkthrough of the property', 'Upload a YouTube video walkthrough of the property', 'Launch a mass email campaign promoting the listing', 'Distribute printed flyers or postcards in target geographic areas', 'Launch hyperlocal or interest-based digital ad campaigns promoting the listing'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="marketing-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="marketing-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <!-- 📋 Listing Presentation & Preparation Section -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📋 Listing Presentation & Preparation</h5>
        <div class="service-options">
            @foreach (['Conduct a property walkthrough and provide recommendations for listing readiness', 'Provide a custom listing preparation checklist', 'Collect property details such as lease terms, square footage, property features, and allowable uses', 'Prepare a marketing packet including zoning, cap rate references, and permitted uses', 'Provide a visual consultation focused on interior layout, cleanliness, and presentation', 'Provide a curb appeal consultation for exterior appearance and signage opportunities', 'Provide referrals to third-party vendors (e.g., cleaners, sign installers, minor repair vendors). Vendor fees billed separately. Referrals only — no endorsement or warranty is made'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="prep-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="prep-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Photography, Video & Virtual Media -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📸 Photography, Video & Virtual Media</h5>
        <div class="service-options">
            @foreach (['Provide professional property photography', 'Provide aerial (drone) photography (subject to FAA Part 107 compliance)', 'Provide a video walkthrough tour', 'Provide a 3D virtual tour', 'Provide virtual staging (digital enhancements only; no physical staging)', 'Provide digital photo enhancements', 'Create a basic schematic floor plan (non-certified; for marketing purposes only)'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input service-checkbox" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="media-{{ Str::slug($service) }}"
                        @if ($service === 'Provide digital photo enhancements') wire:click="$set('showEnhancements', !$showEnhancements)"
                           data-enhancement-trigger @endif>
                    <label class="form-check-label" for="media-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>

                @if ($service === 'Provide digital photo enhancements')
                    <div class="enhancement-options ms-4 mt-2 mb-3"
                        style="display: {{ $showEnhancements ? 'block' : 'none' }};">

                        <div class="form-text mb-2">Select enhancement types:</div>

                        @foreach (['Basic edits (brightness, contrast, cropping)', 'Twilight conversion (convert daytime photo to sunset look)', 'Object removal (e.g., cars, trash cans, furniture, etc.)', 'Virtual twilight photography', 'Color correction or sky replacement', 'Other'] as $enhancement)
                            <div class="form-check">
                                <input class="form-check-input enhancement-checkbox" type="checkbox"
                                    wire:model="photo_enhancements" value="{{ $enhancement }}"
                                    id="enhancement-{{ Str::slug($enhancement) }}"
                                    @if ($enhancement === 'Other') wire:click="$set('showCustomEnhancement', !$showCustomEnhancement)" @endif>
                                <label class="form-check-label" for="enhancement-{{ Str::slug($enhancement) }}">
                                    {{ $enhancement }}
                                </label>
                            </div>
                        @endforeach

                        <div class="mt-2" style="display: {{ $showCustomEnhancement ? 'block' : 'none' }};">
                            <input type="text" class="form-control" wire:model="custom_enhancement"
                                placeholder="Enter photo enhancement requests">
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        <div class="alert alert-warning mt-3 p-2 small">
            <strong> Note: </strong> These services may be provided by the Agent or through a third-party vendor.
        </div>
    </div>

    <!-- 🏢 Showings & Access Coordination -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">🏢 Showings & Access Coordination</h5>
        <div class="service-options">
            @foreach (['Ensure proper notice is given if the property is occupied', 'Install a real estate sign on the property', 'Install a lockbox for Agent access', 'Schedule and attend showings with prospective Tenants', 'Coordinate showings with Tenant’s Agents', 'Collect and relay showing feedback to the Landlord'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="showing-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="showing-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <!-- 📝 Tenant Application Support -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📝 Tenant Application Support</h5>
        <div class="service-options">
            @foreach (['Provide a link to an online application platform or share instructions with prospective Tenants or Tenant’s Agents', 'Ensure compliance with applicable federal, state, and local commercial leasing and anti-discrimination laws', 'Collect and organize application documents (e.g., business licenses, financials, entity records, references)', 'Verify basic information provided in the application (e.g., business operations, income sources, references)', 'Present complete application packages to the Landlord for review and final selection'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="application-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="application-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>
    <!-- 📃 Lease Preparation, LOI & Execution -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📃 Lease Preparation, LOI & Execution</h5>
        <div class="service-options">
            @foreach (['Coordinate lease negotiation with the Tenant or Tenant’s Agent', 'Collect and organize Letters of Intent (LOIs) or draft lease proposals', 'Draft or assist with execution of the final lease agreement using approved forms or templates', 'Provide and review required lease disclosures and addenda based on state or municipal requirements', 'Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties', 'Verify receipt of required deposits and track rent commencement and key lease dates to ensure move-in readiness'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="lease-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="lease-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <!-- 🚚 Move-In Support & Coordination -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">🚚 Move-In Support & Coordination</h5>
        <div class="service-options">
            @foreach (['Coordinate move-in date and key handoff logistics with the Tenant or Tenant’s Agent', 'Confirm completion of any agreed-upon pre-move-in repairs, cleaning, or improvements', 'Verify receipt of all required move-in funds and documents prior to occupancy (e.g., rent, security deposit, insurance certificates)', 'Provide a utility setup checklist and local provider resources for the Tenant', 'Share a move-in checklist for documentation and property condition review', 'Assist with coordination of move-in logistics, including Certificate of Insurance (COI) and vendor access (as agreed)'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="movein-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="movein-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📑 Property Management </h5>
        <div class="service-options">

            @php

                $PropertyManagement = [
                    'Provide ongoing property management services throughout the lease term (rent collection, maintenance coordination, Tenant communications, lease enforcement, renewals, etc.)',
                ];

            @endphp
            @foreach ($PropertyManagement as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="Property-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="Property-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>

        <div class="alert alert-warning mt-3 p-2 small">
            <strong>⚖️ Note: </strong> Property management services are separate from leasing services. Agents
            typically charge an additional fee (monthly flat fee or percentage of rent) under a separate property
            management agreement. Availability and terms may vary by Agent and are subject to brokerage policies and
            state law.
        </div>
    </div>
    <!-- 💡 Leasing Strategy & Guidance -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">💡 Leasing Strategy & Guidance</h5>
        <div class="service-options">
            @foreach (['Provide a Comparable Lease Analysis with pricing recommendations based on similar properties, local vacancy trends, and current market conditions', 'Advise on lease types and structures (e.g., NNN, Modified Gross, Full Service) with general explanations of differences', 'Provide general guidance on Landlord obligations and Tenant rights under applicable commercial leasing laws', 'Provide general guidance on zoning, permitted uses, occupancy standards, or rent escalation terms'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="strategy-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="strategy-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

@endif

{{-- <!-- Custom Services Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">✍️ Additional Services
    </h5>
    <div class="service-options">
        <!-- Other Services Checkbox -->
        <div class="form-check service-item mb-3">
            <input class="form-check-input" type="checkbox" wire:model="other_services_enabled"
                id="other-services-checkbox">
            <label class="form-check-label" for="other-services-checkbox">
                Other – Specify additional services as needed or requested by the landlord
            </label>
        </div>

        <!-- Other Services Input (shown when checkbox is checked) -->
        @if ($other_services_enabled)
            <div class="mb-3">
                <label for="other-services-input" class="form-label">
                    Specify additional services requested:
                </label>
                <textarea class="form-control" id="other-services-input" wire:model="other_services" rows="3"
                    placeholder="Enter additional services not listed above (e.g., Rental license coordination, Employer housing outreach, Lease compliance assistance)"></textarea>
            </div>
        @endif

    </div>
</div> --}}

<!-- Custom Services Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">✍️ Additional Services</h5>

    <div class="service-options">
        <!-- Toggle -->
        <div class="form-check service-item mb-3">
            <input id="other-services-checkbox" type="checkbox" class="form-check-input"
                wire:model="other_services_enabled">
            <label class="form-check-label" for="other-services-checkbox">
                Other – Specify additional services as needed
            </label>
        </div>

        @if ($other_services_enabled)
            <div id="other-services-fieldset">
                @foreach ($other_services as $i => $value)
                    <div class="mb-3 service-entry" wire:key="other_service_{{ $i }}">
                        <label class="form-label" for="other-services-input-{{ $i }}">
                            Specify any additional services requested
                        </label>

                        <input id="other-services-input-{{ $i }}" type="text"
                            class="form-control mb-2 @error("other_services.$i") is-invalid @enderror"
                            placeholder="Enter additional services not listed above (e.g., Rental license coordination, Employer housing outreach, Lease compliance assistance)"
                            wire:model="other_services.{{ $i }}">

                        @error("other_services.$i")
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror

                        <button type="button" class="btn btn-danger btn-sm remove-service"
                            wire:click="removeService({{ $i }})">❌ Remove</button>
                    </div>
                @endforeach
            </div>

            <button type="button" class="btn btn-primary btn-sm" id="add-service-btn"
                wire:click="addServiceField">➕ Add Another Service</button>
        @endif
    </div>
</div>
<div class="alert alert-warning mt-3 p-2 small">
    <strong> ⚖️ Note: </strong> All services described above are provided within the scope of real estate brokerage
    duties as defined by state law. Services are administrative, advisory, or informational in nature and do not include
    legal, tax, accounting, or financial advice. Analyses are not formal appraisals or valuations. All services,
    including showings, lockbox access, signage, and marketing, are subject to applicable MLS, association, and/or local
    regulations. The Agent does not handle client funds outside of escrow or trust accounts as permitted by law. All
    information is relayed as provided by third parties (e.g., Tenant, Property Manager, Lender, Title, Escrow,
    Attorney, CPA, or other licensed professionals). The Landlord and/or their Attorney, CPA, or other licensed
    professional remain solely responsible for reviewing, confirming, and approving the accuracy, completeness, and
    compliance of all documents, disclosures, financial statements, lease agreements, and transfers. Additional services
    must remain within the scope of brokerage law and fiduciary duties as outlined in the signed brokerage agreement.
</div>
