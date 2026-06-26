{{--
  property-map — embedded Google Map for a single property pin.
  Section 1 (MLS Listing Information).
  Uses Maps Embed API with the same key as the JS Maps API (config.services.google.places_key).
  Degrades to a "View on Google Maps" link if no coordinates or no API key.
--}}
@props([
    'latitude'  => null,
    'longitude' => null,
    'address'   => null,
])

@php
    $mapsKey = config('services.google.places_key', '');
    $hasCoords = $latitude !== null && $longitude !== null;
    $query = $hasCoords
        ? $latitude . ',' . $longitude
        : ($address ? urlencode($address) : null);
    $googleMapsLink = $hasCoords
        ? 'https://www.google.com/maps/search/?api=1&query=' . $latitude . ',' . $longitude
        : ($address ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($address) : null);
@endphp

@if($query)
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-1 d-flex align-items-center justify-content-between">
        <h5 class="mb-0 fw-semibold" style="font-size:.95rem;">
            <i class="fas fa-map-location-dot me-2 text-primary"></i>Map
        </h5>
        @if($googleMapsLink)
            <a href="{{ $googleMapsLink }}" target="_blank" rel="noopener noreferrer"
               class="btn btn-outline-secondary btn-sm" style="font-size:.78rem;">
                <i class="fas fa-external-link-alt me-1"></i>Open in Google Maps
            </a>
        @endif
    </div>
    <div class="card-body p-0" style="border-radius:0 0 .375rem .375rem;overflow:hidden;">
        @if($mapsKey && $query)
            <iframe
                width="100%"
                height="320"
                style="border:0;display:block;"
                loading="lazy"
                allowfullscreen
                referrerpolicy="no-referrer-when-downgrade"
                src="https://www.google.com/maps/embed/v1/place?key={{ $mapsKey }}&q={{ $query }}&zoom=15">
            </iframe>
        @elseif($googleMapsLink)
            <div class="d-flex align-items-center justify-content-center bg-light" style="height:200px;">
                <div class="text-center text-muted">
                    <i class="fas fa-map fa-2x mb-2 d-block"></i>
                    <a href="{{ $googleMapsLink }}" target="_blank" rel="noopener noreferrer"
                       class="btn btn-primary btn-sm">
                        <i class="fas fa-external-link-alt me-1"></i>View on Google Maps
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
@endif
