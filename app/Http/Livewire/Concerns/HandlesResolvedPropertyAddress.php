<?php

namespace App\Http\Livewire\Concerns;

/**
 * HandlesResolvedPropertyAddress (A3.20–A3.25)
 *
 * Shared server-side handler for the map-integrated address component
 * (resources/views/components/byo-address-autocomplete.blade.php).
 *
 * The Blade component's `place_changed` listener calls this method with the
 * resolved address parts, populating the standard address properties that every
 * Hire Agent / Create Offer component already declares (address, property_city,
 * property_county, property_state, property_zip).
 *
 * WHY THE NAMES ARE PROVIDER-NEUTRAL (Phase 1)
 * --------------------------------------------
 * `fillFromResolvedAddress()` describes what it receives — a resolved address —
 * not who resolved it. Today the resolver is Google Places; Decision D1 will
 * replace it (US Census Geocoder, then OpenAddresses/NAD). When that happens only
 * the caller changes: the eight-argument contract, this method, and every consuming
 * component stay exactly as they are. That is the whole point of the naming — the
 * D1 swap becomes a one-file change instead of a rename across ten components.
 *
 * The trait carried a Google-specific name for one commit after the method was
 * neutralised, because renaming a trait used by ten components was outside that
 * commit's approved scope. It is now
 * `HandlesResolvedPropertyAddress`, matching the method it exists to provide.
 * Naming only: no signature, no behaviour, and no payload handling changed with
 * either step, and nothing here selects, calls, or knows about a provider.
 *
 * Geo + place-id properties (property_lat / property_lng / google_place_id) and
 * the server-side city-suggestion state (propertyCitySuggestions /
 * highlightedPropertyCityIndex) are only updated when the consuming component
 * actually declares them — guarded with property_exists() so the trait is safe
 * to mix into components that do not carry those fields (e.g. the Hire Agent
 * components, which intentionally omit the geo fields).
 */
trait HandlesResolvedPropertyAddress
{
    public function fillFromResolvedAddress(
        string $street,
        string $city,
        string $county,
        string $state,
        string $zip,
        string $lat = '',
        string $lng = '',
        string $placeId = ''
    ): void {
        $this->address         = $street;
        $this->property_city   = $city;
        $this->property_county = $county;
        $this->property_state  = $state;
        $this->property_zip    = $zip;

        if (property_exists($this, 'property_lat')) {
            $this->property_lat = $lat;
        }
        if (property_exists($this, 'property_lng')) {
            $this->property_lng = $lng;
        }
        if (property_exists($this, 'google_place_id')) {
            $this->google_place_id = $placeId;
        }

        // Clear any open server-side city-suggestion dropdown once the parts are filled.
        if (property_exists($this, 'propertyCitySuggestions')) {
            $this->propertyCitySuggestions = [];
        }
        if (property_exists($this, 'highlightedPropertyCityIndex')) {
            $this->highlightedPropertyCityIndex = -1;
        }
    }
}
