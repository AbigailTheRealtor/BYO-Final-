<h3>Services the Seller Requests from Their Agent</h3>

<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>🤝 Select the services the Seller would like the Agent to provide throughout the sale process. Services
            are offered under a commission-based, full-service agreement. The final scope and compensation will be
            outlined in the signed agreement.</strong>
        </div>
    </div>
</div>

@php
    $propType = $property_type ?? '';
    $isVacantOrResidential = str_contains($propType, 'Vacant') || str_contains($propType, 'Residential');
    $isIncome              = str_contains($propType, 'Income');
    $isCommercialOrBusiness= str_contains($propType, 'Commercial') || str_contains($propType, 'Business');
@endphp

{{-- ======================== RESIDENTIAL / VACANT LAND ======================== --}}
@if ($isVacantOrResidential || (!$isIncome && !$isCommercialOrBusiness))

    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📊 Pricing & Market Analysis</h5>
        <div class="service-options">
            @foreach ([
                "Conduct a thorough comparative market analysis (CMA) to determine the property's value and pricing strategy.",
            ] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="seller-svc-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="seller-svc-{{ Str::slug($service) }}">{{ $service }}</label>
                </div>
            @endforeach
        </div>
    </div>

    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📣 Listing & Marketing</h5>
        <div class="service-options">
            @foreach ([
                "List the property on the MLS.",
                "List the property on major real estate websites, such as Zillow, Trulia, Realtor.com, Homes.com, Homesnap, Hotpads, and many more, to increase the property's visibility and exposure.",
                "List the property on the Bid Your Offer platform.",
                "Implement an online marketing campaign with a QR code or listing link that leads to the property's listing.",
                "Market the property to various groups, pages, and affiliates to generate interest and leads with a QR code or listing link leading to the property's listing.",
                "Promote the property on social media platforms with a QR code or listing link leading to the property's listing.",
                "Distribute postcards featuring the seller's listing within their neighborhood with a QR code or listing link leading to the property's listing.",
                "Distribute postcards featuring the seller's listing to the most opportune buyers with a QR code or listing link leading to the property's listing.",
                "Conduct real estate email marketing campaigns that lead to the seller's listing.",
            ] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="seller-svc-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="seller-svc-{{ Str::slug($service) }}">{{ $service }}</label>
                </div>
            @endforeach
        </div>
    </div>

    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📷 Photography & Media</h5>
        <div class="service-options">
            @foreach ([
                "Provide professional photos to showcase the property's features.",
                "Provide aerial photography to capture the property's surroundings and neighborhood.",
                "Provide a professional video to showcase the land.",
                "Provide a plot plan to showcase the land.",
            ] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="seller-svc-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="seller-svc-{{ Str::slug($service) }}">{{ $service }}</label>
                </div>
            @endforeach
        </div>
    </div>

    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">🏡 Showings & Buyer Engagement</h5>
        <div class="service-options">
            @foreach ([
                "Provide guidance and assistance in preparing the property for sale, including recommendations for repairs or improvements.",
                "Coordinate and schedule showings for potential buyers, ensuring a smooth and efficient viewing experience.",
                "Send email alerts to buyers searching for properties that match the property's criteria the moment the property is listed directly through the MLS.",
            ] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="seller-svc-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="seller-svc-{{ Str::slug($service) }}">{{ $service }}</label>
                </div>
            @endforeach
        </div>
    </div>

    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📝 Negotiation, Contract & Closing</h5>
        <div class="service-options">
            @foreach ([
                "Offer expert negotiation skills to secure the best possible terms and price during the selling process.",
                "Provide guidance and support throughout the entire transaction, from listing to closing, to ensure a seamless and successful sale.",
                "Assist with the completion and submission of all necessary paperwork and documentation related to the sale.",
                "Collaborate with other real estate professionals and agents to expand the network of potential buyers for the property.",
                "Provide regular updates on market activity, showings, and feedback from potential buyers.",
            ] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="seller-svc-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="seller-svc-{{ Str::slug($service) }}">{{ $service }}</label>
                </div>
            @endforeach
        </div>
    </div>

{{-- ======================== INCOME PROPERTY ======================== --}}
@elseif ($isIncome)

    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📊 Pricing & Market Analysis</h5>
        <div class="service-options">
            @foreach ([
                "Conduct a thorough comparative market analysis (CMA) to determine the property's value and pricing strategy.",
            ] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="seller-svc-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="seller-svc-{{ Str::slug($service) }}">{{ $service }}</label>
                </div>
            @endforeach
        </div>
    </div>

    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📣 Listing & Marketing</h5>
        <div class="service-options">
            @foreach ([
                "List the property on the MLS.",
                "List the property on major real estate websites, such as Zillow, Trulia, Realtor.com, Homes.com, Homesnap, Hotpads, and many more, to increase the property's visibility and exposure.",
                "List the property on Loopnet, a major commercial real estate website.",
                "List the property on Crexi, a major commercial real estate website.",
                "List the property on the Bid Your Offer platform.",
                "Implement an online marketing campaign with a QR code or listing link that leads to the property's listing on the BidYourOffer.com platform.",
                "Market the property to various groups, pages, and affiliates to generate interest and leads with a QR code or listing link leading to the property's listing on the BidYourOffer.com platform.",
                "Promote the property on social media platforms with a QR code or listing link leading to the property's listing.",
                "Distribute postcards featuring the seller's listing within their neighborhood with a QR code or listing link leading to the property's listing.",
                "Distribute postcards featuring the seller's listing to the most opportune buyers with a QR code or listing link leading to the property's listing.",
                "Conduct real estate email marketing campaigns that lead to the seller's listing on the BidYourOffer.com platform.",
            ] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="seller-svc-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="seller-svc-{{ Str::slug($service) }}">{{ $service }}</label>
                </div>
            @endforeach
        </div>
    </div>

    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📷 Photography & Media</h5>
        <div class="service-options">
            @foreach ([
                "Provide professional photos to showcase the property's best features.",
                "Provide aerial photography to capture the property's surroundings and neighborhood.",
                "Provide a professional video to showcase the property's interior and exterior.",
            ] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="seller-svc-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="seller-svc-{{ Str::slug($service) }}">{{ $service }}</label>
                </div>
            @endforeach
        </div>
    </div>

    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">🏡 Showings & Buyer Engagement</h5>
        <div class="service-options">
            @foreach ([
                "Provide guidance and assistance in preparing the property for sale, including recommendations for repairs or improvements.",
                "Coordinate and schedule showings for potential buyers, ensuring a smooth and efficient viewing experience.",
                "Send email alerts to buyers searching for properties that match the property's criteria the moment the property is listed directly through the MLS.",
            ] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="seller-svc-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="seller-svc-{{ Str::slug($service) }}">{{ $service }}</label>
                </div>
            @endforeach
        </div>
    </div>

    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📝 Negotiation, Contract & Closing</h5>
        <div class="service-options">
            @foreach ([
                "Offer expert negotiation skills to secure the best possible terms and price during the selling process.",
                "Provide guidance and support throughout the entire transaction, from listing to closing, to ensure a seamless and successful sale.",
                "Assist with the completion and submission of all necessary paperwork and documentation related to the sale.",
                "Collaborate with other real estate professionals and agents to expand the network of potential buyers for the property.",
                "Provide regular updates on market activity, showings, and feedback from potential buyers.",
            ] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="seller-svc-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="seller-svc-{{ Str::slug($service) }}">{{ $service }}</label>
                </div>
            @endforeach
        </div>
    </div>

{{-- ======================== COMMERCIAL / BUSINESS ======================== --}}
@elseif ($isCommercialOrBusiness)

    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📊 Pricing & Market Analysis</h5>
        <div class="service-options">
            @foreach ([
                "Conduct a thorough comparative market analysis (CMA) to determine the property's value and pricing strategy.",
            ] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="seller-svc-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="seller-svc-{{ Str::slug($service) }}">{{ $service }}</label>
                </div>
            @endforeach
        </div>
    </div>

    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📣 Listing & Marketing</h5>
        <div class="service-options">
            @foreach ([
                "List the property on the MLS.",
                "List the property on major real estate websites, such as Zillow, Trulia, Realtor.com, Homes.com, Homesnap, Hotpads, and many more, to increase the property's visibility and exposure.",
                "List the property on Loopnet, a major commercial real estate website.",
                "List the property on Crexi, a major commercial real estate website.",
                "List the property on the Bid Your Offer platform.",
                "Implement an online marketing campaign with a QR code or listing link that leads to the property's listing on the BidYourOffer.com platform.",
                "Market the property to various groups, pages, and affiliates to generate interest and leads with a QR code or listing link leading to the property's listing on the BidYourOffer.com platform.",
                "Promote the property on social media platforms with a QR code or listing link leading to the property's listing.",
                "Distribute postcards featuring the seller's listing within their neighborhood with a QR code or listing link leading to the property's listing.",
                "Distribute postcards featuring the seller's listing to the most opportune buyers with a QR code or listing link leading to the property's listing.",
                "Conduct real estate email marketing campaigns that lead to the seller's listing on the BidYourOffer.com platform.",
            ] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="seller-svc-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="seller-svc-{{ Str::slug($service) }}">{{ $service }}</label>
                </div>
            @endforeach
        </div>
    </div>

    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📷 Photography, Virtual Tours & Media</h5>
        <div class="service-options">
            @foreach ([
                "Provide professional photos to showcase the property's best features.",
                "Provide aerial photography to capture the property's surroundings and neighborhood.",
                "Provide a professional video to showcase the property's interior and exterior.",
                "Provide a 3D tour to showcase the property's interior.",
                "Provide a floor plan of the property to showcase its layout and spatial configuration.",
                "Provide virtual staging to enhance the property's visual appeal and attract potential buyers.",
            ] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="seller-svc-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="seller-svc-{{ Str::slug($service) }}">{{ $service }}</label>
                </div>
            @endforeach
        </div>
    </div>

    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">🏡 Showings & Buyer Engagement</h5>
        <div class="service-options">
            @foreach ([
                "Provide guidance and assistance in preparing the property for sale, including recommendations for repairs or improvements.",
                "Coordinate and schedule showings for potential buyers, ensuring a smooth and efficient viewing experience.",
                "Send email alerts to buyers searching for properties that match the property's criteria the moment the property is listed directly through the MLS.",
            ] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="seller-svc-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="seller-svc-{{ Str::slug($service) }}">{{ $service }}</label>
                </div>
            @endforeach
        </div>
    </div>

    <div class="service-section mb-4">
        <h5 class="section-header bg-info text-white p-2 mb-3">📝 Negotiation, Contract & Closing</h5>
        <div class="service-options">
            @foreach ([
                "Offer expert negotiation skills to secure the best possible terms and price during the selling process.",
                "Provide guidance and support throughout the entire transaction, from listing to closing, to ensure a seamless and successful sale.",
                "Assist with the completion and submission of all necessary paperwork and documentation related to the sale.",
                "Collaborate with other real estate professionals and agents to expand the network of potential buyers for the property.",
                "Provide regular updates on market activity, showings, and feedback from potential buyers.",
            ] as $service)
                <div class="form-check service-item">
                    <input class="form-check-input" type="checkbox" wire:model="services" value="{{ $service }}"
                        id="seller-svc-{{ Str::slug($service) }}">
                    <label class="form-check-label" for="seller-svc-{{ Str::slug($service) }}">{{ $service }}</label>
                </div>
            @endforeach
        </div>
    </div>

@endif

<!-- Other Services -->
<div class="service-section mb-4">
    <div class="form-check service-item mb-3">
        <input class="form-check-input" type="checkbox" wire:model="other_services_enabled" id="other_services_enabled">
        <label class="form-check-label fw-bold" for="other_services_enabled">
            Other – Add additional services as offered
        </label>
    </div>

    @if ($other_services_enabled)
        <div class="ms-4">
            @foreach ($other_services as $i => $svc)
                <div class="input-group mb-2" wire:key="other-svc-{{ $i }}">
                    <input type="text" wire:model="other_services.{{ $i }}" class="form-control"
                        placeholder="Describe additional service...">
                    <button type="button" wire:click="removeService({{ $i }})" class="btn btn-outline-danger btn-sm">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>
            @endforeach
            <button type="button" wire:click="addServiceField" class="btn btn-outline-secondary btn-sm mt-1">
                <i class="fa-solid fa-plus"></i> Add Another Service
            </button>
        </div>
    @endif
</div>
