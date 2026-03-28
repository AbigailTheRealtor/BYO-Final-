<h3>Services the Tenant Requests from Their Agent</h3>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>🤝 Review and adjust the services the Tenant would like the Agent to provide. Only the services selected in the original bid are shown below. Uncheck any service you wish to remove from the counter offer.
            </strong>
        </div>
    </div>
</div>

@php
    /*
     * Normalize curly/smart quotes → straight ASCII equivalents so that
     * catalog strings (which may use either) match stored service strings.
     * Both sides are normalized before comparison; display always uses the
     * stored string value so wire:model matching stays intact.
     */
    $normalizeStr = function (string $s): string {
        return str_replace(
            ["\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}", "\u{2032}", "\u{2033}"],
            ["'",         "'",         '"',         '"',         "'",         '"'],
            $s
        );
    };

    // ── Full service catalog (ASCII apostrophes — normalized before compare) ──
    $catalog = [
        'Residential Property' => [
            '📢 Tenant Criteria Marketing & Promotion' => [
                "Create a branded flyer summarizing the Tenant's rental criteria",
                "Post the Tenant's rental criteria on Craigslist under the \"Real Estate Wanted\" section",
                "Share the Tenant's rental criteria on Nextdoor in Neighborhood or Community Groups",
                "Promote the Tenant's rental criteria on Facebook in Rental or Housing Groups",
                "Share the Tenant's rental criteria on Instagram using posts, stories, or reels",
                "Promote the Tenant's rental criteria on LinkedIn in Real Estate or Housing Groups",
                "Upload a TikTok video summarizing the Tenant's rental criteria",
                "Upload a YouTube video summarizing the Tenant's rental criteria",
                "Launch a mass email campaign promoting the Tenant's rental criteria",
                "Distribute branded postcards or flyers in the Tenant's preferred neighborhoods",
                "Launch hyperlocal digital ads targeting the Tenant's preferred rental areas",
            ],
            '🔍 Property Search, Alerts & Matching' => [
                "Send email alerts with new listings from the MLS that match the Tenant's rental criteria",
                "Search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the Tenant's rental criteria",
                "Communicate with the Landlord's Agent, Landlord, or Property Manager to confirm availability, lease terms, and showing instructions",
                "Evaluate properties with the Tenant and provide insights on pricing, lease terms, and overall fit",
            ],
            '🏡 Property Showings & Virtual Tours' => [
                "Schedule and attend property showings with the Tenant",
                "Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
                "Preview properties on behalf of the Tenant upon request",
                "Provide factual observations on property layout and condition",
            ],
            '📝 Tenant Application Support' => [
                "Provide the Tenant with application instructions or links to an online rental application platform",
                "Gather and organize required supporting documents (e.g., identification, income verification, reference letters)",
                "Submit complete and organized application packages to the Landlord's Agent, Landlord, or Property Manager for review",
                "Answer questions about the application process, screening timelines, and required documentation",
            ],
            '📃 Lease Preparation & Execution' => [
                "Review lease offers and assist the Tenant in preparing questions or requested changes",
                "Coordinate lease negotiation with the Landlord's Agent, Landlord, or Property Manager",
                "Assist with completing required lease disclosures and reviewing key lease terms",
                "Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties",
                "Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods",
            ],
            '🚚 Move-In Support & Coordination' => [
                "Coordinate move-in date and key handoff logistics with the Landlord's Agent, Landlord or Property Manager",
                "Confirm completion of any agreed-upon pre-move-in cleaning or repairs",
                "Provide a utility setup checklist and local provider resources",
                "Share a move-in checklist for documentation and property condition review",
                "Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods",
            ],
            '💡 Leasing Strategy & Guidance' => [
                "Provide a Rental Market Analysis (RMA) with pricing insights based on comparable rentals, neighborhood trends, and current market conditions",
                "Advise on lease types and structures (e.g., month-to-month, annual, furnished, lease-option)",
                "Provide general guidance on Tenant rights and Landlord responsibilities under state law",
                "Provide general guidance on lease clauses, payment terms, and renewal options",
            ],
        ],
        'Commercial Property' => [
            '📢 Tenant Criteria Marketing & Promotion' => [
                "Create a branded flyer summarizing the Tenant's leasing criteria",
                "Post the Tenant's leasing criteria on Craigslist under the \"Office/Commercial\" or \"Retail\" section",
                "Promote the Tenant's leasing criteria on Facebook in Commercial Leasing or Business Groups",
                "Share the Tenant's leasing criteria on Instagram using posts, stories, or reels",
                "Promote the Tenant's leasing criteria on LinkedIn in Professional, Real Estate, or Commercial Investment Groups",
                "Upload a TikTok video summarizing the Tenant's leasing criteria",
                "Upload a YouTube video summarizing the Tenant's leasing criteria",
                "Launch a mass email campaign promoting the Tenant's leasing criteria",
                "Distribute branded postcards or flyers in the Tenant's preferred neighborhoods",
                "Launch hyperlocal digital ads targeting the Tenant's preferred leasing areas",
            ],
            '🔍 Property Search, Alerts & Matching' => [
                "Send listing alerts from real estate platforms that match the Tenant's leasing criteria",
                "Search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the Tenant's rental criteria",
                "Communicate with the Landlord's Agent, Landlord, or Property Manager to confirm availability, lease terms, and showing instructions",
                "Evaluate properties for layout efficiency, building specs, logistics, zoning fit, and operational alignment",
            ],
            '🏢 Property Showings & Virtual Tours' => [
                "Schedule and attend property tours with the Tenant",
                "Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
                "Preview properties on behalf of the Tenant upon request",
                "Provide factual notes on layout, access, parking, visibility, and other operational considerations",
            ],
            '📝 Tenant Application Support' => [
                "Provide the Tenant with application instructions or links to online platforms",
                "Gather and organize required supporting documents (e.g., business licenses, financials, references)",
                "Submit complete and organized application packages to the Landlord's Agent, Landlord, or Property Manager",
            ],
            '📃 Lease Preparation, LOI & Execution' => [
                "Draft or assist with preparing a Letter of Intent (LOI) summarizing the Tenant's business needs and proposed terms",
                "Assist with negotiating rent, CAM, lease term, TI allowance, exclusivity clauses, renewal options, and other provisions (as permitted under the agency agreement)",
                "Coordinate with the Landlord's Agent, Landlord or Property Manager to finalize lease terms",
                "Review lease drafts and coordinate revisions through appropriate channels",
                "Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties",
                "Track required deposits, rent commencement, and key lease dates to ensure move-in readiness",
            ],
            '🚚 Move-In Support & Coordination' => [
                "Coordinate move-in date and key handoff logistics with the Landlord, Landlord's Agent, or Property Manager",
                "Confirm completion of any agreed-upon pre-move-in repairs, cleaning, or buildout",
                "Provide a utility setup checklist and local provider resources",
                "Share a move-in checklist for documentation and property condition review",
                "Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods",
            ],
            '💡 Leasing Strategy & Guidance' => [
                "Provide a Comparative Lease Market Analysis (CLMA) with pricing insights, comps, and vacancy trends",
                "Advise on lease types and structures (e.g., NNN, Modified Gross, Full Service) with general explanations of differences",
                "Provide general guidance on Tenant rights and Landlord responsibilities under commercial leasing law",
                "Provide general guidance on lease clauses, escalation terms, and space usage considerations",
            ],
        ],
    ];

    // ── Build normalized lookup of selected services ─────────────────────────
    $selectedServices = (array) ($services ?? []);

    // Map normalized-string → original stored string (for display & wire:model value)
    $normalizedToOriginal = [];
    foreach ($selectedServices as $orig) {
        $normalizedToOriginal[$normalizeStr($orig)] = $orig;
    }

    $categories = $catalog[$property_type] ?? [];

    $filteredCategories = [];  // heading → [original stored strings to show]
    $matchedNormalized  = [];  // track which selected services were placed in a category

    foreach ($categories as $heading => $allServices) {
        $matched = [];
        foreach ($allServices as $catalogEntry) {
            $key = $normalizeStr($catalogEntry);
            if (isset($normalizedToOriginal[$key]) && !in_array($key, $matchedNormalized, true)) {
                $matched[] = $normalizedToOriginal[$key]; // use original stored string
                $matchedNormalized[] = $key;
            }
        }
        if (!empty($matched)) {
            $filteredCategories[$heading] = $matched;
        }
    }

    // Any selected services that did not match a catalog entry → "Other Selected Services"
    $unmapped = array_values(array_filter(
        $selectedServices,
        fn($s) => !in_array($normalizeStr($s), $matchedNormalized, true) && trim($s) !== ''
    ));
@endphp

@if (empty($selectedServices))
    <div class="alert alert-secondary mt-2">
        No offered services were selected for this listing.
    </div>
@else
    @foreach ($filteredCategories as $heading => $catServices)
        <div class="service-section mb-4">
            <h5 class="section-header bg-info text-white p-2 mb-3">{{ $heading }}</h5>
            <div class="service-options">
                @foreach ($catServices as $service)
                    <div class="form-check service-item">
                        <input class="form-check-input" type="checkbox"
                            wire:model="services"
                            value="{{ $service }}"
                            id="ctr-svc-{{ Str::slug($service) }}">
                        <label class="form-check-label" for="ctr-svc-{{ Str::slug($service) }}">
                            {{ $service }}
                        </label>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    @if (!empty($unmapped))
        <div class="service-section mb-4">
            <h5 class="section-header bg-info text-white p-2 mb-3">✍️ Other Selected Services</h5>
            <div class="service-options">
                @foreach ($unmapped as $service)
                    <div class="form-check service-item">
                        <input class="form-check-input" type="checkbox"
                            wire:model="services"
                            value="{{ $service }}"
                            id="ctr-svc-unmapped-{{ Str::slug($service) }}">
                        <label class="form-check-label" for="ctr-svc-unmapped-{{ Str::slug($service) }}">
                            {{ $service }}
                        </label>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endif

<!-- Additional Services Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">✍️ Additional Services</h5>
    <div class="service-options">
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
                            placeholder="Specify any additional services requested"
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
    <strong>⚖️ Note:</strong> All services described above are provided within the scope of real estate brokerage duties as defined by state law. Services are administrative, advisory, or informational in nature and do not include legal, tax, accounting, or financial advice. Analyses are not formal appraisals or valuations. The Agent does not handle client funds outside of escrow or trust accounts as permitted by law. All information is relayed as provided by third parties (e.g., Landlord, Property Manager, Lender, Title, Escrow, Attorney, CPA, or other licensed professionals). The Tenant and/or their Attorney, CPA, or other licensed professional remain solely responsible for reviewing, confirming, and approving the accuracy, completeness, and compliance of all documents, disclosures, financial statements, lease agreements, and transfers. Additional services must remain within the scope of brokerage law and fiduciary duties as outlined in the signed brokerage agreement.
</div>
