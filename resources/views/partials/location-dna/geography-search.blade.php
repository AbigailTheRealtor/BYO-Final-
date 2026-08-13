{{--
    M2 — the geography search box.

    A SHORTCUT INTO THE CASCADE, NOT A SECOND WAY TO CHOOSE. Selecting a result fills the tier
    selects below exactly as using them in order would, so everything downstream — resolver,
    projector, hydrator, stored payload — is the same either way.

    NO JAVASCRIPT AND NO GOOGLE. Every interaction is a Livewire round trip against
    `App\Services\LocationDna\Criteria\Search\GeographySearchRepository`, which reads
    `location_places` and the census corpus. There is deliberately no script tag here: the map
    widget's autocomplete is what M2 exists to replace, and re-introducing a client-side dependency
    would recreate the coupling that made the tier inputs unusable whenever Google failed to load.

    Host contract: App\Http\Livewire\Concerns\HasGeographySearch.
    Rendered by partials/location-dna/geography-cascade, behind `geography_search_enabled`.
--}}

<div class="mb-3" wire:key="geo-search">

    {{-- ── Location-change notice ────────────────────────────────────────────
         Search back-fill deliberately suppresses the generic cleared-selection warning, because
         reshaping the tiers above a chosen place is the user's own doing rather than something
         going wrong. Moving to a DIFFERENT STATE is the exception: the whole previous context goes
         with it, and that is too large a change to make silently. One specific sentence, rather
         than an enumeration of everything dropped. --}}
    @if (($geoStateChangedTo ?? '') !== '')
        <div class="alert alert-info py-2 px-3 d-flex justify-content-between align-items-start"
             style="font-size:.85rem;" wire:key="geo-state-changed">
            <div>
                <i class="fa-solid fa-location-dot me-1"></i>
                Location updated to {{ $geoStateChangedTo }}.
            </div>
            <button type="button" class="btn-close btn-sm ms-2"
                    wire:click="dismissGeographyStateChange" aria-label="Dismiss"></button>
        </div>
    @endif

    <label class="fw-bold d-block mb-1" style="font-size:.88rem;" for="geo-search-input">
        Find a location
        <small class="text-muted d-block fw-normal" style="font-size:.78rem;">
            Search for a city, county, ZIP code or state — we'll fill in the fields below.
        </small>
    </label>

    <div class="position-relative">
        <input type="text"
               id="geo-search-input"
               class="form-control form-control-sm"
               autocomplete="off"
               placeholder="e.g. Clearwater, Pinellas County, 33756"
               wire:model.debounce.300ms="geoSearchTerm">

        @if ($geoSearchTerm !== '')
            <button type="button"
                    class="btn btn-sm btn-link position-absolute top-0 end-0 text-muted"
                    style="text-decoration:none;"
                    wire:click="clearGeographySearch"
                    aria-label="Clear search">&times;</button>
        @endif
    </div>

    {{-- ── Results ───────────────────────────────────────────────────────────
         Every row carries its breadcrumb. That is not decoration: "Springfield" is a real place in
         roughly thirty-four states, and without the context line the list is a coin toss. --}}
    @if (! empty($geoSearchResults))
        <ul class="list-group mt-1 shadow-sm" style="max-height:16rem; overflow-y:auto;" wire:key="geo-search-results">
            @foreach ($geoSearchResults as $result)
                <li class="list-group-item list-group-item-action py-2 px-3"
                    style="cursor:pointer;"
                    wire:key="geo-search-{{ $result['kind'] }}-{{ $result['id'] }}"
                    wire:click="selectGeographyMatch('{{ $result['kind'] }}', '{{ $result['id'] }}')">

                    <div class="d-flex justify-content-between align-items-baseline gap-2">
                        <span style="font-size:.88rem;">{{ $result['label'] }}</span>
                        <span class="badge bg-light text-secondary text-uppercase"
                              style="font-size:.65rem;">{{ $result['kind'] }}</span>
                    </div>

                    @if ($result['breadcrumb'] !== '')
                        <div class="text-muted" style="font-size:.76rem;">{{ $result['breadcrumb'] }}</div>
                    @endif
                </li>
            @endforeach
        </ul>

        {{-- Truncation is reported, never silent — a list that quietly stops at ten reads as
             "this is everything" to someone who cannot find their town in it. --}}
        @if ($geoSearchTruncated)
            <div class="text-muted mt-1" style="font-size:.76rem;">
                More matches exist — keep typing to narrow the list.
            </div>
        @endif
    @elseif ($geoSearchPerformed)
        {{-- Distinguishes "nothing matched" from "you have not typed enough yet", which an empty
             list alone cannot say. --}}
        <div class="text-muted mt-1" style="font-size:.78rem;">
            No matches for “{{ $geoSearchTerm }}”. Try a city, county, ZIP code or state.
        </div>
    @endif
</div>
