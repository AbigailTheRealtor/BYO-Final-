{{--
  property-utilities — utilities, sewer, water source card.
  Section 1 (MLS Listing Information).
--}}
@props([
    'utilities'   => [],
    'sewer'       => [],
    'waterSource' => [],
])

@php
    $groups = array_filter([
        'Utilities'    => $utilities,
        'Sewer'        => $sewer,
        'Water Source' => $waterSource,
    ], fn($arr) => count($arr) > 0);
@endphp

@if(count($groups) > 0)
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-1">
        <h5 class="mb-0 fw-semibold" style="font-size:.95rem;">
            <i class="fas fa-plug me-2 text-primary"></i>Utilities
        </h5>
    </div>
    <div class="card-body pt-2">
        @foreach($groups as $label => $items)
            <div class="mb-2">
                <span class="text-muted me-2" style="font-size:.82rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">
                    {{ $label }}:
                </span>
                {{ implode(', ', $items) }}
            </div>
        @endforeach
    </div>
</div>
@endif
