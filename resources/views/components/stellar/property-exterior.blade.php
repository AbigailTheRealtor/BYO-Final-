{{--
  property-exterior — exterior, structure, outdoor, parking card.
  Section 1 (MLS Listing Information).
--}}
@props([
    'exteriorFeatures'       => [],
    'constructionMaterials'  => [],
    'roof'                   => [],
    'foundation'             => [],
    'patioAndPorch'          => [],
    'otherStructures'        => [],
    'parkingFeatures'        => [],
    'poolFeatures'           => [],
    'spaFeatures'            => [],
    'view'                   => [],
    'waterfrontFeatures'     => [],
    'pool'                   => false,
    'spa'                    => false,
    'waterfront'             => false,
    'viewYn'                 => false,
    'garage'                 => false,
    'garageSpaces'           => null,
    'carportSpaces'          => null,
])

@php
    $groups = array_filter([
        'Exterior'             => $exteriorFeatures,
        'Construction'         => $constructionMaterials,
        'Roof'                 => $roof,
        'Foundation'           => $foundation,
        'Patio & Porch'        => $patioAndPorch,
        'Other Structures'     => $otherStructures,
        'Pool'                 => $poolFeatures,
        'Spa'                  => $spaFeatures,
        'View'                 => $view,
        'Waterfront'           => $waterfrontFeatures,
    ], fn($arr) => count($arr) > 0);

    $parkingLine = null;
    if(count($parkingFeatures) > 0) {
        $parkingLine = implode(', ', $parkingFeatures);
        if($garageSpaces) $parkingLine .= ' — ' . $garageSpaces . ' garage space' . ($garageSpaces != 1 ? 's' : '');
        if($carportSpaces) $parkingLine .= ' — ' . $carportSpaces . ' carport space' . ($carportSpaces != 1 ? 's' : '');
    } elseif($garage) {
        $parkingLine = $garageSpaces ? $garageSpaces . ' garage space' . ($garageSpaces != 1 ? 's' : '') : 'Garage';
        if($carportSpaces) $parkingLine .= ', ' . $carportSpaces . ' carport';
    } elseif($carportSpaces) {
        $parkingLine = $carportSpaces . ' carport space' . ($carportSpaces != 1 ? 's' : '');
    }

    $hasAnything = count($groups) > 0 || $parkingLine
        || ($pool && count($groups['Pool'] ?? []) === 0)
        || ($spa && count($groups['Spa'] ?? []) === 0)
        || ($waterfront && count($groups['Waterfront'] ?? []) === 0);
@endphp

@if($hasAnything)
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-1">
        <h5 class="mb-0 fw-semibold" style="font-size:.95rem;">
            <i class="fas fa-tree me-2 text-primary"></i>Exterior &amp; Structure
        </h5>
    </div>
    <div class="card-body pt-2">

        @if($parkingLine)
            <div class="mb-3">
                <div class="text-muted mb-1" style="font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Parking</div>
                <div style="font-size:.9rem;">{{ $parkingLine }}</div>
            </div>
        @endif

        @foreach($groups as $label => $items)
            <div class="mb-3">
                <div class="text-muted mb-1" style="font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">{{ $label }}</div>
                <div class="d-flex flex-wrap gap-1">
                    @foreach($items as $item)
                        <span class="badge bg-light text-dark border" style="font-size:.8rem;font-weight:400;">{{ $item }}</span>
                    @endforeach
                </div>
            </div>
        @endforeach

        @if($pool && count($groups['Pool'] ?? []) === 0)
            <div class="mb-2">
                <div class="text-muted mb-1" style="font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Pool</div>
                <span class="badge bg-light text-dark border" style="font-size:.8rem;font-weight:400;">Yes</span>
            </div>
        @endif
        @if($spa && count($groups['Spa'] ?? []) === 0)
            <div class="mb-2">
                <div class="text-muted mb-1" style="font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Spa</div>
                <span class="badge bg-light text-dark border" style="font-size:.8rem;font-weight:400;">Yes</span>
            </div>
        @endif
        @if($waterfront && count($groups['Waterfront'] ?? []) === 0)
            <div class="mb-2">
                <div class="text-muted mb-1" style="font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Waterfront</div>
                <span class="badge bg-info text-dark border" style="font-size:.8rem;font-weight:400;">Yes</span>
            </div>
        @endif

    </div>
</div>
@endif
