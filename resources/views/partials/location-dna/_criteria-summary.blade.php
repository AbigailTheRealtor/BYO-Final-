{{--
  Search Areas & Location Preferences — what the client asked for, in words.

  Rendered by components/location-dna-map.blade.php, beneath whichever tier won, for Buyer and
  Tenant listings (Seller and Landlord carry a property pin and never reach it). Every Buyer and
  Tenant detail page — Offer Listing, Criteria and Hire — renders THIS partial, so the two roles
  share one layout and cannot drift into two.

  Inputs:
    $ldnaCriteria       App\Support\LocationDna\LocationDnaCriteriaDisplay
    $ldnaCriteriaAreas  bool — show the named areas here. False on the chip tier, which already
                        shows them as chips above; repeating them underneath would be the same fact
                        twice.

  Layout: one group per kind of preference — Preferred Locations (chips), Radius Searches, Custom
  Search Areas, Important Places. Each item is a row card: a title line, a distance line and, for
  the owner only, a smaller muted "Exact location" line. Nothing is run together on one line.

  Everything printed is escaped text. No coordinate, JSON or provider value reaches this file: the
  display object only ever holds addresses, labels, types and distances.

  An Important Place is PRIVATE unless the viewer owns the listing: its card then carries the type
  and the miles only, with no address and no map note — a private place is never drawn, so "not
  located" would be untrue. The display object does not hand this file an address in that case.

  Page stylesheets decorate every list item (the Buyer criteria page draws a FontAwesome angle quote
  through `ul li::marker` and indents every `ul`). None of that may reach these rows, so each row is
  a flex item — which generates no marker at all — and the reset below is scoped to this section.

  Flexible location and location notes are rendered by the component itself in every tier.
--}}
@php
  $ldnaCriteriaShowAreas = ($ldnaCriteriaAreas ?? false) && $ldnaCriteria->areas !== [];
  // The component's own chip colours (.ldna-area-chip.*), so these match the chips above the map.
  $ldnaCritChipClass = ['State' => 'county', 'Counties' => 'county', 'Cities' => 'city', 'ZIP codes' => 'zip', 'Neighborhoods' => 'neigh'];
@endphp
@if ($ldnaCriteria->hasMappedCriteria() || $ldnaCriteriaShowAreas)
@once
<style>
  .ldna-criteria-summary { border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; padding: 1rem 1.1rem; }
  .ldna-criteria-summary .ldna-crit-group + .ldna-crit-group { margin-top: 1rem; }
  .ldna-criteria-summary .ldna-crit-heading { display: flex; align-items: center; gap: .4rem; margin: 0 0 .5rem;
    font-size: .74rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
  .ldna-criteria-summary ul.ldna-crit-list { list-style: none; margin: 0; padding: 0; display: grid; gap: .5rem; }
  .ldna-criteria-summary ul.ldna-crit-list > li { display: flex; align-items: flex-start; gap: .65rem; margin: 0;
    padding: .6rem .75rem; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; color: #1f2937; }
  .ldna-criteria-summary ul.ldna-crit-list > li::marker,
  .ldna-criteria-summary ul.ldna-crit-list > li::before { content: none !important; }
  .ldna-criteria-summary .ldna-crit-icon { flex-shrink: 0; width: 1rem; margin-top: .2rem; text-align: center;
    font-size: .9rem; color: #0369a1; }
  .ldna-criteria-summary .ldna-crit-body { min-width: 0; }
  .ldna-criteria-summary .ldna-crit-title { font-size: .92rem; font-weight: 600; color: #0f172a; overflow-wrap: anywhere; }
  .ldna-criteria-summary .ldna-crit-meta { font-size: .85rem; color: #334155; }
  .ldna-criteria-summary .ldna-crit-exact,
  .ldna-criteria-summary .ldna-crit-note { margin-top: .2rem; font-size: .78rem; color: #64748b; overflow-wrap: anywhere; }
  .ldna-criteria-summary .ldna-crit-private { margin: -.15rem 0 .5rem; font-size: .8rem; color: #64748b; }
  .ldna-criteria-summary .ldna-crit-areas { display: grid; gap: .35rem; }
  .ldna-criteria-summary .ldna-crit-area-row { display: flex; flex-wrap: wrap; align-items: center; gap: .25rem; }
  .ldna-criteria-summary .ldna-crit-area-label { margin-right: .2rem; font-size: .72rem; font-weight: 600; color: #64748b;
    text-transform: uppercase; letter-spacing: .03em; }
</style>
@endonce
{{-- The section holds reader-facing content only — styles sit outside it. --}}
<section class="ldna-criteria-summary mb-3" data-ldna-criteria-summary>

  @if ($ldnaCriteriaShowAreas)
    <div class="ldna-crit-group" data-ldna-criteria-areas>
      <h6 class="ldna-crit-heading"><i class="fa-solid fa-map" aria-hidden="true"></i>Preferred Locations</h6>
      <div class="ldna-crit-areas">
        @foreach ($ldnaCriteria->areas as $ldnaCritAreaLabel => $ldnaCritAreaValues)
          <div class="ldna-crit-area-row">
            <span class="ldna-crit-area-label">{{ $ldnaCritAreaLabel }}</span>
            @foreach ($ldnaCritAreaValues as $ldnaCritAreaValue)
              <span class="ldna-area-chip {{ $ldnaCritChipClass[$ldnaCritAreaLabel] ?? 'city' }}">{{ $ldnaCritAreaValue }}</span>
            @endforeach
          </div>
        @endforeach
      </div>
    </div>
  @endif

  @if ($ldnaCriteria->radiusSearches)
    <div class="ldna-crit-group">
      <h6 class="ldna-crit-heading"><i class="fa-solid fa-circle-dot" aria-hidden="true"></i>Radius {{ count($ldnaCriteria->radiusSearches) === 1 ? 'Search' : 'Searches' }}</h6>
      <ul class="ldna-crit-list">
        @foreach ($ldnaCriteria->radiusSearches as $ldnaCritRadius)
          <li data-ldna-criteria-radius>
            <i class="fa-solid fa-circle-dot ldna-crit-icon" aria-hidden="true"></i>
            <div class="ldna-crit-body">
              <div class="ldna-crit-title">{{ $ldnaCritRadius['title'] }}</div>
              @if ($ldnaCritRadius['distance'])
                <div class="ldna-crit-meta">{{ $ldnaCritRadius['distance'] }}</div>
              @endif
            </div>
          </li>
        @endforeach
      </ul>
    </div>
  @endif

  @if ($ldnaCriteria->customAreaCount > 0)
    <div class="ldna-crit-group">
      <h6 class="ldna-crit-heading"><i class="fa-solid fa-draw-polygon" aria-hidden="true"></i>Custom Search {{ $ldnaCriteria->customAreaCount === 1 ? 'Area' : 'Areas' }}</h6>
      <ul class="ldna-crit-list">
        <li data-ldna-criteria-custom-areas>
          <i class="fa-solid fa-draw-polygon ldna-crit-icon" aria-hidden="true"></i>
          <div class="ldna-crit-body">
            <div class="ldna-crit-title">{{ $ldnaCriteria->customAreaCount === 1 ? '1 custom area' : $ldnaCriteria->customAreaCount . ' custom areas' }}</div>
            <div class="ldna-crit-meta">Drawn by the client and shown on the map</div>
          </div>
        </li>
      </ul>
    </div>
  @endif

  @if ($ldnaCriteria->importantPlaces)
    <div class="ldna-crit-group">
      <h6 class="ldna-crit-heading"><i class="fa-solid fa-location-dot" aria-hidden="true"></i>Important {{ count($ldnaCriteria->importantPlaces) === 1 ? 'Place' : 'Places' }}</h6>
      @if ($ldnaCriteria->hasPrivatePlaces())
        <div class="ldna-crit-private" data-ldna-criteria-places-private><i class="fa-solid fa-lock me-1" aria-hidden="true"></i>Exact locations are private — only the type of place and the distance are shown.</div>
      @endif
      <ul class="ldna-crit-list">
        @foreach ($ldnaCriteria->importantPlaces as $ldnaCritPlace)
          <li data-ldna-criteria-place>
            <i class="fa-solid fa-location-dot ldna-crit-icon" aria-hidden="true"></i>
            <div class="ldna-crit-body">
              <div class="ldna-crit-title">{{ $ldnaCritPlace['type'] }}</div>
              @if ($ldnaCritPlace['distance'])
                <div class="ldna-crit-meta">{{ $ldnaCritPlace['distance'] }}</div>
              @endif
              @unless ($ldnaCritPlace['private'])
                <div class="ldna-crit-exact" data-ldna-criteria-place-exact>Exact location: {{ $ldnaCritPlace['address'] !== '' ? $ldnaCritPlace['address'] : 'No address provided' }} <span class="text-nowrap">(owner only)</span></div>
                @if ($ldnaCritPlace['on_map'] === 'none')
                  <div class="ldna-crit-note">Not shown on the map — this address has not been located.</div>
                @elseif ($ldnaCritPlace['legacy_minutes'])
                  <div class="ldna-crit-note">Saved as a travel-time preference, which is no longer offered. Shown on the map as a pin, without a radius.</div>
                @endif
              @endunless
            </div>
          </li>
        @endforeach
      </ul>
    </div>
  @endif
</section>
@endif
