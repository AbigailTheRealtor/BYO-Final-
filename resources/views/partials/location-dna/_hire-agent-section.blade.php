{{--
  Location DNA on a Hire Agent detail page — the body of the `location-dna` section.

  Input: $hireLocationDna, built by App\Services\LocationDna\ListingLocationDnaViewData.

  The SAME component, renderer and criteria summary the Offer Listing pages use. A Buyer or
  Tenant Hire listing shows its search areas, radius searches, Important Places and the words
  for them; a Seller or Landlord Hire listing shows its property pin and the Location DNA panel.
  Nothing here parses Location DNA — the builder hands over the component's own inputs.

  The caller renders this only when $hireLocationDna['hasContent'] is true, which for a
  Seller/Landlord listing already accounts for whether this viewer may see the exact location.
--}}
@if (($hireLocationDna['kind'] ?? '') === 'property')
  <x-location-dna-map
      :preferences="null"
      :legacyLocation="[]"
      :boundaryData="null"
      :floodZoneData="null"
      :schoolDistrictData="null"
      :propertyPin="$hireLocationDna['propertyPin']"
  />
  @if ($hireLocationDna['locationDna'])
    @include('partials.location-dna-agent-panel', [
        'listingType'            => $hireLocationDna['listingType'],
        'listingId'              => $hireLocationDna['listingId'],
        'locationDna'            => $hireLocationDna['locationDna'],
        'locationPois'           => $hireLocationDna['locationPois'],
        'canGenerateLocationDna' => false,
    ])
  @endif
@else
  {{-- No heading: the section card's own title already names it. Important Places arrive
       already reduced for a non-owner, and the flag keeps the component private by default. --}}
  <x-location-dna-map
      :preferences="$hireLocationDna['locationDnaPreferences'] ?? null"
      :legacyLocation="$hireLocationDna['legacyLocation'] ?? []"
      :importantPlaces="$hireLocationDna['importantPlaces'] ?? []"
      :importantPlacesExact="$hireLocationDna['importantPlacesExact'] ?? false"
      :boundaryData="$hireLocationDna['boundaryData'] ?? null"
      :floodZoneData="$hireLocationDna['floodZoneData'] ?? null"
      :schoolDistrictData="$hireLocationDna['schoolDistrictData'] ?? null"
  />
@endif
