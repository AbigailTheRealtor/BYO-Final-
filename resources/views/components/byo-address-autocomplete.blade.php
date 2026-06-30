{{--
  Shared map-integrated address component (A3.20–A3.25)
  =====================================================
  Renders the Street Address input (wired to Google Places Autocomplete) plus an
  optional Unit / Apt / Suite field, and emits the autocomplete JS + the Google
  Maps loader. On place selection it calls the consuming Livewire component's
  fill method (HandlesGooglePlacesAddress::fillFromGooglePlaces) which populates
  the City / County / State / ZIP fields that live below this component in the
  host form. Those downstream fields keep their existing markup + server-side
  city-suggestion behaviour — this component only owns Street + Unit + the map.

  This is the single shared implementation used by the Hire Agent flows
  (Seller + Landlord, create + edit). Create Offer Seller/Landlord may adopt it
  in a follow-up cleanup (documented; not a launch blocker).

  Props
  -----
  street-id      (string, required) unique DOM id for the street input
  callback       (string, required) unique global JS init fn name (one per page)
  fill-method    (string) Livewire method to call on place select
  street-model   (string) wire:model for street      (default 'address')
  unit-model     (string) wire:model for unit         (default 'unit_address')
  show-unit      (bool)   render the Unit field       (default true)
  street-required(bool)   mark street required        (default true)
  with-geo-fields(bool)   render hidden lat/lng/place_id (default false)
--}}
@props([
    'streetId',
    'callback',
    'fillMethod' => 'fillFromGooglePlaces',
    'streetModel' => 'address',
    'unitModel' => 'unit_address',
    'showUnit' => true,
    'streetRequired' => true,
    'withGeoFields' => false,
    'latModel' => 'property_lat',
    'lngModel' => 'property_lng',
    'placeIdModel' => 'google_place_id',
])

<!-- Street Address (Google Places autocomplete) -->
<div class="form-group mb-3">
    <label class="fw-bold">Street Address:@if($streetRequired)<span class="text-danger">*</span>@endif</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the street address of the property (e.g., 123 Main Street). City, County, and State will be entered separately below.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <input type="text" id="{{ $streetId }}" wire:model="{{ $streetModel }}"
            class="form-control has-icon" data-icon="fa-solid fa-map-pin"
            placeholder="Enter street address (e.g., 123 Main Street)"
            autocomplete="off" @if($streetRequired) required @endif>
    </div>
</div>

@if($showUnit)
<!-- Unit / Apt / Suite -->
<div class="form-group mb-3">
    <label class="fw-bold">Unit / Apt / Suite:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the unit, apartment, or suite number if applicable (e.g., Apt 4B, Suite 200).">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <input type="text" wire:model="{{ $unitModel }}"
            class="form-control has-icon" data-icon="fa-solid fa-door-open"
            placeholder="e.g., Apt 4B, Suite 200 (optional)" autocomplete="off">
    </div>
</div>
@endif

@if($withGeoFields)
<input type="hidden" id="{{ $streetId }}-lat" wire:model="{{ $latModel }}">
<input type="hidden" id="{{ $streetId }}-lng" wire:model="{{ $lngModel }}">
<input type="hidden" id="{{ $streetId }}-place-id" wire:model="{{ $placeIdModel }}">
@endif

@push('scripts')
<script>
    window.{{ $callback }} = (function () {
        var boundNode = null;
        return function () {
            var input = document.getElementById(@json($streetId));
            if (!input || !window.google || !window.google.maps || !window.google.maps.places) { return; }
            if (input === boundNode) { return; }
            boundNode = input;

            var ac = new google.maps.places.Autocomplete(input, {
                types: ['address'],
                componentRestrictions: { country: 'us' },
                fields: ['address_components', 'geometry', 'place_id']
            });

            // Prevent Enter from submitting the form while the suggestion list is open.
            google.maps.event.addDomListener(input, 'keydown', function (e) {
                if (e.keyCode === 13) { e.preventDefault(); }
            });

            ac.addListener('place_changed', function () {
                var place = ac.getPlace();
                if (!place || !place.address_components) { return; }

                var streetNum = '', route = '', city = '', county = '', state = '', zip = '';
                place.address_components.forEach(function (c) {
                    var t = c.types;
                    if (t.indexOf('street_number') !== -1)                 streetNum = c.long_name;
                    if (t.indexOf('route') !== -1)                         route     = c.long_name;
                    if (t.indexOf('locality') !== -1)                      city      = c.long_name;
                    if (t.indexOf('sublocality_level_1') !== -1 && !city)  city      = c.long_name;
                    if (t.indexOf('administrative_area_level_2') !== -1)   county    = c.long_name.replace(/ County$/, '');
                    if (t.indexOf('administrative_area_level_1') !== -1)   state     = c.short_name;
                    if (t.indexOf('postal_code') !== -1)                   zip       = c.long_name;
                });

                var street = streetNum ? (streetNum + ' ' + route).trim() : route;
                var lat = (place.geometry && place.geometry.location) ? String(place.geometry.location.lat()) : '';
                var lng = (place.geometry && place.geometry.location) ? String(place.geometry.location.lng()) : '';
                var placeId = place.place_id || '';

                // Resolve the host Livewire component from the DOM (parent-agnostic — works
                // for the live TenantAgentAuction flow and the dedicated Hire components).
                var wireEl = input.closest('[wire\\:id]');
                var LW = window.Livewire || window.livewire;
                if (!wireEl || !LW || typeof LW.find !== 'function') { return; }
                var comp = LW.find(wireEl.getAttribute('wire:id'));
                if (comp) {
                    comp.call(@json($fillMethod), street, city, county, state, zip, lat, lng, placeId);
                }
            });
        };
    })();

    document.addEventListener('DOMContentLoaded', function () { window.{{ $callback }}(); });
    document.addEventListener('livewire:load', function () {
        window.{{ $callback }}();
        if (window.Livewire && typeof window.Livewire.hook === 'function') {
            Livewire.hook('message.processed', function () { window.{{ $callback }}(); });
        }
    });
</script>
@endpush

<x-google-maps-script :callback="$callback" />
