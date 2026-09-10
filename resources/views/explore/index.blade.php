@extends('layouts.main')

@section('title', 'Explore')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/explore/explore.css') }}">
@endpush

@section('content')
{{--
    BidYourOffer Explore — the public IDX surface.

    THIS TEMPLATE PUBLISHES NO LISTING DATA.
    ----------------------------------------
    There is no server-rendered marker, no embedded listing array, no @json of a
    property. Everything a consumer sees about a property arrives from
    /api/explore/listings, which re-decides eligibility on every request. A
    template that pre-rendered listings would move a licensing decision into a
    Blade file, and a Blade edit could then change what the public may see.

    The only server values here are configuration: the map credential, the
    opening camera, the filter labels, and the endpoint URLs.
--}}
<div class="explore-shell"
     id="explore-shell"
     data-listings-endpoint="{{ route('explore.api.listings') }}"
     data-listing-endpoint="{{ url('/api/explore/listings') }}"
     data-google-ready="{{ $googleReady ? '1' : '0' }}"
     data-google-key="{{ $googleKey }}"
     data-google-map-id="{{ $googleMapId }}"
     data-google-version="{{ $googleApiVersion }}"
     data-google-libraries="{{ implode(',', $googleLibraries) }}"
     data-max-results="{{ $maxResults }}"
     data-max-span="{{ $maxSpanDegrees }}"
     data-camera="{{ json_encode($defaultCamera) }}">

    <div class="explore-topbar">
        <div class="explore-brand">
            <span class="explore-brand-mark">BidYourOffer</span>
            <span class="explore-brand-sub">Explore</span>
        </div>

        {{--
            Consumer terminology. "For Sale" / "For Rent" — never "Seller" /
            "Landlord", which are internal domain words and mean nothing to a
            member of the public looking for a house.
        --}}
        <div class="explore-filters" role="group" aria-label="Listing type">
            @foreach ($filters as $filter)
                <button type="button"
                        class="explore-filter{{ $loop->first ? ' is-active' : '' }}"
                        data-filter="{{ $filter['value'] }}">{{ $filter['label'] }}</button>
            @endforeach

            {{--
                Property Intelligence is ABSENT, not disabled.

                A greyed-out control would tell every visitor that BidYourOffer
                holds off-market MLS intelligence and is withholding it. It does
                not hold any: there is no VOW approval, dataset, credential or
                registration flow in this application. Rendering the control at
                all would misrepresent what exists.
            --}}
            @if ($propertyIntelligenceAvailable)
                <button type="button" class="explore-filter explore-filter-intel" data-filter="intelligence">
                    Property Intelligence
                </button>
            @endif
        </div>

        <div class="explore-status" id="explore-status" aria-live="polite"></div>
    </div>

    <div class="explore-stage">
        <div class="explore-map" id="explore-map">
            @unless ($googleReady)
                {{--
                    The deliberate unavailable state.

                    Explore is enabled and its data endpoints work; the 3D
                    renderer has no browser credential in this environment. Said
                    out loud, because a blank grey rectangle is indistinguishable
                    from a broken map and no PHP test can tell them apart.
                --}}
                <div class="explore-map-unavailable" role="status">
                    <div class="explore-map-unavailable-inner">
                        <h2>The 3D neighbourhood is unavailable</h2>
                        <p>{{ $googleUnavailable }}</p>
                        <p class="explore-map-unavailable-note">
                            Listings are still being returned for this area and are shown in the list below.
                        </p>
                    </div>
                </div>
            @endunless
        </div>

        <aside class="explore-panel" id="explore-panel" hidden aria-label="Selected property"></aside>

        <div class="explore-results" id="explore-results" aria-label="Listings in view"></div>
    </div>

    <footer class="explore-attribution" id="explore-attribution"></footer>
</div>
@endsection

@push('scripts')
    {{--
        A static asset, not a Mix bundle, and not part of app.js.

        Not app.js, because a map surface must not reach the bundle every
        consumer page already loads — the same reason the Location DNA MapLibre
        renderer has its own entry point.

        Not a Mix bundle, because this file has no imports and nothing to
        resolve: the Google Maps JavaScript API is a runtime script tag the file
        adds itself, and only when a browser key is present. Compiling a plain
        IIFE would buy nothing and would couple /explore to a build step. Same
        convention as js/select2-stable.js and the other hand-authored scripts
        the layout already serves — the lightest integration consistent with the
        existing application, and no new npm dependency.
    --}}
    <script src="{{ asset('js/explore/explore-3d.js') }}" defer></script>
@endpush
