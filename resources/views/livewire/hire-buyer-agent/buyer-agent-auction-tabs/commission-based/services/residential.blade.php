{{-- Residential Property Services - Buyer Agent --}}

<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">Buyer Criteria Marketing & Promotion</h5>
    <div class="service-options">
        @foreach ([
            'Create a branded flyer summarizing the Buyer\'s purchase criteria',
            'Post the Buyer\'s purchase criteria on Craigslist under the "Real Estate Wanted" section',
            'Share the Buyer\'s purchase criteria on Nextdoor in Neighborhood or Community Groups',
            'Promote the Buyer\'s purchase criteria on Facebook in Real Estate or Housing Groups',
            'Share the Buyer\'s purchase criteria on Instagram using posts, stories, or reels',
            'Promote the Buyer\'s purchase criteria on LinkedIn in Real Estate or Housing Groups',
            'Upload a TikTok video summarizing the Buyer\'s purchase criteria',
            'Upload a YouTube video summarizing the Buyer\'s purchase criteria',
            'Launch a mass email campaign promoting the Buyer\'s purchase criteria',
            'Distribute branded postcards or flyers in the Buyer\'s preferred neighborhoods',
            'Launch hyperlocal digital ads targeting the Buyer\'s preferred purchase areas'
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

<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">Property Search, Alerts & Matching</h5>
    <div class="service-options">
        @foreach ([
            'Send email alerts with new listings from the MLS that match the Buyer\'s purchase criteria',
            'Search for off-market, pre-market, distressed, withdrawn, canceled, or expired properties that meet the Buyer\'s purchase criteria',
            'Communicate with the Seller\'s Agent or Seller to confirm availability, purchase terms, and showing instructions',
            'Evaluate properties with the Buyer and provide insights on pricing, terms, potential, and overall fit'
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

<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">Property Showings & Virtual Tours</h5>
    <div class="service-options">
        @foreach ([
            'Schedule and attend property showings with the Buyer',
            'Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs',
            'Preview properties on behalf of the Buyer upon request',
            'Provide factual observations on property layout and condition'
        ] as $service)
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

<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">Offer & Contract Coordination</h5>
    <div class="service-options">
        @foreach ([
            'Draft and submit offers using state-approved purchase forms',
            'Provide the Buyer with the necessary disclosure forms required by state or local law',
            'Draft and deliver counteroffers and manage revisions to the purchase agreement',
            'Negotiate price, deposits, and contingencies with the Seller\'s Agent or Seller (as permitted under the agency agreement)',
            'Manage communications with the Seller\'s Agent or Seller',
            'Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties',
            'Assist with inspection-related negotiations and Buyer requests for repairs',
            'Monitor contract milestones, contingency periods, and financing deadlines',
            'Provide referrals to Attorneys, Title Companies, Escrow Professionals, or Lenders (referrals only - no endorsement or warranty is made)'
        ] as $service)
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

<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">Closing Coordination & Transaction Management</h5>
    <div class="service-options">
        @foreach ([
            'Coordinate inspections, appraisals, and lease audits (if applicable)',
            'Coordinate with the Lender, Title, Escrow, and/or Attorney to prepare for Closing',
            'Review the Settlement Statement for accuracy and coordinate with relevant parties if corrections are needed (no legal or financial advice provided)',
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

<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">Buying Strategy & Guidance</h5>
    <div class="service-options">
        @foreach ([
            'Provide a Comparative Market Analysis (CMA) with pricing recommendations based on comparable sales, neighborhood trends, and current market conditions (for informational purposes only - not a formal appraisal)',
            'Answer general questions about financing, loan options, property taxes, insurance, and escrow timelines (non-legal guidance)',
            'Provide factual information about neighborhood characteristics, school zones, crime data, and local amenities using third-party sources (no personal opinions or steering)',
            'Offer general guidance on inspection expectations, common repair requests, and contingency planning during the offer process (non-legal advice)'
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
