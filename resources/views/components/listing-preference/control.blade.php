@props([
    'listingType',
    'listingId',
    // Card presentation: the same control, laid out for a result card.
    // NOT a second component — see the note below.
    'compact' => false,
    // WHICH SURFACE'S WRITE ROUTES TO POST TO.
    //
    // Each surface has its own route group carrying `preference_surface` as a
    // ROUTE DEFAULT, so the recorded event says where the choice was really
    // made and a browser cannot relabel it. An unrecognised value falls back to
    // the detail routes rather than rendering a broken link.
    'surface' => \App\Support\ListingPreferences\ListingPreferenceSurface::DETAIL,
])

{{--
    Save | Maybe | Pass — the one shared control.

    REUSABLE BY CONSTRUCTION. It takes a listing reference and nothing else: no
    current state, no chip list, no route. Everything is resolved here through
    the same services the write path uses, so search results, Virtual Drive and
    recommendation surfaces can drop this in without a second copy of the
    business logic. Phase 2 wired the BYO Seller/Landlord detail pages; Phase 3A
    adds the four property result surfaces through the `compact` prop.

    ONE COMPONENT, TWO LAYOUTS, AND THAT IS THE WHOLE POINT. `compact` changes
    where the tray sits and how big the buttons are. It changes nothing about
    what is offered, what is written, or which endpoint is called — a card and a
    detail page reach the same controller through the same service, so a rule
    can never apply on one surface and not the other. A card-specific component
    would be the duplicate preference logic the governance forbids.

    RESULT PAGES PRIME, THEY DO NOT CHANGE THE CONTRACT. A page of cards calls
    `x-listing-preference.prefetch` first and this control then finds its state
    and context already resolved, so 150 cards cost a bounded number of queries
    instead of two each. A page that primes nothing still works: the control
    falls back to its own reads. Slower, never different.

    THE CHIPS COME FROM THE CATALOG, NEVER FROM THIS FILE. There is no reason
    list in this template and none in the JavaScript — a second list would be a
    second vocabulary, and the Fair Housing exclusions that travel with the
    governed reason catalog would not travel with it. (The catalog's config file
    is deliberately not named here: a test asserts ListingPreferenceConfig is its
    only reader, and a mention would register as a second one.)

    A GUEST SEES THE CONTROL AND IS SENT TO LOGIN. No anonymous row is created;
    `intended` preserves the listing so they come back to it.
--}}

@php
    use App\Services\ListingPreferences\ListingPreferenceReader;
    use App\Support\ListingPreferences\ListingPreferenceAvailability;
    use App\Support\ListingPreferences\ListingPreferenceChipCatalog;
    use App\Support\ListingPreferences\ListingPreferencePrefetch;
    use App\Support\ListingPreferences\ListingPreferenceState;
    use App\Support\ListingPreferences\ListingPreferenceSurface;
    use App\Support\SmartTags\SmartTagListingRef;
    use App\Support\SmartTags\SmartTagListingType;

    $lpRender    = false;
    $lpGuest     = false;
    $lpCompact   = $compact === true;
    $lpCurrent   = ['state' => null, 'reasons' => []];
    $lpChipToken = ListingPreferenceChipCatalog::UNRESOLVED_TOKEN;
    $lpChipBlob  = null;
    $lpRoutes    = ListingPreferenceSurface::routes(is_string($surface) ? $surface : null);

    try {
        $lpType = SmartTagListingType::tryFrom((string) $listingType);
        $lpId   = (int) $listingId;

        if ($lpType !== null && $lpId > 0) {
            $lpRef      = new SmartTagListingRef($lpType, $lpId);
            $lpReader   = app(ListingPreferenceReader::class);
            $lpPrefetch = app(ListingPreferencePrefetch::class);

            // Phase 2's context obligation: resolve the real listing context
            // before offering chips, so the null-context fallback is reached
            // only when the data genuinely cannot answer. A primed page has
            // already done this in batch; the answer is identical either way,
            // because both come from the same reader.
            $lpContext = $lpPrefetch->hasContext($lpRef)
                ? $lpPrefetch->context($lpRef)
                : $lpReader->contextFor($lpRef);

            $lpAvail = ListingPreferenceAvailability::for(auth()->user(), $lpContext);

            $lpRender = $lpAvail->shouldRender();
            $lpGuest  = $lpAvail->isGuest();

            if ($lpRender) {
                // The chips themselves are the governed catalog, filtered by
                // state and context. What changes on a card page is only WHERE
                // the payload is written: once per context, not once per card.
                $lpCatalog   = app(ListingPreferenceChipCatalog::class);
                $lpChipToken = $lpCatalog->token($lpContext);
                $lpChipBlob  = $lpCatalog->takePayload($lpContext);

                if ($lpAvail->allowed) {
                    $lpUserId = (int) auth()->id();

                    $lpCurrent = $lpPrefetch->hasCurrent($lpUserId, $lpAvail->seekerRole, $lpRef)
                        ? $lpPrefetch->current($lpRef)
                        : $lpReader->current($lpUserId, $lpAvail->seekerRole, $lpRef);
                }
            }
        }
    } catch (\Throwable $e) {
        // A listing page must never fail because a preference control could
        // not be prepared. Rendering nothing is the safe degradation.
        $lpRender = false;
    }
@endphp

@if($lpRender)
{{-- The chip payload for this listing's context, written the first time that
     context appears in the response. Later controls sharing it carry only the
     token. A mixed sale/lease page emits one payload per context, which is what
     keeps each card's chips correct. --}}
@if($lpChipBlob !== null)
<script type="application/json" data-lp-chip-catalog="{{ $lpChipToken }}">@json($lpChipBlob)</script>
@endif

<div class="lp-control{{ $lpCompact ? ' lp-compact' : '' }}"
     data-lp-control
     @if($lpCompact) data-lp-compact="1" @endif
     data-lp-listing-type="{{ $listingType }}"
     data-lp-listing-id="{{ $listingId }}"
     data-lp-guest="{{ $lpGuest ? '1' : '0' }}"
     data-lp-login-url="{{ route('login') }}"
     data-lp-state-url="{{ $lpRoutes['state'] }}"
     data-lp-reasons-url="{{ $lpRoutes['reasons'] }}"
     data-lp-clear-url="{{ $lpRoutes['clear'] }}"
     data-lp-csrf="{{ csrf_token() }}"
     data-lp-chip-context="{{ $lpChipToken }}"
     data-lp-current='@json($lpCurrent)'>

    <div class="lp-buttons" role="group" aria-label="Save, maybe or pass on this listing">
        @foreach(ListingPreferenceState::cases() as $lpState)
            <button type="button"
                    class="lp-btn lp-btn-{{ $lpState->value }}{{ $lpCurrent['state'] === $lpState->value ? ' is-active' : '' }}"
                    data-lp-state="{{ $lpState->value }}"
                    aria-pressed="{{ $lpCurrent['state'] === $lpState->value ? 'true' : 'false' }}">
                <i class="fa-regular {{ ['save' => 'fa-bookmark', 'maybe' => 'fa-circle-question', 'pass' => 'fa-circle-xmark'][$lpState->value] }}"></i>
                <span>{{ ucfirst($lpState->value) }}</span>
            </button>
        @endforeach
    </div>

    {{-- Reason tray. Populated from the catalog payload above, never from a
         list held in this template or in the script. --}}
    <div class="lp-tray" data-lp-tray hidden>
        <div class="lp-tray-prompt" data-lp-prompt></div>
        <div class="lp-chips" data-lp-chips></div>
        <div class="lp-tray-actions">
            <button type="button" class="lp-tray-done" data-lp-done>Done</button>
            <button type="button" class="lp-tray-clear" data-lp-clear>Remove</button>
        </div>
    </div>

    <div class="lp-status" data-lp-status role="status" aria-live="polite"></div>
</div>

{{-- The stylesheet and the behaviour, emitted once per document.

     A SEPARATE COMPONENT because a page may need them BEFORE any control
     exists: the Virtual Drive injects server-rendered controls into the DOM
     after load, and markup inserted with innerHTML never executes its scripts.
     Such a page emits <x-listing-preference.assets /> itself; the @once inside
     means doing so costs nothing when a control has already emitted them. --}}
<x-listing-preference.assets />
@endif
