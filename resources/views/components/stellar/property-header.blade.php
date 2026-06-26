{{--
  property-header — price, address, specs bar, virtual tour, status badges, CTA row.
  Section 1 (MLS Listing Information) header block.
  Props: $property (full PropertyDetailViewMapper output array)
--}}
@props(['property'])

<div class="mb-3">

    {{-- Status badges --}}
    <div class="d-flex flex-wrap gap-2 mb-2">
        @if($property['mls_status'])
            <span class="badge bg-secondary" style="font-size:.75rem;">{{ $property['mls_status'] }}</span>
        @endif
        @if($property['new_construction'])
            <span class="badge bg-success" style="font-size:.75rem;">New Construction</span>
        @endif
        @if($property['waterfront'])
            <span class="badge bg-info text-dark" style="font-size:.75rem;">Waterfront</span>
        @endif
        @if($property['senior_community'])
            <span class="badge bg-warning text-dark" style="font-size:.75rem;">55+ Community</span>
        @endif
        @if($property['cdd'])
            <span class="badge bg-secondary" style="font-size:.75rem;">CDD</span>
        @endif
    </div>

    {{-- Price row --}}
    <div class="d-flex align-items-baseline gap-3 flex-wrap">
        @if($property['price_display'])
            <span class="fw-bold text-dark" style="font-size:2rem;line-height:1.1;">
                {{ $property['price_display'] }}
            </span>
        @else
            <span class="text-muted fst-italic" style="font-size:1.4rem;">Price not available</span>
        @endif
        @if($property['price_reduced'] && $property['original_list_price'])
            <span class="text-muted" style="font-size:.875rem;">
                <s>${{ number_format($property['original_list_price'], 0) }}</s>
                <span class="badge bg-danger ms-1">Price Reduced</span>
            </span>
        @endif
    </div>

    {{-- Address block --}}
    @if($property['address'])
        <div class="fw-semibold text-dark mt-1" style="font-size:1.15rem;">
            {{ $property['address'] }}
            @if($property['unit_number'])
                <span class="text-muted fw-normal">Unit {{ $property['unit_number'] }}</span>
            @endif
        </div>
    @endif
    @if($property['city'] || $property['state'] || $property['postal_code'])
        <div class="text-muted" style="font-size:.95rem;">
            {{ implode(', ', array_filter([$property['city'], $property['state']])) }}
            @if($property['postal_code']) {{ $property['postal_code'] }} @endif
        </div>
    @endif
    @if($property['subdivision'])
        <div class="text-muted" style="font-size:.85rem;">
            <i class="fas fa-map-pin me-1"></i>{{ $property['subdivision'] }}
            @if($property['county']) &mdash; {{ $property['county'] }} County @endif
        </div>
    @endif

    {{-- Specs bar --}}
    <div class="d-flex flex-wrap gap-3 align-items-center py-2 mt-2 border-top border-bottom" style="font-size:.93rem;">
        @if($property['beds'] !== null)
            <div>
                <i class="fas fa-bed me-1 text-secondary"></i>
                <strong>{{ $property['beds'] }}</strong> bed{{ $property['beds'] != 1 ? 's' : '' }}
            </div>
        @endif
        @if($property['baths_total'] !== null)
            <div>
                <i class="fas fa-bath me-1 text-secondary"></i>
                @if($property['baths_full'] !== null && $property['baths_half'])
                    <strong>{{ $property['baths_full'] }}</strong> full + <strong>{{ $property['baths_half'] }}</strong> half
                @else
                    <strong>{{ $property['baths_total'] }}</strong> bath{{ $property['baths_total'] != 1 ? 's' : '' }}
                @endif
            </div>
        @endif
        @if($property['sqft_display'])
            <div>
                <i class="fas fa-vector-square me-1 text-secondary"></i>
                <strong>{{ $property['sqft_display'] }}</strong> sqft
            </div>
        @endif
        @if($property['lot_size_acres'])
            <div>
                <i class="fas fa-ruler-combined me-1 text-secondary"></i>
                <strong>{{ $property['lot_size_acres'] }}</strong> ac
            </div>
        @endif
        @if($property['year_built'])
            <div>
                <i class="fas fa-calendar-alt me-1 text-secondary"></i>
                Built <strong>{{ $property['year_built'] }}</strong>
            </div>
        @endif
        @if($property['property_type'])
            <div>
                <i class="fas fa-home me-1 text-secondary"></i>
                {{ $property['property_type'] }}
                @if($property['property_sub_type']) &mdash; {{ $property['property_sub_type'] }} @endif
            </div>
        @endif
        @if($property['days_on_market'] !== null)
            <div class="ms-auto text-muted" style="font-size:.83rem;">
                {{ $property['days_on_market'] }} day{{ $property['days_on_market'] != 1 ? 's' : '' }} on market
            </div>
        @endif
        @if($property['virtual_tour_url'])
            <a href="{{ $property['virtual_tour_url'] }}" target="_blank" rel="noopener noreferrer"
               class="btn btn-sm btn-outline-primary {{ $property['days_on_market'] !== null ? '' : 'ms-auto' }}">
                <i class="fas fa-vr-cardboard me-1"></i>Virtual Tour
            </a>
        @endif
    </div>

    {{-- CTA buttons --}}
    <div class="d-flex gap-2 flex-wrap mt-2">
        <button class="btn btn-outline-secondary btn-sm" disabled title="Coming soon">
            <i class="fas fa-calendar-check me-1"></i>Request Showing
        </button>
        <button class="btn btn-outline-secondary btn-sm" disabled title="Coming soon">
            <i class="far fa-bookmark me-1"></i>Save
        </button>
        <button class="btn btn-outline-secondary btn-sm" disabled title="Coming soon">
            <i class="fas fa-share-alt me-1"></i>Share
        </button>
    </div>

</div>
