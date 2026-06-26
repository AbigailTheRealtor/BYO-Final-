{{--
  property-interior — interior features card.
  Section 1 (MLS Listing Information).
--}}
@props([
    'interiorFeatures' => [],
    'appliances'       => [],
    'flooring'         => [],
    'cooling'          => [],
    'heating'          => [],
    'laundry'          => [],
    'fireplaceFeatures'=> [],
    'windowFeatures'   => [],
    'security'         => [],
    'accessibility'    => [],
    'fireplace'        => false,
])

@php
    $groups = array_filter([
        'Interior'          => $interiorFeatures,
        'Appliances'        => $appliances,
        'Flooring'          => $flooring,
        'Cooling'           => $cooling,
        'Heating'           => $heating,
        'Laundry'           => $laundry,
        'Fireplace'         => $fireplaceFeatures,
        'Windows'           => $windowFeatures,
        'Security'          => $security,
        'Accessibility'     => $accessibility,
    ], fn($arr) => count($arr) > 0);
@endphp

@if(count($groups) > 0 || $fireplace)
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-1">
        <h5 class="mb-0 fw-semibold" style="font-size:.95rem;">
            <i class="fas fa-couch me-2 text-primary"></i>Interior Features
        </h5>
    </div>
    <div class="card-body pt-2">
        @foreach($groups as $label => $items)
            <div class="mb-3">
                <div class="text-muted mb-1" style="font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">
                    {{ $label }}
                </div>
                <div class="d-flex flex-wrap gap-1">
                    @foreach($items as $item)
                        <span class="badge bg-light text-dark border" style="font-size:.8rem;font-weight:400;">{{ $item }}</span>
                    @endforeach
                </div>
            </div>
        @endforeach
        @if($fireplace && count($groups['Fireplace'] ?? []) === 0)
            <div class="mb-3">
                <div class="text-muted mb-1" style="font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Fireplace</div>
                <span class="badge bg-light text-dark border" style="font-size:.8rem;font-weight:400;">Yes</span>
            </div>
        @endif
    </div>
</div>
@endif
