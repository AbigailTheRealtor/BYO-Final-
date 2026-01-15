<h3>Offered Services</h3>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>🤝 Select or review the services the Agent will provide to the Tenant throughout the leasing process. The services previously selected by the Tenant are pre-checked below. The Agent may edit, add, or remove services as needed to match what the Agent offers. Services are offered under a commission-based, full-service agreement, with the brokerage relationship type determined in accordance with state law. Your selections help outline the intended scope of services, but the signed brokerage agreement governs the final scope of representation and compensation.
            </strong>
        </div>
    </div>
</div>
@if ($property_type === 'Residential Property')
    <!-- Marketing Services Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">📢 Tenant Criteria Marketing & Promotion</h5>
    <div class="service-options">
        @foreach ([
            'Create a branded flyer summarizing the Tenant’s rental criteria',
            'Post the Tenant’s rental criteria on Craigslist under the “Real Estate Wanted” section',
            'Share the Tenant’s rental criteria on Nextdoor in Neighborhood or Community Groups',
            'Promote the Tenant’s rental criteria on Facebook in Rental or Housing Groups',
            'Share the Tenant’s rental criteria on Instagram using posts, stories, or reels',
            'Promote the Tenant’s rental criteria on LinkedIn in Real Estate or Housing Groups',
            'Upload a TikTok video summarizing the Tenant’s rental criteria',
            'Upload a YouTube video summarizing the Tenant’s rental criteria',
            'Launch a mass email campaign promoting the Tenant’s rental criteria',
            'Distribute branded postcards or flyers in the Tenant’s preferred neighborhoods',
            'Launch hyperlocal digital ads targeting the Tenant’s preferred rental areas'
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
<!-- Search & Property Matching Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">🔍 Property Search, Alerts & Matching</h5>
    <div class="service-options">
        @foreach ([
            'Send email alerts with new listings from the MLS that match the Tenant’s rental criteria',
            'Search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the Tenant’s rental criteria',
            'Communicate with the Landlord’s Agent, Landlord, or Property Manager to confirm availability, lease terms, and showing instructions',
            'Evaluate properties with the Tenant and provide insights on pricing, lease terms, and overall fit'
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
<!-- Showings & Virtual Tours Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">🏡 Property Showings & Virtual Tours</h5>
    <div class="service-options">
        @foreach ([
            'Schedule and attend property showings with the Tenant',
            'Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs',
            'Preview properties on behalf of the Tenant upon request',
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
<!-- Tenant Application Support Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">📝 Tenant Application Support</h5>
    <div class="service-options">
        @foreach ([
            'Provide the Tenant with application instructions or links to an online rental application platform',
            'Gather and organize required supporting documents (e.g., identification, income verification, reference letters)',
            'Submit complete and organized application packages to the Landlord’s Agent, Landlord, or Property Manager for review',
            'Answer questions about the application process, screening timelines, and required documentation'
        ] as $service)
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
<!-- Lease Preparation & Execution Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">📃 Lease Preparation & Execution</h5>
    <div class="service-options">
        @foreach ([
            'Review lease offers and assist the Tenant in preparing questions or requested changes',
            'Coordinate lease negotiation with the Landlord’s Agent, Landlord, or Property Manager',
            'Assist with completing required lease disclosures and reviewing key lease terms',
            'Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties',
        ] as $service)
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
<!-- Move-In Support & Coordination Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">🚚 Move-In Support & Coordination</h5>
    <div class="service-options">
        @foreach ([
            'Coordinate move-in date and key handoff logistics with the Landlord’s Agent, Landlord or Property Manager',
            'Confirm completion of any agreed-upon pre-move-in cleaning or repairs',
            'Provide a utility setup checklist and local provider resources',
            'Share a move-in checklist for documentation and property condition review',
            'Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods'
        ] as $service)
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
<!-- Leasing Strategy & Guidance Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">💡 Leasing Strategy & Guidance</h5>
    <div class="service-options">
        @foreach ([
            'Provide a Rental Market Analysis (RMA) with pricing insights based on comparable rentals, neighborhood trends, and current market conditions',
            'Advise on lease types and structures (e.g., month-to-month, annual, furnished, lease-option)',
            'Provide general guidance on Tenant rights and Landlord responsibilities under state law',
            'Provide general guidance on lease clauses, payment terms, and renewal options',
        ] as $service)
            <div class="form-check service-item">
                <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                    id="leasing-{{ Str::slug($service) }}">
                <label class="form-check-label" for="leasing-{{ Str::slug($service) }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>
@endif
@if ($property_type === 'Commercial Property')
<!-- Tenant Criteria Marketing & Promotion Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">📢 Tenant Criteria Marketing & Promotion</h5>
    <div class="service-options">
        @foreach ([
            'Create a branded flyer summarizing the Tenant’s leasing criteria',
            'Post the Tenant’s leasing criteria on Craigslist under the “Office/Commercial” or “Retail” section',
            'Promote the Tenant’s leasing criteria on Facebook in Commercial Leasing or Business Groups',
            'Share the Tenant’s leasing criteria on Instagram using posts, stories, or reels',
            'Promote the Tenant’s leasing criteria on LinkedIn in Professional, Real Estate, or Commercial Investment Groups',
            'Upload a TikTok video summarizing the Tenant’s leasing criteria',
            'Upload a YouTube video summarizing the Tenant’s leasing criteria',
            'Launch a mass email campaign promoting the Tenant’s leasing criteria',
            'Distribute branded postcards or flyers in the Tenant’s preferred neighborhoods',
            'Launch hyperlocal digital ads targeting the Tenant’s preferred leasing areas'
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
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">🔍 Property Search, Alerts & Matching</h5>
    <div class="service-options">
        @foreach ([
            'Send listing alerts from real estate platforms that match the Tenant's leasing criteria',
            'Search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the Tenant’s rental criteria',
            'Communicate with the Landlord’s Agent, Landlord, or Property Manager to confirm availability, lease terms, and showing instructions',
            'Evaluate properties for layout efficiency, building specs, logistics, zoning fit, and operational alignment'
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
    <h5 class="section-header bg-info text-white p-2 mb-3">🏢 Property Showings & Virtual Tours</h5>
    <div class="service-options">
        @foreach ([
            'Schedule and attend property tours with the Tenant',
            'Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs',
            'Preview properties on behalf of the Tenant upon request',
            'Provide factual notes on layout, access, parking, visibility, and other operational considerations'
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
<!-- Tenant Application Support Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">📝 Tenant Application Support</h5>
    <div class="service-options">
        @foreach ([
            'Provide the Tenant with application instructions or links to online platforms',
            'Gather and organize required supporting documents (e.g., business licenses, financials, references)',
            'Submit complete and organized application packages to the Landlord’s Agent, Landlord, or Property Manager'
        ] as $service)
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
<!-- Lease Preparation, LOI & Execution Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">📃 Lease Preparation, LOI & Execution</h5>
    <div class="service-options">
        @foreach ([
            'Draft or assist with preparing a Letter of Intent (LOI) summarizing the Tenant’s business needs and proposed terms',
            'Assist with negotiating rent, CAM, lease term, TI allowance, exclusivity clauses, renewal options, and other provisions (as permitted under the agency agreement)',
            'Coordinate with the Landlord’s Agent, Landlord or Property Manager to finalize lease terms',
            'Review lease drafts and coordinate revisions through appropriate channels',
            'Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties',
            'Track required deposits, rent commencement, and key lease dates to ensure move-in readiness'
        ] as $service)
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
<!-- Move-In Support & Coordination Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">🚚 Move-In Support & Coordination</h5>
    <div class="service-options">
        @foreach ([
            'Coordinate move-in date and key handoff logistics with the Landlord, Landlord’s Agent, or Property Manager',
            'Confirm completion of any agreed-upon pre-move-in repairs, cleaning, or buildout',
            'Provide a utility setup checklist and local provider resources',
            'Share a move-in checklist for documentation and property condition review',
            'Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods'
        ] as $service)
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
<!-- Leasing Strategy & Guidance Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">💡 Leasing Strategy & Guidance</h5>
    <div class="service-options">
        @foreach ([
            'Provide a Comparative Lease Market Analysis (CLMA) with pricing insights, comps, and vacancy trends',
            'Advise on lease types and structures (e.g., NNN, Modified Gross, Full Service) with general explanations of differences',
            'Provide general guidance on Tenant rights and Landlord responsibilities under commercial leasing law',
            'Provide general guidance on lease clauses, escalation terms, and space usage considerations',
        ] as $service)
            <div class="form-check service-item">
                <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                    id="leasing-{{ Str::slug($service) }}">
                <label class="form-check-label" for="leasing-{{ Str::slug($service) }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>
@endif
{{-- <!-- Additional Services Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">✍️ Additional Services</h5>
    <div class="service-options">
        <!-- Other Services Checkbox -->
        <div class="form-check service-item mb-3">
            <input class="form-check-input" type="checkbox" wire:model="other_services_enabled"
                id="other-services-checkbox">
            <label class="form-check-label" for="other-services-checkbox">
                Other – Specify additional services as needed.
            </label>
        </div>
        <!-- Other Services Input (shown when checkbox is checked) -->
        @if ($other_services_enabled)
            <div class="mb-3">
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
    <strong> ⚖️ Note: </strong> All services described above are provided within the scope of real estate brokerage duties as defined by state law. Services are administrative, advisory, or informational in nature and do not include legal, tax, accounting, or financial advice. Analyses are not formal appraisals or valuations. The Agent does not handle client funds outside of escrow or trust accounts as permitted by law. All information is relayed as provided by third parties (e.g., Landlord, Property Manager, Lender, Title, Escrow, Attorney, CPA, or other licensed professionals). The Tenant and/or their Attorney, CPA, or other licensed professional remain solely responsible for reviewing, confirming, and approving the accuracy, completeness, and compliance of all documents, disclosures, financial statements, lease agreements, and transfers. Additional services must remain within the scope of brokerage law and fiduciary duties as outlined in the signed brokerage agreement.
</div>
