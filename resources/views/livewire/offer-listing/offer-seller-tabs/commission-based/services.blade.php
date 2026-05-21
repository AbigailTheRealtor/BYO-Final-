<h3>Services the Seller Requests from Their Agent </h3>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>🤝 Select the services the Seller would like the Agent to provide throughout the selling process.
                Services are offered under a commission-based, full-service agreement, with the brokerage relationship
                type determined in accordance with state law. Selections here are for guidance only; the signed
                brokerage agreement governs the final scope of representation and compensation.
            </strong>
        </div>
    </div>
</div>
@if ($property_type == 'Residential')
    <!-- Property Marketing & Listing Promotion -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">
            📢 Property Marketing & Listing Promotion
        </h5>
        <div class="service-options">
            @foreach (['List the property on the local Multiple Listing Service (MLS)', 'Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)', 'Create a branded flyer featuring the property’s key highlights', 'Post the property on Facebook Marketplace', 'Post the property on Craigslist under the “Homes for Sale” category', 'Share the listing on Nextdoor in Neighborhood or Community Groups', 'Promote the listing on Facebook in Real Estate or Community Groups', 'Share the listing on Instagram using posts, stories, or reels', 'Promote the listing on LinkedIn in Professional or Real Estate Groups', 'Upload a TikTok video walkthrough of the property', 'Upload a YouTube video walkthrough of the property', 'Launch a mass email campaign promoting the listing', 'Distribute printed flyers or postcards in target geographic areas', 'Launch hyperlocal or interest-based digital ad campaigns promoting the listing'] as $service)
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

    <!-- Listing Preparation & Presentation -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">🛠️ Listing Preparation & Presentation</h5>
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
            <strong>Note:</strong> These services may be provided by the Agent or through a third-party vendor.
        </div>

    </div>

    <!-- Showings & Access Coordination -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">🏡 Showings & Access Coordination</h5>
        <div class="service-options">
            @foreach (['Ensure proper notice is provided if the property is occupied', 'Install a real estate sign on the property', 'Install a lockbox for Agent access', 'Schedule and attend showings with prospective Buyers', 'Coordinate showings with Buyer’s Agents', 'Collect and relay showing feedback to the Seller'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input service-checkbox" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="showings-{{ Str::slug($service) }}"
                        @if ($service === 'Host open houses') wire:change="$set('showOpenHouseInput', $event.target.checked)"
                       onclick="handleOpenHouseToggle(this)"
                       data-open-house-trigger @endif>
                    <label class="form-check-label" for="showings-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach

            <!-- Optional input for # of open houses -->
            {{-- <div class="form-group mt-2" id="openHouseInputContainer"
                style="display: {{ $showOpenHouseInput ? 'block' : 'none' }};">
                <input type="number" class="form-control" id="openHouseCount" wire:model="openHouseCount"
                    placeholder="Enter # of open houses to be hosted">
            </div> --}}
        </div>

        {{-- <div class="alert alert-warning mt-3 p-2 small">
            <strong>Note:</strong> All services, including showings, lockbox access, signage, and marketing, are
            subject to applicable MLS, association, and/or local regulations.
        </div> --}}

    </div>

    <!-- Offer & Contract Management -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📑 Offer & Contract Management</h5>
        <div class="service-options">
            @foreach (['Present all offers to the Seller and summarize key terms, pricing, and contingencies', 'Provide the Seller with the necessary disclosure forms required by state or local law', 'Negotiate price, terms, and contingencies with the Buyer’s Agent or Buyer', 'Manage communications with the Buyer’s Agent or Buyer', 'Draft and deliver counteroffers and manage revisions to the purchase agreement', 'Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties', 'Assist with inspection-related negotiations and Buyer requests for repairs', 'Monitor contract milestones, contingency periods, and financing deadlines', 'Provide referrals to Attorneys, Title Companies, and Escrow Professionals (referrals only — no endorsement or warranty is made)'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="contract-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="contract-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Closing Coordination -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">🧾 Closing Coordination & Transaction Management</h5>
        <div class="service-options">
            @foreach (['Coordinate scheduling for inspections, appraisals, and other requested evaluations', 'Coordinate with the Buyer’s Agent, Lender, Title, Escrow, and/or Attorney to prepare for Closing', 'Review the Settlement Statement and coordinate with all parties if corrections are needed', 'Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties', 'Schedule and confirm the Final Walkthrough', 'Schedule and confirm the Closing Appointment'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="closing-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="closing-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Selling Strategy & Guidance -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">💡 Selling Strategy & Guidance</h5>
        <div class="service-options">
            @foreach (['Provide a Comparative Market Analysis (CMA) with pricing recommendations based on comparable sales, neighborhood trends, and current market conditions', 'Provide general insight on local market trends, seasonal timing, and pricing thresholds', 'Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest', 'Provide general guidance on Seller obligations, required disclosures, and listing preparation'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="pricing-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="pricing-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

@endif

@if ($property_type == 'Income')

    <!-- 📢 Property Marketing & Listing Promotion -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📢 Property Marketing & Listing Promotion</h5>
        <div class="service-options">
            @foreach ([
        'List the property on the local Multiple Listing Service (MLS)',
        'Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)',
        'List the property on Crexi.com',
        'List the property on LoopNet.com',
        'Create a branded flyer with key rental data (e.g., unit mix, gross income, occupancy)',
        'Post the property on Craigslist under the “Multi-Family for Sale” category',
        'Share the listing on Nextdoor in Neighborhood or Community Groups',
        'Promote the listing on Facebook in Real Estate Investor or Multi-Family Buyer Groups',
        'Share the listing on Instagram using posts, stories, or reels',
        'Promote the listing on LinkedIn in Investment or Real Estate Groups',
        'Upload a TikTok video walkthrough of the property',
        'Upload a YouTube video walkthrough of the property',
        'Launch a mass email campaign promoting the listing',
        'Distribute printed flyers or postcards in target geographic areas',
        'Launch hyperlocal or interest-based digital ad campaigns promoting the listing',
    ] as $service)
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

    <!-- 🛠️ Listing Preparation & Investment Packaging -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">🛠️ Listing Preparation & Investment Packaging</h5>
        <div class="service-options">
            @foreach (['Conduct a property walkthrough and provide recommendations for listing readiness', 'Provide a custom listing preparation checklist', 'Assist with assembling an income property packet, including rent roll, lease copies, and an income/expense summary (as available)', 'Provide a visual consultation focused on interior layout, cleanliness, and unit presentation', 'Provide a curb appeal consultation focused on exterior maintenance and first impressions', 'Provide referrals to third-party vendors (e.g., cleaners, handypeople, electricians, landscapers). Vendor fees billed separately. Referrals only — no endorsement or warranty is made'] as $service)
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

    <!-- 📸 Photography, Video & Virtual Media -->
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
            <strong>Note:</strong> These services may be provided by the Agent or through a third-party vendor.
        </div>

    </div>

    <!-- Showings & Open Houses -->
    {{-- <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">🏘️ Showings & Tenant Coordination</h5>
        <div class="service-options">
            @foreach (['Schedule and host Buyer showings (with appropriate Tenant notice)', 'Host Broker tours', 'Install a lockbox for Agent access', 'Track showing activity and collect Buyer or Agent feedback', 'Assist with Tenant communication and showing logistics (administrative only)'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input service-checkbox" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="showings-{{ Str::slug($service) }}"
                        @if ($service === 'Host broker tours') wire:change="$set('showOpenHouseInput', $event.target.checked)"
                           onclick="handleOpenHouseToggle(this)"
                           data-open-house-trigger @endif>
                    <label class="form-check-label" for="showings-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach

            <!-- Optional input for # of open houses -->
            <div class="form-group mt-2" id="openHouseInputContainer"
                style="display: {{ $showOpenHouseInput ? 'block' : 'none' }};">
                <input type="number" class="form-control" id="openHouseCount" wire:model="openHouseCount"
                    placeholder="Enter # of broker tours">
            </div>
        </div>
    </div> --}}
    <!-- 🏘️ Showings & Access Coordination -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">🏘️ Showings & Access Coordination</h5>
        <div class="service-options">
            @foreach (['Respond to Buyer inquiries and screen for general qualifications', 'Provide Non-Disclosure Agreement (NDA) templates for confidential showings or document access', 'Ensure proper notice is provided if the property is occupied', 'Install a real estate sign on the property', 'Install a lockbox for Agent access', 'Schedule and attend showings with prospective Buyers', 'Coordinate showings with Buyer’s Agents', 'Collect and relay showing feedback to the Seller'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input service-checkbox" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="showings-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="showings-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>

        {{-- <div class="alert alert-warning mt-3 p-2 small">
            <strong>Note:</strong> All services, including showings, lockbox access, signage, and marketing, are
            subject to applicable MLS, association, and/or local regulations.
        </div> --}}

    </div>

    <!-- 📉 Offer & Contract Management -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3"> 📉 Offer & Contract Management</h5>
        <div class="service-options">
            @foreach (['Present all offers to the Seller and summarize key terms, pricing, and contingencies', 'Provide the Seller with the necessary disclosure forms required by state or local law', 'Negotiate deal structure, deposits, due diligence timelines, and Buyer contingencies', 'Draft and deliver counteroffers and manage revisions to the purchase agreement', 'Manage communication with the Buyer’s Agent or Buyers', 'Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties', 'Assist with inspection-related negotiations and Buyer requests for repairs', 'Monitor contract contingencies, including financing, lease audits, estoppel review, insurance, inspections, and environmental reports', 'Provide referrals to Attorneys, Title Companies, Escrow Officers, or 1031 Exchange Professionals. Referrals only — no endorsement or warranty is made'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="offer-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="offer-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <!-- 🧾 Closing Coordination & Transaction Management -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">🧾 Closing Coordination & Transaction Management</h5>
        <div class="service-options">
            @foreach (['Review and organize due diligence documentation such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)', 'Coordinate with the Buyer’s Agent, Buyer’s Lender, Title, Escrow, and/or Attorney to prepare for Closing', 'Review the Settlement Statement for accuracy and coordinate with relevant parties if corrections are needed', 'Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties', 'Schedule and confirm the Final Walkthrough', 'Schedule and confirm the Closing Appointment'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="closing-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="closing-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <!-- 💡 Selling Strategy & Guidance -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">💡 Selling Strategy & Guidance</h5>
        <div class="service-options">
            @foreach (['Provide a Comparative Market Analysis (CMA) with pricing insights based on recent income property sales, rental income trends, unit mix, and local investor activity', 'Assist in estimating Gross Rent Multiplier (GRM), Capitalization Rate (Cap Rate), or Price per Unit based on listing details and income property comparables ', 'Provide general insight on likely Investor Buyer behavior, common value drivers, and investment strategies', 'Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest', 'Provide general guidance on lease transfers, rent proration, security deposits, and possession timelines'] as $service)
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
@if ($property_type == 'Commercial')

    <!-- 📢 Property Marketing & Listing Promotion -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📢 Property Marketing & Listing Promotion</h5>
        <div class="service-options">
            @foreach (['List the property on the local Multiple Listing Service (MLS)', 'List the property on Crexi.com', 'List the property on LoopNet.com', 'Create a branded flyer summarizing the property’s investment highlights and key selling points', 'Post the property on Craigslist under the “Commercial for Sale” category', 'Promote the listing on Facebook in Commercial or Investor Real Estate Groups', 'Share the listing on Instagram using posts, stories, or reels', 'Promote the listing on LinkedIn in Professional, Real Estate, or Commercial Investment Groups', 'Upload a TikTok video walkthrough of the property', 'Upload a YouTube video walkthrough of the property', 'Launch a mass email campaign promoting the listing', 'Distribute printed flyers or postcards in target geographic areas', 'Launch hyperlocal or interest-based digital ad campaigns promoting the listing'] as $service)
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
    <!-- 🛠️ Listing Preparation & Asset Presentation -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">🛠️ Listing Preparation & Asset Presentation</h5>
        <div class="service-options">
            @foreach (['Conduct a property walkthrough and provide recommendations for listing readiness', 'Provide a visual consultation on interior layout, cleanliness, and overall presentation', 'Provide a curb appeal consultation focused on exterior appearance and first impressions', 'Provide referrals to third-party vendors such as cleaners, handypeople, electricians, and landscapers (vendor fees billed separately; referrals only — no endorsement or warranty is made)', 'Compile essential marketing materials such as rent rolls, lease summaries, financial statements, and operating data (as available)', 'Organize zoning documentation, surveys, and public record reports (as available)'] as $service)
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

    <!-- 📸 Photography, Video & Virtual Media -->
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
            <strong>Note:</strong> These services may be provided by the Agent or through a third-party vendor.
        </div>

    </div>

    <!-- 🏢 Showings & Access Coordination -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">🏢 Showings & Access Coordination</h5>
        <div class="service-options">
            @foreach (['Respond to Buyer inquiries and screen for general qualifications','Provide Non-Disclosure Agreement (NDA) templates for access to confidential documents or showings', 'Ensure proper notice is provided if the property is occupied', 'Install a real estate sign on the property', 'Install a lockbox for Agent access', 'Schedule and attend showings with prospective Buyers', 'Coordinate showings with Buyer’s Agents', 'Collect and relay showing feedback to the Seller'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input service-checkbox" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="showings-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="showings-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach

            <!-- Optional input for # of open houses -->
            {{-- <div class="form-group mt-2" id="openHouseInputContainer"
                style="display: {{ $showOpenHouseInput ? 'block' : 'none' }};">
                <input type="number" class="form-control" id="openHouseCount" wire:model="openHouseCount"
                    placeholder="Enter # of broker tours">
            </div> --}}
        </div>
{{--
        <div class="alert alert-warning mt-3 p-2 small">
            <strong>Note:</strong> All services, including showings, lockbox access, signage, and marketing, are
            subject to applicable MLS, association, and/or local regulations.
        </div> --}}

    </div>

    <!-- 📉 Offer & Contract Management -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📉 Offer & Contract Management</h5>
        <div class="service-options">
            @foreach (['Present all offers to the Seller and summarize key terms, pricing, and contingencies', 'Provide the Seller with the necessary disclosure forms required by state or local law', 'Coordinate Letter of Intent (LOI) submissions, counteroffers, and contract revisions', 'Negotiate deal terms such as pricing, deposit structure, closing timelines, and due diligence periods', 'Manage communication with the Buyer’s Agent or Buyer', 'Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties', 'Assist with inspection-related negotiations and Buyer requests for repairs or credits', 'Monitor contract contingencies, including financing, estoppel review, lease audits, and environmental reports', 'Provide referrals to Attorneys, Title Companies, Escrow Officers, or 1031 Exchange Professionals (referrals only — no endorsement or warranty is made)'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="offer-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="offer-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <!-- 🧾 Closing Coordination & Transaction Management -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">🧾 Closing Coordination & Transaction Management</h5>
        <div class="service-options">
            @foreach (['Coordinate inspections, appraisals, and estoppel certificate delivery with the Buyer’s Agent or Buyer, as applicable', 'Provide due diligence documentation such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)', 'Coordinate with the Buyer’s Agent, Lender, Title, Escrow, and/or Attorney to prepare for Closing', 'Review the Settlement Statement and coordinate with all parties if corrections are needed', 'Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties', 'Schedule and confirm the Final Walkthrough', 'Schedule and confirm the Closing Appointment'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="closing-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="closing-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <!-- 💡 Selling Strategy & Guidance -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">💡 Selling Strategy & Guidance</h5>
        <div class="service-options">
            @foreach (['Provide a Comparative Market Analysis (CMA) with pricing insights based on recent commercial property sales, rental income trends, market cap rates, and investor activity', 'Assist in estimating Capitalization Rate (Cap Rate), Price per Square Foot, or Gross Rent Multiplier (GRM) based on listing details and commercial comparables', 'Provide general insight on likely Buyer types (e.g., Owner-User, Investor, 1031 Exchange Buyer), common value drivers, and investment strategies', 'Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest', 'Provide general guidance on lease structures, expense ratios, and Tenant impacts'] as $service)
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
@if ($property_type == 'Business')

    <!-- 📢 Business Marketing & Listing Promotion -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📢 Business Marketing & Listing Promotion</h5>
        <div class="service-options">
            @foreach ([
        'List the Business Opportunity on the local Multiple Listing Service (MLS)',
        'List the Business Opportunity on Crexi.com ',
        'List the Business Opportunity on LoopNet.com ',
        'List the Business Opportunity on BizBuySell.com ',
        'List the Business Opportunity on BizQuest.com',
        'List the Business Opportunity on BusinessesForSale.com',
        'Create a branded flyer summarizing the Business’s key features (e.g., industry, cash flow, assets)',
        'Post the Business Opportunity on Craigslist under the “Business for Sale” category',
        'Promote the listing on Facebook in Business Buyer, Franchise, or Investor Groups',
        'Share the listing on Instagram using posts, stories, or reels',
        'Promote the listing on LinkedIn in Business Acquisition, Startup, or Investor Groups',
        'Upload a TikTok video summarizing the Business Opportunity',
        'Upload a YouTube video summarizing the Business Opportunity',
        'Launch a mass email campaign promoting the listing',
        'Distribute printed flyers or postcards in target geographic areas',
        'Launch hyperlocal or interest-based digital ad campaigns promoting the listing',
    ] as $service)
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

    <!-- 🛠️ Listing Preparation & Confidential Marketing -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">🛠️ Listing Preparation & Confidential Marketing</h5>
        <div class="service-options">
            @foreach (['Conduct a preliminary Seller consultation to gather details about the Business’s operations, assets, and goals', 'Provide a business sale checklist to collect financials, licenses, lease terms, and key operational details', 'Assist with preparing a non-confidential teaser or executive summary for marketing purposes', 'Organize internal documentation such as profit and loss statements, balance sheets, FF&E summaries, inventory lists, and staffing overviews (as available)', 'Refer third-party professionals such as valuation experts, accountants, or financial consultants, if requested (referrals only — no endorsement or warranty is made)', 'Compile essential marketing materials including business overviews, location descriptions, asset lists, and financial summaries'] as $service)
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

    <!-- 📸 Photography, Video & Virtual Media -->
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

                        @foreach (['Basic edits (brightness, contrast, cropping)', 'Twilight conversion (convert daytime photo to sunset look)', 'Object removal (e.g., cars, trash cans, furniture)', 'Virtual twilight photography', 'Color correction or sky replacement', 'Other'] as $enhancement)
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
            <strong>Note:</strong> These services may be provided by the Agent or through a third-party vendor.
        </div>

    </div>

    <!-- 🏢 Showings & Access Coordination -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">🏢 Showings & Access Coordination</h5>
        <div class="service-options">
            @foreach (['Respond to Buyer inquiries and screen for general qualifications', 'Provide Non-Disclosure Agreement (NDA) templates for confidential showings or document access', 'Ensure proper notice is provided if the property or business premises is occupied', 'Install a real estate sign on the property', 'Install a lockbox for Agent access', 'Schedule and attend showings with prospective Buyers', 'Coordinate showings with Buyer’s Agents', 'Coordinate directly with Tenant(s) or business staff to arrange access for showings', 'Collect and relay showing feedback to the Seller'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input service-checkbox" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="showings-{{ Str::slug($service) }}"
                        @if ($service === 'Schedule and attend showings with prospective Buyers') wire:change="$set('showOpenHouseInput', $event.target.checked)"
                    onclick="handleOpenHouseToggle(this)"
                    data-open-house-trigger @endif>
                    <label class="form-check-label" for="showings-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach

            <!-- Optional input for # of open houses -->
            {{-- <div class="form-group mt-2" id="openHouseInputContainer"
                style="display: {{ $showOpenHouseInput ? 'block' : 'none' }};">
                <input type="number" class="form-control" id="openHouseCount" wire:model="openHouseCount"
                    placeholder="Enter # of broker tours">
            </div> --}}
        </div>

        {{-- <div class="alert alert-warning mt-3 p-2 small">
            <strong>Note:</strong> All services, including showings, lockbox access, signage, and marketing, are
            subject to applicable MLS, association, and/or local regulations.
        </div> --}}

    </div>

    <!-- 📉 Offer & Contract Management -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📉 Offer & Contract Management</h5>
        <div class="service-options">
            @foreach ([
        'Present all Letters of Intent (LOIs) or formal offers to the Seller and summarize key deal terms',
        'Provide the Seller with the necessary disclosure forms required by state or local law',
        'Negotiate deal terms such as purchase price, deposit structure, contingencies, transition period, and asset allocation',
        'Coordinate revisions, counteroffers, and ongoing communication with the Buyer or their representatives',
        'Manage communication with the Buyer’s Broker or Buyer',
        'Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties',
        'Monitor contract contingencies and organize delivery of due diligence materials such as leases, vendor contracts, tax filings, and financial statements',
        'Refer the Seller to legal counsel for formal contract drafting and execution (referrals only — no legal advice provided)',
        'Provide referrals to Business Attorneys, Escrow Officers, or Business Transfer Specialists (referrals only — no endorsement or warranty is made)',
    ] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="deal-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="deal-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <!-- 📃 Closing Coordination & Transaction Management -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📃 Closing Coordination & Transaction Management</h5>
        <div class="service-options">
            @foreach (['Coordinate Buyer inspections, management interviews, and site visits as applicable', 'Provide a transaction checklist and track key deadlines throughout the escrow period', 'Coordinate with the Buyer’s Attorney, Escrow Officer, or designated Closing Facilitator', 'Review the Settlement Statement and coordinate corrections with relevant parties', 'Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties', 'Schedule and confirm the Final Walkthrough', 'Schedule and confirm the Closing Appointment'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="closing-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="closing-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <!-- 💡 Selling Strategy & Guidance -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">💡 Selling Strategy & Guidance</h5>
        <div class="service-options">
            @foreach (['Provide a business market overview with insights from recent comparable listings','Identify likely Buyer types (e.g., Owner-Operator, Investor, Franchisee) and discuss common deal structures (e.g., asset sale, stock sale)','Provide general insight on common value drivers such as cash flow, recurring revenue, transferable licenses, and key staff retention','Provide general guidance on operational transition timelines, staff notifications, lease assignments, and post-sale training periods','Provide referrals to business valuation, accounting, or legal professionals (referrals only — no endorsement or warranty is made)'] as $service)
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
@if ($property_type == 'Vacant Land')

    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📢 Property Marketing & Listing Promotion</h5>
        <div class="service-options">
            @foreach ([
        'List the property in the local Multiple Listing Service (MLS)',
        'Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)',
        'List the property on LandWatch.com',
        'List the property on Land.com',
        'List the property on LandAndFarm.com',
        'Create a branded flyer highlighting lot features, zoning, and potential use',
        'Post the listing on Facebook Marketplace',
        'Post the listing on Craigslist under the “Land for Sale” category',
        'Share the listing on Nextdoor in Neighborhood or Rural Groups',
        'Promote the listing on Facebook in Land Buyers, Developers, or Homesteader Groups',
        'Share the listing on Instagram using posts, stories, or reels',
        'Promote the listing on LinkedIn in Land Acquisition or Investment Groups',
        'Upload a TikTok video summarizing the land opportunity',
        'Upload a YouTube video summarizing the land opportunity (e.g., drone tour, narrated overview)',
        'Launch a mass email campaign promoting the listing Distribute printed flyers or postcards in target geographic areas',
        'Launch hyperlocal or interest-based digital ad campaigns promoting the listing',
    ] as $service)
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

    <!-- 🛠️ Listing Preparation & Research -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">🛠️ Listing Preparation & Research</h5>
        <div class="service-options">
            @foreach (['Provide a checklist to gather parcel data (e.g., APN, lot size, zoning, utilities, and access)', 'Assist with collecting public records, flood zone data, and land use information (as available)', 'Provide referrals to surveyors, soil testers, or land service professionals (referrals only — no endorsement or warranty is made)'] as $service)
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

    <div class="service-section mb-4" wire:key="seller-vacant-land-media">
        <h5 class="section-header bg-info text-white p-2 mb-3">📸 Photography, Video & Virtual Media</h5>
        <div class="service-options">
            @foreach (['Provide professional property photography', 'Provide aerial (drone) photography (subject to FAA Part 107 compliance)', 'Provide a video overview or narrated walkthrough', 'Provide a 3D virtual tour (if applicable)', 'Provide digital enhancements to media assets', 'Provide a parcel map, topographical image, or plot plan (non-certified; for marketing purposes only)'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input service-checkbox" type="checkbox" wire:model="services"
                        id="vl-media-{{ Str::slug($service) }}" value="{{ $service }}">
                    <label class="form-check-label" for="vl-media-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>

                @if ($service === 'Provide digital enhancements to media assets')
                    <div class="enhancement-options ms-4 mt-2 mb-3" wire:key="vacant-land-digital-enhancements"
                        style="display: {{ $showEnhancements ? 'block' : 'none' }};">
                        <div class="form-text mb-2">Select enhancement types:</div>

                        @foreach (['Basic edits (brightness, contrast, cropping)', 'Twilight conversion', 'Object removal (e.g., clutter, signage)', 'Sky replacement or color correction', 'Virtual twilight effect', 'Other'] as $enhancement)
                            <div class="form-check">
                                <input class="form-check-input enhancement-checkbox" type="checkbox"
                                    wire:model="photo_enhancements" value="{{ $enhancement }}"
                                    id="vacant-enhancement-{{ Str::slug($enhancement) }}">
                                <label class="form-check-label" for="vacant-enhancement-{{ Str::slug($enhancement) }}">
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
            <strong>Note:</strong> Services may be provided by the Agent or by a third-party vendor.
        </div>

    </div>

    <!-- 🏡 Showings & Access Coordination -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">🏡 Showings & Access Coordination</h5>
        <div class="service-options">
            @foreach (['Install a real estate sign on the property', 'Schedule and attend showings with prospective Buyers', 'Coordinate showings with Buyer’s Agents', 'Collect and relay showing feedback to the Seller'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="showing-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="showing-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>

        {{-- <div class="alert alert-warning mt-3 p-2 small">
            <strong>Note:</strong> All services, including showings, signage, and marketing, are subject to
            applicable MLS, association, and/or local regulations.
        </div> --}}

    </div>

    <!-- 📉 Offer & Contract Management -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📉 Offer & Contract Management</h5>
        <div class="service-options">
            @foreach (['Present all offers to the Seller and summarize key terms, pricing, and contingencies', 'Provide the Seller with the necessary disclosure forms required by state or local law', 'Negotiate price, due diligence timelines, and closing terms', 'Draft and deliver counteroffers and manage revisions to the purchase agreement', 'Manage communication with the Buyer’s Agent or Buyer', 'Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties', 'Monitor contract contingencies, including survey, zoning verification, financing, and environmental reviews', 'Provide referrals to Attorneys, Title Companies, Escrow Officers, or Land Use Professionals (referrals only — no endorsement or warranty is made)'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="contract-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="contract-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <!-- 📃 Closing Coordination & Transaction Management -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📃 Closing Coordination & Transaction Management</h5>
        <div class="service-options">
            @foreach (['Coordinate surveys, site visits, or environmental access with the Buyer or Buyer’s Agent, as applicable', 'Coordinate with Title, Escrow, and/or Attorney to prepare for Closing', 'Review the Settlement Statement and coordinate with all parties if corrections are needed', 'Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties', 'Schedule and confirm the Final Walkthrough', 'Schedule and confirm the Closing Appointment'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services"
                        value="{{ $service }}" id="closing-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="closing-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <hr> <!-- Horizontal line for separation -->

    <!-- 💡 Selling Strategy & Guidance -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">💡 Selling Strategy & Guidance</h5>
        <div class="service-options">
            @foreach (['Provide a Comparative Market Analysis (CMA) with pricing recommendations based on recent land sales, zoning categories, and location-based trends','Provide general insight on permitted uses, utility access, parcel features, and Buyer demand in the area','Recommend adjustments to pricing or marketing strategy if the land is not receiving sufficient interest','Provide general guidance on Seller obligations, disclosure requirements, and listing preparation'] as $service)
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
                            placeholder="Enter additional services not listed above (e.g., Estate Sale Coordination, senior transition assistance, pre-listing contractor coordination)"
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
    <strong> ⚖️ Note:</strong> All services described above are provided within the scope of real estate brokerage
    duties as defined by state law. Services are administrative, advisory, or informational in nature and do not include
    legal, tax, accounting, or financial advice. Analyses are not formal appraisals or valuations. All services,
    including showings, lockbox access, signage, and marketing, are subject to applicable MLS, association, and/or local
    regulations. The Agent does not handle client funds outside of escrow or trust accounts as permitted by law. All
    information is relayed as provided by third parties (e.g., Buyer, Lender, Title, Escrow, Attorney, CPA, or other
    licensed professionals). The Seller and/or their Attorney, CPA, or other licensed professional remain solely
    responsible for reviewing, confirming, and approving the accuracy, completeness, and compliance of all documents,
    disclosures, financial statements, contracts, and transfers. Additional services must remain within the scope of
    brokerage law and fiduciary duties as outlined in the signed brokerage agreement.
</div>

