{{-- Income Property (2-4 Units) Services - Buyer Agent --}}

<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">Investor Criteria Marketing & Promotion</h5>
    <div class="service-options">
        @foreach ([
            'Create a branded flyer summarizing the Buyer\'s investment criteria for 2-4 unit income properties',
            'Post the Buyer\'s investment criteria on Craigslist under the "Real Estate Wanted - Investment" section',
            'Share the Buyer\'s investment criteria on Nextdoor in Real Estate Investment or Neighborhood Groups',
            'Promote the Buyer\'s investment criteria on Facebook in Real Estate Investment or Multi-Family Groups',
            'Share the Buyer\'s investment criteria on Instagram using posts, stories, or reels',
            'Promote the Buyer\'s investment criteria on LinkedIn in Real Estate Investment or Multi-Family Groups',
            'Upload a TikTok video summarizing the Buyer\'s investment criteria for income properties',
            'Upload a YouTube video summarizing the Buyer\'s investment criteria for income properties',
            'Launch a mass email campaign promoting the Buyer\'s investment criteria',
            'Distribute branded postcards or flyers in the Buyer\'s preferred investment neighborhoods',
            'Launch hyperlocal digital ads targeting the Buyer\'s preferred areas for 2-4 unit properties'
        ] as $service)
            <div class="form-check service-item">
                <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                    id="income-marketing-{{ Str::slug($service) }}">
                <label class="form-check-label" for="income-marketing-{{ Str::slug($service) }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>

<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">Income Property Search, Alerts & Matching</h5>
    <div class="service-options">
        @foreach ([
            'Send email alerts with new 2-4 unit listings from the MLS that match the Buyer\'s investment criteria',
            'Search for off-market, pre-market, distressed, withdrawn, canceled, or expired 2-4 unit properties that meet the Buyer\'s investment criteria',
            'Communicate with the Seller\'s Agent or Seller to confirm availability, purchase terms, rent rolls, and showing instructions',
            'Evaluate income properties with the Buyer and provide insights on pricing, rental income potential, cap rates, and overall investment fit'
        ] as $service)
            <div class="form-check service-item">
                <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                    id="income-search-{{ Str::slug($service) }}">
                <label class="form-check-label" for="income-search-{{ Str::slug($service) }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>

<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">Income Property Showings & Virtual Tours</h5>
    <div class="service-options">
        @foreach ([
            'Schedule and attend property showings of 2-4 unit buildings with the Buyer',
            'Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs',
            'Preview income properties on behalf of the Buyer upon request',
            'Provide factual observations on unit layouts, tenant occupancy, property condition, and income potential'
        ] as $service)
            <div class="form-check service-item">
                <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                    id="income-showings-{{ Str::slug($service) }}">
                <label class="form-check-label" for="income-showings-{{ Str::slug($service) }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>

<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">Offer & Contract Coordination</h5>
    <div class="service-options">
        @foreach ([
            'Draft and submit offers using state-approved purchase forms for 2-4 unit income properties',
            'Provide the Buyer with the necessary disclosure forms required by state or local law',
            'Draft and deliver counteroffers and manage revisions to the purchase agreement',
            'Negotiate price, deposits, contingencies, and seller credits with the Seller\'s Agent or Seller (as permitted under the agency agreement)',
            'Manage communications with the Seller\'s Agent or Seller',
            'Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties',
            'Assist with inspection-related negotiations and Buyer requests for repairs',
            'Monitor contract milestones, contingency periods, and financing deadlines',
            'Provide referrals to Attorneys, Title Companies, Escrow Professionals, Property Managers, or Lenders (referrals only - no endorsement or warranty is made)'
        ] as $service)
            <div class="form-check service-item">
                <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                    id="income-offer-{{ Str::slug($service) }}">
                <label class="form-check-label" for="income-offer-{{ Str::slug($service) }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>

<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">Closing Coordination & Transaction Management</h5>
    <div class="service-options">
        @foreach ([
            'Coordinate inspections, appraisals, rent roll verification, and lease audits',
            'Request and review tenant estoppel certificates and existing lease agreements',
            'Coordinate with the Lender, Title, Escrow, and/or Attorney to prepare for Closing',
            'Review the Settlement Statement for accuracy and coordinate with relevant parties if corrections are needed (no legal or financial advice provided)',
            'Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties',
            'Schedule and confirm the Final Walkthrough',
            'Schedule and confirm the Closing Appointment'
        ] as $service)
            <div class="form-check service-item">
                <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                    id="income-closing-{{ Str::slug($service) }}">
                <label class="form-check-label" for="income-closing-{{ Str::slug($service) }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>

<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">Investment Strategy & Guidance</h5>
    <div class="service-options">
        @foreach ([
            'Provide a Comparative Market Analysis (CMA) with pricing recommendations based on comparable 2-4 unit sales, rental comps, and current market conditions (for informational purposes only - not a formal appraisal)',
            'Provide rental income analysis including estimated gross rents, vacancy rates, and expense ratios for 2-4 unit properties',
            'Answer general questions about investment financing, loan options (conventional, FHA multi-family, portfolio), property taxes, insurance, and escrow timelines (non-legal guidance)',
            'Provide factual information about neighborhood characteristics, rental demand, and local amenities using third-party sources (no personal opinions or steering)',
            'Offer general guidance on inspection expectations, tenant-occupied property considerations, and contingency planning during the offer process (non-legal advice)'
        ] as $service)
            <div class="form-check service-item">
                <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                    id="income-guidance-{{ Str::slug($service) }}">
                <label class="form-check-label" for="income-guidance-{{ Str::slug($service) }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>
