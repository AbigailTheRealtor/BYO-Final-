{{--
    Phase 5 — the one line that says Your Home Taste is (or could be) shaping
    the Best Match order, and the one link that turns it off or back on.

    Rendered only when the controller's outcome offers the toggle: the feature is
    on, the viewer is a Buyer/Tenant shopping this market, the order is Best Match
    and their taste has something to act on (or they switched it off). Every other
    state renders nothing, so the page is exactly as it was.

    Not called AI, and no number: the customer is told what happened and how to
    see the standard order, nothing about weights.
--}}
@if(($status ?? null) === \App\Services\ListingPreferences\Taste\TasteRerankOutcome::PERSONALIZED && ! empty($urls))
    <span class="d-block small text-muted mt-1" data-taste-rerank="personalized">
        <i class="fas fa-sliders-h me-1" aria-hidden="true"></i>Personalized with
        <a href="{{ route('listing-preferences.mine.taste') }}">Your Home Taste</a>
        &middot;
        <a href="{{ $urls['standard'] }}" data-taste-rerank-toggle="standard">Show standard Best Match order</a>
    </span>
@elseif(($status ?? null) === \App\Services\ListingPreferences\Taste\TasteRerankOutcome::OPTED_OUT && ! empty($urls))
    <span class="d-block small text-muted mt-1" data-taste-rerank="standard">
        Standard Best Match order &middot;
        <a href="{{ $urls['personalized'] }}" data-taste-rerank-toggle="personalized">Personalize with Your Home Taste</a>
    </span>
@endif
