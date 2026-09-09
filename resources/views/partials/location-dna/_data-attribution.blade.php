{{--
    Location DNA data attribution — the notice that travels with the POIs.

    WHY THIS EXISTS
    ---------------
    Two of the licenses behind our points-of-interest data REQUIRE attribution
    wherever the data is published: CDLA-Permissive-2.0 (the bulk of the Overture
    Places theme) and Apache-2.0 (its Foursquare slice). Google's terms ask for a
    credit too. Before this partial, no Location DNA surface said where a place
    name came from — which was survivable only because the corpus provider has
    never been enabled. It stops being survivable the moment it is.

    NOT THE MLS ATTRIBUTION BLOCK.
    `offer-listing/partials/_mls_attribution.blade.php` states where the LISTING
    came from, under the Bridge/Stellar IDX terms. This states where the PLACES
    BESIDE IT came from, under open-data licenses. A listing page can owe both,
    and they are separate statements on purpose: merged into one "data sources"
    line, a reader would reasonably take the Stellar copyright as covering the
    nearby-restaurants list, or the Overture credit as covering the listing. The
    visual language is shared; the claims are not.

    IT RENDERS NOTHING WHEN NOTHING IS OWED.
    Sources are resolved from each POI row's own recorded provenance, never from
    which provider is currently configured — a row outlives the switch that
    fetched it. A page with no POIs, or with rows carrying no provenance, renders
    no markup at all rather than a default credit, because a guessed attribution
    is a false statement about someone else's data.

    Expects ONE of:
      $pois                 iterable of POI rows (models, arrays or stdClass)
      $attributionSources   pre-resolved descriptors, when a caller already has them

    Optional:
      $attributionCompact   bool, default true. Compact = the one-line footer used
                            inside a panel. False = the bordered block used when
                            the attribution stands alone.
--}}
@php
    $ldnaAttrSources = $attributionSources
        ?? \App\Support\LocationDna\LocationDataAttribution::forPois($pois ?? []);

    $ldnaAttrCompact = $attributionCompact ?? true;
@endphp

@if(!empty($ldnaAttrSources))
    <div class="location-dna-attribution {{ $ldnaAttrCompact ? 'mt-3 pt-2 border-top' : 'card shadow-sm border-0 mb-3' }}">
        <div class="{{ $ldnaAttrCompact ? '' : 'card-body py-2 px-3' }}">
            <div style="font-size:.75rem;color:#9ca3af;line-height:1.5;">
                @foreach($ldnaAttrSources as $ldnaAttrSource)
                    <span class="d-inline-block me-2">
                        @if(!empty($ldnaAttrSource['url']))
                            <a href="{{ $ldnaAttrSource['url'] }}" target="_blank" rel="noopener noreferrer"
                               style="color:inherit;text-decoration:underline;">{{ $ldnaAttrSource['statement'] ?? $ldnaAttrSource['name'] }}</a>
                        @else
                            {{ $ldnaAttrSource['statement'] ?? $ldnaAttrSource['name'] }}
                        @endif
                    </span>
                @endforeach
                <a href="{{ route('data-sources') }}" style="color:inherit;text-decoration:underline;">Data sources &amp; licenses</a>
            </div>
        </div>
    </div>
@endif
