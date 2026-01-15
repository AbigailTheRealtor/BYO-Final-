<h3>Services the Buyer Requests from Their Agent</h3>



<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>🤝 Select the services the Buyer would like the Agent to provide throughout the purchase process.
                Services are offered under a commission-based, full-service agreement, with the brokerage relationship
                type determined in accordance with state law. Selections here are for guidance only; the signed
                brokerage agreement governs the final scope of representation and compensation.
            </strong>
        </div>
    </div>
</div>



@if ($property_type == 'Residential')

    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📣 Buyer Criteria Marketing & Promotion</h5>
        <div class="service-options">
            @foreach (['Create a branded flyer summarizing the Buyer’s purchase criteria', 'Post the Buyer’s purchase criteria on Craigslist under the “Real Estate Wanted” section', 'Share the Buyer’s purchase criteria on Nextdoor in Neighborhood or Community Groups', 'Promote the Buyer’s purchase criteria on Facebook in Real Estate or Housing Groups', 'Share the Buyer’s purchase criteria on Instagram using posts, stories, or reels', 'Promote the Buyer’s purchase criteria on LinkedIn in Real Estate or Housing Groups', 'Upload a TikTok video summarizing the Buyer’s purchase criteria', 'Upload a YouTube video summarizing the Buyer’s purchase criteria', 'Launch a mass email campaign promoting the Buyer’s purchase criteria', 'Distribute branded postcards or flyers in the Buyer’s preferred neighborhoods', 'Launch hyperlocal digital ads targeting the Buyer’s preferred purchase areas'] as $service)
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

    <!-- Search & Property Matching Section -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">🔍 Property Search, Alerts & Matching</h5>
        <div class="service-options">
            @foreach (['Send email alerts with new listings from the MLS that match the Buyer’s purchase criteria', 'Search for off-market, pre-market, distressed, withdrawn, canceled, or expired properties that meet the Buyer’s purchase criteria', 'Communicate with the Seller’s Agent or Seller to confirm availability, purchase terms, and showing instructions', 'Evaluate properties with the Buyer and provide insights on pricing, terms, potential, and overall fit'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="search-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="search-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Showings & Virtual Tours Section -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">🏡 Property Showings & Virtual Tours</h5>
        <div class="service-options">
            @foreach (['Schedule and attend property showings with the Buyer', 'Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs', 'Preview properties on behalf of the Buyer upon request', 'Provide factual observations on property layout and condition'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="showings-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="showings-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Offer Preparation & Contract Coordination Section -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📝 Offer & Contract Coordination</h5>
        <div class="service-options">
            @foreach (['Draft and submit offers using state-approved purchase forms', 'Provide the Buyer with the necessary disclosure forms required by state or local law', 'Draft and deliver counteroffers and manage revisions to the purchase agreement', 'Negotiate price, deposits, and contingencies with the Seller’s Agent or Seller ', 'Manage communications with the Seller’s Agent or Seller', 'Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties', 'Assist with inspection-related negotiations and Buyer requests for repairs', 'Monitor contract milestones, contingency periods, and financing deadlines', 'Provide referrals to Attorneys, Title Companies, Escrow Professionals, or Lenders (referrals only — no endorsement or warranty is made)'] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="offer-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="offer-{{ Str::slug($service) }}">
                        {{ $service }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Transaction Management & Closing Support Section -->
    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📋 Closing Coordination & Transaction Management</h5>
        <div class="service-options">
            @foreach (['Review and provide the Buyer with Seller-supplied due diligence documentation, including property disclosures, inspection reports, HOA documents, and utility summaries (as available)', 'Coordinate scheduling for inspections, appraisals, and other requested evaluations', 'Coordinate with the Lender, Title, Escrow, and/or Attorney to prepare for Closing', 'Review the Settlement Statement for accuracy and coordinate with relevant parties if corrections are needed ', 'Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties', 'Schedule and confirm the Final Walkthrough', 'Schedule and confirm the Closing Appointment'] as $service)
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

<!-- Buyer Guidance & Strategy Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">💡 Buying Strategy & Guidance</h5>
    <div class="service-options">
        @foreach ([
            'Provide a Comparative Market Analysis (CMA) with pricing recommendations based on comparable sales, neighborhood trends, and current market conditions',
            'Provide general guidance on financing, loan options, property taxes, insurance, and escrow timelines',
            'Provide factual information about neighborhood characteristics, school zones, crime data, and local amenities using third-party sources',
            'Provide general guidance on inspection expectations, common repair requests, and contingency planning'
        ] as $service)
            <div class="form-check service-item">
                <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                    id="guidance-{{ Str::slug($service) }}">
                <label class="form-check-label" for="guidance-{{ Str::slug($service) }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>


@endif

@if ($property_type == 'Income')

<!-- Buyer Criteria Marketing & Promotion Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">
        📣 Buyer Criteria Marketing & Promotion
    </h5>
    <div class="service-options">
        @foreach ([
            'Create a branded flyer summarizing the Buyer’s purchase criteria',
            'Post the Buyer’s purchase criteria on Craigslist under the “Real Estate Wanted” section',
            'Share the Buyer’s purchase criteria on Nextdoor in Neighborhood or Community Groups',
            'Promote the Buyer’s purchase criteria on Facebook in Real Estate Investor or Multifamily Groups',
            'Share the Buyer’s purchase criteria on Instagram using posts, stories, or reels',
            'Promote the Buyer’s purchase criteria on LinkedIn in Investment or Property Management Groups',
            'Upload a TikTok video summarizing the Buyer’s purchase criteria',
            'Upload a YouTube video summarizing the Buyer’s purchase criteria',
            'Launch a mass email campaign promoting the Buyer’s purchase criteria',
            'Distribute branded postcards or flyers in the Buyer’s preferred neighborhoods',
            'Launch hyperlocal digital ads targeting the Buyer’s preferred purchase areas'
        ] as $service)
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
    <!-- Property Search, Alerts & Matching Section -->
<!-- Property Search, Alerts & Matching Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">🔍 Property Search, Alerts & Matching</h5>
    <div class="service-options">
        @foreach ([
            'Send email alerts with new listings that match the Buyer’s purchase criteria',
            'Search for off-market, pre-market, distressed, withdrawn, canceled, or expired properties that meet the Buyer’s purchase criteria',
            'Communicate with the Seller’s Agent or Sellers to confirm pricing, rental income, expenses, and showing instructions',
            'Evaluate investment properties with the Buyer and provide insights on cash flow, cap rates, and value-add potential'
        ] as $service)
            <div class="form-check service-item">
                <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                    id="search-{{ Str::slug($service) }}">
                <label class="form-check-label" for="search-{{ Str::slug($service) }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>


<!-- Property Showings & Virtual Tours Section -->
<div class="service-section mb-4">
<h5 class="section-header bg-info text-white p-2 mb-3">
    <span class="text-dark">🏘</span> Property Showings & Virtual Tours
</h5>    <div class="service-options">
        @foreach ([
            'Schedule and attend property showings with the Buyer',
            'Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs',
            'Preview properties on behalf of the Buyer upon request',
            'Provide observations on Tenant occupancy, building condition, and operating expenses'
        ] as $service)
            <div class="form-check service-item">
                <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                    id="showing-{{ Str::slug($service) }}">
                <label class="form-check-label" for="showing-{{ Str::slug($service) }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>

<!-- Offer & Contract Management Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">📝 Offer & Contract Management</h5>
    <div class="service-options">
        @foreach ([
            'Draft and submit offers using state-approved purchase forms',
            'Provide the Buyer with the necessary disclosure forms required by state or local law',
            'Draft and deliver counteroffers and manage revisions to the purchase agreement',
            'Negotiate price, deposits, and contingencies with the Seller’s Agent or Seller',
            'Manage communication with the Seller’s Agent or Seller',
            'Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties',
            'Assist with inspection-related negotiations and Buyer requests for repairs',
            'Monitor contract milestones, contingency periods, and financing deadlines',
            'Provide referrals to Attorneys, Title Companies, Escrow Professionals, Lenders, or 1031 Exchange Intermediaries (referrals only — no endorsement or warranty is made)'
        ] as $service)
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
<!-- Closing Coordination & Transaction Management Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">📋 Closing Coordination & Transaction Management</h5>
    <div class="service-options">
        @foreach ([
            'Review and provide due diligence documents such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)',
            'Coordinate with the Seller’s Agent, Buyer’s Lender, Title, Escrow, and/or Attorney to prepare for Closing',
            'Review the Settlement Statement for accuracy and coordinate with relevant parties if corrections are needed ',
            'Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties',
            'Schedule and confirm the Final Walkthrough',
            'Schedule and confirm the Closing Appointment'
        ] as $service)
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

<!-- Buying Strategy & Guidance Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">💡 Buying Strategy & Guidance</h5>
    <div class="service-options">
        @foreach ([
            'Provide a Comparative Market Analysis (CMA) with pricing recommendations, rental comps, and Cap Rate estimates',
            'Provide general guidance on financing options, rent control, property taxes, and Landlord responsibilities',
            'Provide factual information on rental demand, turnover rates, and submarket conditions using third-party sources',
            'Provide general guidance on due diligence steps, lease audits, and estoppel reviews'
        ] as $service)
            <div class="form-check service-item">
                <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                    id="strategy-{{ Str::slug($service) }}">
                <label class="form-check-label" for="strategy-{{ Str::slug($service) }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>




@endif
@if ($property_type == 'Commercial')

    <!-- Buyer Criteria Marketing & Promotion Section -->
    <div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">
        📣 Buyer Criteria Marketing & Promotion
    </h5>
    <div class="service-options">
        @foreach ([
            'Create a branded flyer summarizing the Buyer’s purchase criteria',
            'Post the Buyer’s criteria on Craigslist under “Real Estate Wanted – Commercial”',
            'Promote the Buyer’s criteria on Facebook in Commercial Real Estate or Investment Groups',
            'Share the Buyer’s criteria on Instagram using posts, stories, or reels',
            'Promote the Buyer’s criteria on LinkedIn in Commercial or Investment Groups',
            'Upload a TikTok video summarizing the Buyer’s purchase criteria',
            'Upload a YouTube video summarizing the Buyer’s purchase criteria',
            'Launch a mass email campaign promoting the Buyer’s purchase criteria',
            'Distribute branded postcards or flyers in the Buyer’s preferred purchase areas',
            'Launch hyperlocal or interest-based digital ad campaigns targeting desired commercial property types'
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


    <!-- Property Search, Alerts & Matching Section -->
    <div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">🔍 Property Search, Alerts & Matching</h5>
    <div class="service-options">
        @foreach ([
            'Send listing alerts from real estate platforms that match the Buyer’s purchase criteria.',
            'Search for off-market, pre-market, distressed, withdrawn, canceled, or expired listings that meet the Buyer’s criteria',
            'Communicate with the Seller’s Agent or Seller to confirm availability, purchase terms, and showing instructions',
            'Analyze building class, property zoning, income potential, and redevelopment opportunities'
        ] as $service)
            <div class="form-check service-item">
                <input class="form-check-input" type="checkbox" wire:model="services"
                    value="{{ $service }}" id="search-{{ Str::slug($service) }}">
                <label class="form-check-label" for="search-{{ Str::slug($service) }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>


    <!-- Property Showings & Virtual Tours Section -->
    <div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">🏢 Property Showings & Virtual Tours</h5>
    <div class="service-options">
        @foreach ([
            'Schedule and attend property showings with the Buyer',
            'Coordinate or conduct virtual showings via live video or recorded walkthroughs',
            'Preview properties on behalf of the Buyer upon request',
            'Provide insights on layout, access, visibility, Tenant mix, and surrounding infrastructure'
        ] as $service)
            <div class="form-check service-item">
                <input class="form-check-input" type="checkbox" wire:model="services"
                    value="{{ $service }}" id="showings-{{ Str::slug($service) }}">
                <label class="form-check-label" for="showings-{{ Str::slug($service) }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>


    <!-- Offer & Contract Management Section -->
    <div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">📝 Offer & Contract Management</h5>
    <div class="service-options">
        @foreach ([
            'Draft and submit offers using state-approved purchase agreements or Letters of Intent (LOIs)',
            'Provide the Buyer with the necessary disclosure forms required by state or local law',
            'Draft and deliver counteroffers and manage revisions to the purchase agreement',
            'Negotiate price, deposit structure, timelines, and contingencies with the Seller or Seller’s Agent',
            'Manage communication with the Seller’s Agent or Seller',
            'Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties',
            'Assist with due diligence negotiations, including repair requests or credits',
            'Monitor contract contingencies, including financing, estoppel review, lease audits, and environmental reports',
            'Provide referrals to Attorneys, Title Companies, Escrow Officers, Commercial Lenders, or 1031 Exchange Intermediaries (referrals only — no endorsement or warranty is made)'
        ] as $service)
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


    <!-- Closing Coordination & Transaction Management Section -->
   <div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">📋 Closing Coordination & Transaction Management</h5>
    <div class="service-options">
        @foreach ([
            'Coordinate inspections, appraisals, environmental assessments, and estoppel certificate collection as needed',
            'Review and request due diligence documentation such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)',
            'Coordinate with the Lender, Title Company, Escrow Officer, and/or Attorney to prepare for Closing',
            'Review the Settlement Statement for accuracy and coordinate with all parties if corrections are needed ',
            'Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties',
            'Schedule and confirm the Final Walkthrough',
            'Schedule and confirm the Closing Appointment'
        ] as $service)
            <div class="form-check service-item">
                <input class="form-check-input" type="checkbox" wire:model="services"
                    value="{{ $service }}" id="due-diligence-{{ Str::slug($service) }}">
                <label class="form-check-label" for="due-diligence-{{ Str::slug($service) }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>


    <!-- Buying Strategy & Guidance Section -->
   <div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">💡 Buying Strategy & Guidance</h5>
    <div class="service-options">
        @foreach ([
            'Provide a Comparative Market Analysis (CMA) with recent sales comps, lease comps, and an estimated value range',
            'Provide general guidance on zoning regulations, permitted uses, and rental income potential',
            'Provide factual data on traffic counts, commercial market trends, and area demographics using third-party sources',
            'Provide general guidance on lease types, contingency timelines, due diligence, and environmental risks'
        ] as $service)
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

    {{-- <div class="alert alert-info bg-light-info border-info mb-4">
        <div class="d-flex align-items-center">
            <i class="fas fa-info-circle me-2"></i>
            <div>
                These services are provided under a commission-based, full-service buyer representation agreement. The
                agent may assist with business search, site visits, offer preparation, document coordination, and
                closing support, as defined in the executed agreement. The scope of services and compensation are
                outlined in the agreement between the agent and buyer. Buyers are advised to consult a licensed attorney
                and CPA regarding legal, tax, and valuation aspects of business purchases.
            </div>
        </div>
    </div> --}}

    <!-- 📢 Buyer Criteria Marketing & Promotion -->
    <div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">
        📣 Buyer Criteria Marketing & Promotion
    </h5>
    <div class="service-options">
        @foreach ([
            'Create a branded flyer summarizing the Buyer’s purchase criteria',
            'Post the Buyer’s purchase criteria on Craigslist under “Business for Sale” or “Real Estate Wanted – Commercial”',
            'Promote the Buyer’s purchase criteria on Facebook in Business Opportunity or Franchise Groups',
            'Share the Buyer’s purchase criteria on Instagram using posts, stories, or reels',
            'Promote the Buyer’s purchase criteria on LinkedIn in Business, Commercial, or Startup Groups',
            'Upload a TikTok video summarizing the Buyer’s purchase criteria',
            'Upload a YouTube video summarizing the Buyer’s purchase criteria',
            'Launch a mass email campaign promoting the Buyer’s purchase criteria',
            'Distribute branded postcards or flyers in the Buyer’s preferred neighborhoods',
            'Launch hyperlocal digital ads targeting the Buyer’s preferred purchase areas'
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


    <!-- 🔍 Business Search, Alerts & Screening -->
    <div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">🔍 Business Search, Alerts & Matching</h5>
    <div class="service-options">
        @foreach ([
            'Send alerts for businesses that match the Buyer’s acquisition criteria from available business listing sources.',
            'Search for off-market, pre-market, distressed, or recently closed businesses that meet the Buyer’s criteria',
            'Communicate with the Seller’s Broker or Seller to confirm pricing, lease terms, licensing status, and showing availability',
            'Analyze financials, lease assignments, business licensing requirements, and overall market positioning'
        ] as $service)
            <div class="form-check service-item">
                <input class="form-check-input" type="checkbox" wire:model="services"
                    value="{{ $service }}" id="search-{{ Str::slug($service) }}">
                <label class="form-check-label" for="search-{{ Str::slug($service) }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>


    <!-- 🏢 Business Site Showings & Virtual Tours -->
    <div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">🏢 Property Showings & Virtual Tours</h5>
    <div class="service-options">
        @foreach ([
            'Schedule and attend property or business showings with the Buyer',
            'Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs',
            'Preview properties or business locations on behalf of the Buyer upon request',
            'Provide insights on foot traffic, customer base, operational setup, competitive advantages, and location dynamics'
        ] as $service)
            <div class="form-check service-item">
                <input class="form-check-input" type="checkbox" wire:model="services"
                    value="{{ $service }}" id="showings-{{ Str::slug($service) }}">
                <label class="form-check-label" for="showings-{{ Str::slug($service) }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>


    <!-- 📝 Offer Preparation & LOI Support -->
    <div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">📝 Offer & Contract Management</h5>
    <div class="service-options">
        @foreach ([
            'Draft and submit offers using appropriate business purchase or asset sale forms',
            'Provide the Buyer with required disclosures, financial summaries, and documentation made available by the Seller',
            'Negotiate terms such as purchase price, deposit structure, inventory inclusions, non-compete agreements, and contingencies',
            'Draft and deliver counteroffers and manage revisions to the purchase agreement',
            'Manage communication with the Seller’s Broker or Seller',
            'Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties',
            'Assist with due diligence coordination, Buyer-requested repairs, and adjustment negotiations',
            'Monitor contingency periods, financing milestones, and deal approval timelines',
            'Provide referrals to Business Attorneys, CPAs, Escrow Officers, or Lenders (referrals only — no endorsement or warranty is made)'
        ] as $service)
            <div class="form-check service-item">
                <input class="form-check-input" type="checkbox" wire:model="services"
                    value="{{ $service }}" id="loi-{{ Str::slug($service) }}">
                <label class="form-check-label" for="loi-{{ Str::slug($service) }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>


    <!-- 📋 Due Diligence & Transaction Support -->
   <div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">📋 Closing Coordination & Transaction Management</h5>
    <div class="service-options">
        @foreach ([
            'Coordinate inspections, licensing verifications, lease assignments, and inventory counts',
            'Coordinate with Lenders, Attorneys, Escrow Officers, Title Companies, CPAs, and other involved parties to prepare for Closing',
            'Review the Settlement Statement or Closing Worksheet for accuracy and coordinate with all parties if corrections are needed ',
            'Confirm delivery of final executed documents, wire instructions, and business transition materials',
            'Schedule and confirm the Final Walkthrough',
            'Schedule and confirm the Closing Appointment'
        ] as $service)
            <div class="form-check service-item">
                <input class="form-check-input" type="checkbox" wire:model="services"
                    value="{{ $service }}" id="due-diligence-{{ Str::slug($service) }}">
                <label class="form-check-label" for="due-diligence-{{ Str::slug($service) }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>


    <!-- 💡 Business Acquisition Strategy & Support -->
    <div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">💡 Buying Strategy & Guidance</h5>
    <div class="service-options">
        @foreach ([
            'Provide a Comparative Market Review based on similar business sales, financial performance, and industry benchmarks',
            'Provide general guidance on licensing, zoning, SBA financing, registration steps, and transition timing',
            'Provide general guidance on due diligence preparation, key documents to review, and red flags during the acquisition process'
        ] as $service)
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

    {{-- <div class="alert alert-info bg-light-info border-info mb-4">
        <div class="d-flex align-items-center">
            <i class="fas fa-info-circle me-2"></i>
            <div>
                These services are provided under a commission-based, full-service buyer representation agreement. The
                agent may assist with locating vacant land, reviewing comparable properties, submitting offers, and
                coordinating the transaction through closing. The scope of services and compensation terms are outlined
                in the agreement between the buyer and agent. Buyers are encouraged to consult legal, zoning, or
                environmental professionals for specific land use, survey, or development-related guidance
            </div>
        </div>
    </div> --}}

    <!-- 📢 Buyer Criteria Marketing & Promotion -->
    <div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">
        📣 Buyer Criteria Marketing & Promotion
    </h5>
    <div class="service-options">
        @foreach ([
            'Create a branded flyer summarizing the Buyer’s purchase criteria',
            'Post the Buyer’s criteria on Craigslist under “Real Estate Wanted – Land”',
            'Share the Buyer’s criteria on Nextdoor in Neighborhood or Rural Groups',
            'Promote the Buyer’s criteria on Facebook in Land Buyers, Developers, or Homesteader Groups',
            'Share the Buyer’s criteria on Instagram using posts, stories, or reels',
            'Promote the Buyer’s criteria on LinkedIn in Land Acquisition or Investment Groups',
            'Upload a TikTok video summarizing the Buyer’s purchase criteria',
            'Upload a YouTube video summarizing the Buyer’s purchase criteria',
            'Launch a mass email campaign promoting the Buyer’s purchase criteria',
            'Distribute branded postcards or flyers in the Buyer’s preferred neighborhoods',
            'Launch hyperlocal digital ads targeting the Buyer’s preferred purchase areas'
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


    <!-- 🔍 Property Search, Alerts & Screening -->
    <div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">🔍 Property Search, Alerts & Matching</h5>
    <div class="service-options">
        @foreach ([
            'Send property alerts for land listings that match the Buyer’s goals from relevant real estate and land-specific platforms.',
            'Search for off-market, pre-market, distressed, withdrawn, canceled, or expired properties that meet the Buyer’s purchase criteria',
            'Communicate with the Seller’s Agent or Seller to confirm zoning, access, utilities, and pricing',
            'Evaluate development feasibility, land use restrictions, and agricultural potential with the Buyer'
        ] as $service)
            <div class="form-check service-item">
                <input class="form-check-input" type="checkbox" wire:model="services"
                    value="{{ $service }}" id="search-{{ Str::slug($service) }}">
                <label class="form-check-label" for="search-{{ Str::slug($service) }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>

    <!-- 🏡 Property Showings & Virtual Tours -->
    <div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">🏡 Property Showings & Virtual Tours</h5>
    <div class="service-options">
        @foreach ([
            'Schedule and attend land visits with the Buyer',
            'Coordinate or conduct virtual walkthroughs using maps, aerials, and site photos',
            'Preview parcels on behalf of the Buyer upon request',
            'Provide observations on topography, road frontage, and surrounding land uses'
        ] as $service)
            <div class="form-check service-item">
                <input class="form-check-input" type="checkbox" wire:model="services"
                    value="{{ $service }}" id="site-{{ Str::slug($service) }}">
                <label class="form-check-label" for="site-{{ Str::slug($service) }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>


    <!-- 📝 Offer Submission & Negotiation -->
    <div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">📜 Offer & Contract Management</h5>
    <div class="service-options">
        @foreach ([
            'Draft and submit offers using state-approved purchase forms',
            'Provide the Buyer with required state or local disclosure forms',
            'Draft and deliver counteroffers and manage revisions to the purchase agreement',
            "Negotiate price, deposits, and contingencies with the Seller's Agent or Seller",
            "Manage communication with the Seller's Agent or Seller",
            'Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed documents to all parties',
            'Assist with due diligence coordination, including survey review, soil testing, zoning checks, and permit verification',
            'Monitor contract milestones, contingency deadlines, and financing timelines',
            'Provide referrals to Attorneys, Title Companies, Escrow Officers, Surveyors, or Land Use Consultants (referrals only — no endorsement or warranty is made)'
        ] as $service)
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

    <!-- 📋 Due Diligence & Closing Coordination -->
   <div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">📋 Closing Coordination & Transaction Management</h5>
    <div class="service-options">
        @foreach ([
            'Coordinate surveys, appraisals, inspections, and environmental assessments',
            'Coordinate with the Lender, Title Company, Escrow Officer, and/or Attorney to prepare for Closing',
            'Review the Settlement Statement for accuracy and coordinate with all parties if corrections are needed ',
            'Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties',
            'Schedule and confirm the Final Walkthrough',
            'Schedule and confirm the Closing Appointment'
        ] as $service)
            <div class="form-check service-item">
                <input class="form-check-input" type="checkbox" wire:model="services"
                    value="{{ $service }}" id="due-{{ Str::slug($service) }}">
                <label class="form-check-label" for="due-{{ Str::slug($service) }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>


    <!-- 💡 Buyer Strategy & Advisory Support -->
    <div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">💡 Buying Strategy & Guidance</h5>
    <div class="service-options">
        @foreach ([
            'Provide a Comparative Market Analysis (CMA) with acreage comps, recent land sales, and price-per-acre benchmarks',
            'Provide general guidance on zoning, utilities, development potential, and environmental constraints',
            'Provide factual data on flood zones, wetlands, and land use maps using third-party sources',
            'Provide general guidance on feasibility timelines, inspection steps, and rural financing considerations'
        ] as $service)
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
{{-- <div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">✍️ Custom or Additional Services
    </h5>
    <div class="service-options">
        <!-- Other Services Checkbox -->
        <div class="form-check service-item mb-3">
            <input class="form-check-input" type="checkbox" wire:model="other_services_enabled"
                id="other-services-checkbox">
            <label class="form-check-label" for="other-services-checkbox">
                Other – Specify additional services as needed
            </label>
        </div>

        <!-- Other Services Input (shown when checkbox is checked) -->
        @if ($other_services_enabled)
            <div class="mb-3">
                <label for="other-services-input" class="form-label">
                    Specify additional services requested:
                </label>
                <textarea class="form-control" id="other-services-input" wire:model="other_services" rows="3"
                    placeholder="Please describe any additional services you require"></textarea>
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
            <input
                id="other-services-checkbox"
                type="checkbox"
                class="form-check-input"
                wire:model="other_services_enabled"
            >
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

                        <input
                            id="other-services-input-{{ $i }}"
                            type="text"
                            class="form-control mb-2 @error("other_services.$i") is-invalid @enderror"
                            placeholder="Specify any additional services requested"
                            wire:model="other_services.{{ $i }}"
                        >

                        @error("other_services.$i")
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror

                        <button
                            type="button"
                            class="btn btn-danger btn-sm remove-service"
                            wire:click="removeService({{ $i }})"
                        >❌ Remove</button>
                    </div>
                @endforeach
            </div>

            <button
                type="button"
                class="btn btn-primary btn-sm"
                id="add-service-btn"
                wire:click="addServiceField"
            >➕ Add Another Service</button>
        @endif
    </div>
</div>

<div class="alert alert-warning mt-3 p-2 small">
    <strong> ⚖️ Note:</strong> All services described above are provided within the scope of real estate brokerage
    duties as defined by state law. Services are administrative, advisory, or informational in nature and do not include
    legal, tax, accounting, or financial advice. Analyses are not formal appraisals or valuations. The Agent does not
    handle client funds outside of escrow or trust accounts as permitted by law. All information is relayed as provided
    by third parties (e.g., Seller, Lender, Title, Escrow, Attorney, CPA, or other licensed professionals). The Buyer
    and/or their Attorney, CPA, or other licensed professional remain solely responsible for reviewing, confirming, and
    approving the accuracy, completeness, and compliance of all documents, disclosures, financial statements, contracts,
    and transfers. Additional services must remain within the scope of brokerage law and fiduciary duties as outlined in
    the signed brokerage agreement.
</div>
