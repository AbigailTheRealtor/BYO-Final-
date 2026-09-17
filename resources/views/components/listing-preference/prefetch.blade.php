@props([
    'listingType',
    'listingIds' => [],
])

{{--
    Prime a page of listings before the card loop runs.

    WHY A PAGE MUST CALL THIS. `x-listing-preference.control` resolves its own
    state, which is right for a detail page and ruinous for a results page: the
    same two reads per card become hundreds of queries for one screen. This
    resolves the whole page in batch first — one subject query per listing type,
    one preference query for the page, one context query per type — and every
    control below then finds its answer already in memory.

    IT RENDERS NOTHING, and a page that forgets it is slower, never wrong: an
    unprimed control falls back to its own single-listing reads. That is the
    safe direction, and it is why this is an optimisation rather than a second
    code path with its own idea of a customer's preferences.

    NO WRITES, NO DECISIONS. Every value comes from ListingPreferenceReader —
    the same reader the unprimed path uses.
--}}

@php
    use App\Support\ListingPreferences\ListingPreferenceAvailability;
    use App\Support\ListingPreferences\ListingPreferencePrefetch;
    use App\Support\ListingPreferences\SeekerRole;
    use App\Support\SmartTags\SmartTagListingType;

    try {
        // Nothing is read while the feature is off: with no control rendering,
        // a prime would be queries spent on a page that shows nothing.
        if (ListingPreferenceAvailability::featureEnabled()) {
            $lpPrimeType = SmartTagListingType::tryFrom((string) $listingType);

            if ($lpPrimeType !== null) {
                $lpPrimeUser = auth()->user();
                $lpPrimeRole = $lpPrimeUser === null
                    ? null
                    : SeekerRole::forUserType(is_string($lpPrimeUser->user_type ?? null) ? $lpPrimeUser->user_type : null);

                app(ListingPreferencePrefetch::class)->prime(
                    $lpPrimeType,
                    $listingIds instanceof \Illuminate\Support\Collection ? $listingIds->all() : (array) $listingIds,
                    // A guest and a non-seeker have no stored state to fetch;
                    // their contexts are still primed, because the control is
                    // rendered for a guest and needs one.
                    $lpPrimeRole === null ? null : (int) $lpPrimeUser->getAuthIdentifier(),
                    $lpPrimeRole,
                );
            }
        }
    } catch (\Throwable $e) {
        // A results page must never fail because a prefetch could not run.
        // Every control below still resolves itself.
    }
@endphp
