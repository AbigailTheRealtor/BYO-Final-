{{--
  property-key-facts — sidebar key facts dl card.
  Section 1 (MLS Listing Information).
  Props: $property (full PropertyDetailViewMapper output array)
--}}
@props(['property'])

@php
    $facts = array_filter([
        'Price'          => $property['price_display'],
        'Bedrooms'       => $property['beds'] !== null ? $property['beds'] . ' bed' . ($property['beds'] != 1 ? 's' : '') : null,
        'Bathrooms'      => $property['baths_full'] !== null && $property['baths_half']
                                ? $property['baths_full'] . ' full + ' . $property['baths_half'] . ' half'
                                : ($property['baths_total'] !== null ? $property['baths_total'] . ' bath' . ($property['baths_total'] != 1 ? 's' : '') : null),
        'Living Area'    => $property['sqft_display'] ? $property['sqft_display'] . ' sqft' : null,
        'Lot Size'       => $property['lot_size_acres'] ? $property['lot_size_acres'] . ' acres' : null,
        'Year Built'     => $property['year_built'],
        'Levels'         => $property['levels'],
        'Stories'        => $property['stories'],
        'Property Type'  => $property['property_sub_type'] ?? $property['property_type'],
        'Days on Market' => $property['days_on_market'] !== null ? $property['days_on_market'] . ' day' . ($property['days_on_market'] != 1 ? 's' : '') : null,
        'Garage'         => $property['garage'] ? ($property['garage_spaces'] ? $property['garage_spaces'] . ' space' . ($property['garage_spaces'] != 1 ? 's' : '') : 'Yes') : null,
        'Carport'        => $property['carport_spaces'] ? $property['carport_spaces'] . ' space' . ($property['carport_spaces'] != 1 ? 's' : '') : null,
        'Pool'           => $property['pool'] ? 'Yes' : null,
        'Spa'            => $property['spa'] ? 'Yes' : null,
        'Waterfront'     => $property['waterfront'] ? 'Yes' : null,
        'Water View'     => $property['water_view'] ? 'Yes' : null,
        'Fireplace'      => $property['fireplace'] ? 'Yes' : null,
        'Pets Allowed'   => $property['pets_allowed'],
    ], fn($v) => $v !== null && $v !== '' && $v !== false);
@endphp

@if(count($facts) > 0)
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-1">
        <h5 class="mb-0 fw-semibold" style="font-size:.95rem;">
            <i class="fas fa-list-check me-2 text-primary"></i>Key Facts
        </h5>
    </div>
    <div class="card-body pt-1 px-3 pb-2">
        <dl class="mb-0" style="font-size:.875rem;">
            @php $keys = array_keys($facts); $last = end($keys); @endphp
            @foreach($facts as $label => $value)
                <div class="d-flex justify-content-between py-1 {{ $label !== $last ? 'border-bottom' : '' }}">
                    <dt class="fw-normal text-muted">{{ $label }}</dt>
                    <dd class="mb-0 text-end fw-semibold">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</div>
@endif
