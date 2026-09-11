{{--
  Location DNA criteria summary — the words for what the search map draws.

  Rendered by components/location-dna-map.blade.php, beneath whichever tier won, for
  Buyer and Tenant listings (Seller and Landlord carry a property pin and never reach it).

  Inputs:
    $ldnaCriteria       App\Support\LocationDna\LocationDnaCriteriaDisplay
    $ldnaCriteriaAreas  bool — list state/county/city/ZIP rows here. False on the chip
                        tier, which already shows those values as chips; repeating them
                        underneath would be the same fact twice.

  Everything printed is escaped text. No coordinate, JSON or provider value reaches this
  file: the display object only ever holds addresses, labels, types and distances.

  Flexible location and location notes are rendered by the component itself in every
  tier, so they are deliberately not repeated here.
--}}
@php
  $ldnaCriteriaShowAreas = ($ldnaCriteriaAreas ?? false) && $ldnaCriteria->areas !== [];
@endphp
@if ($ldnaCriteria->hasMappedCriteria() || $ldnaCriteriaShowAreas)
@once
<style>
  .ldna-criteria-summary { border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; padding: .85rem 1rem; }
  .ldna-criteria-summary h6 { font-size: .74rem; font-weight: 700; color: #64748b; text-transform: uppercase;
    letter-spacing: .04em; margin: .6rem 0 .3rem; }
  .ldna-criteria-summary h6:first-child { margin-top: 0; }
  .ldna-criteria-summary ul { list-style: none; margin: 0; padding: 0; }
  .ldna-criteria-summary li { font-size: .88rem; color: #1f2937; padding: .2rem 0; }
  .ldna-criteria-summary .ldna-crit-sub { color: #64748b; font-size: .82rem; }
</style>
@endonce
{{-- The section holds reader-facing content only — styles sit outside it. --}}
<section class="ldna-criteria-summary mb-3" data-ldna-criteria-summary>

  @if ($ldnaCriteria->radiusSearches)
    <h6><i class="fa-solid fa-circle-dot me-1"></i>Radius {{ count($ldnaCriteria->radiusSearches) === 1 ? 'Search' : 'Searches' }}</h6>
    <ul>
      @foreach ($ldnaCriteria->radiusSearches as $ldnaCritRadius)
        <li data-ldna-criteria-radius>
          <strong>{{ $ldnaCritRadius['title'] }}</strong>
          @if ($ldnaCritRadius['distance'])
            <span class="ldna-crit-sub">&middot; {{ $ldnaCritRadius['distance'] }}</span>
          @endif
        </li>
      @endforeach
    </ul>
  @endif

  @if ($ldnaCriteria->importantPlaces)
    <h6><i class="fa-solid fa-location-dot me-1"></i>Important {{ count($ldnaCriteria->importantPlaces) === 1 ? 'Place' : 'Places' }}</h6>
    <ul>
      @foreach ($ldnaCriteria->importantPlaces as $ldnaCritPlace)
        <li data-ldna-criteria-place>
          <strong>{{ $ldnaCritPlace['type'] }}</strong>
          <span class="ldna-crit-sub">&middot; {{ $ldnaCritPlace['address'] !== '' ? $ldnaCritPlace['address'] : 'No address provided' }}</span>
          @if ($ldnaCritPlace['distance'])
            <span class="ldna-crit-sub">&middot; {{ $ldnaCritPlace['distance'] }}</span>
          @endif
          @if ($ldnaCritPlace['on_map'] === 'none')
            <div class="ldna-crit-sub">Not shown on the map — this address has not been located.</div>
          @elseif ($ldnaCritPlace['legacy_minutes'])
            <div class="ldna-crit-sub">Saved as a travel-time preference, which is no longer offered. Shown on the map as a pin, without a radius.</div>
          @endif
        </li>
      @endforeach
    </ul>
  @endif

  @if ($ldnaCriteria->customAreaCount > 0)
    <h6><i class="fa-solid fa-draw-polygon me-1"></i>Custom Search {{ $ldnaCriteria->customAreaCount === 1 ? 'Area' : 'Areas' }}</h6>
    <ul>
      <li data-ldna-criteria-custom-areas>
        {{ $ldnaCriteria->customAreaCount === 1 ? '1 custom area' : $ldnaCriteria->customAreaCount . ' custom areas' }}
        <span class="ldna-crit-sub">&middot; shown on the map</span>
      </li>
    </ul>
  @endif

  @if ($ldnaCriteriaShowAreas)
    <h6><i class="fa-solid fa-map me-1"></i>Preferred Areas</h6>
    <ul>
      @foreach ($ldnaCriteria->areas as $ldnaCritAreaLabel => $ldnaCritAreaValues)
        <li><span class="ldna-crit-sub">{{ $ldnaCritAreaLabel }}:</span> {{ implode(', ', $ldnaCritAreaValues) }}</li>
      @endforeach
    </ul>
  @endif
</section>
@endif
