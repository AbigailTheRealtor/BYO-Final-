@php
    /** @var \App\Services\ListingPreferences\ListingPreferenceListingCard $card */
    $showControl = $showControl ?? true;
    /** @var list<string> $reasons catalog LABELS, resolved by the reader — never keys */
    $reasons     = $reasons ?? [];
    // Enough to recall why, not a wall of chips: the rest are one tap away in
    // the control's own tray.
    $shownReasons = array_slice($reasons, 0, 3);
    $moreReasons  = count($reasons) - count($shownReasons);
@endphp

{{--
    One property in the customer's own Saved / Maybe / Passed list.

    THE UNAVAILABLE CASE IS NOT AN ERROR. A listing can be withdrawn after
    somebody passed on it, and their decision is still theirs. Such a card says
    the listing is no longer available and INVENTS NOTHING — no address, no
    price, no facts — while keeping the preference control working so they can
    still change or remove their own choice.
--}}

<div class="card h-100 shadow-sm">
    <div class="card-body pb-2">
        @if($card->available)
            <h6 class="card-title mb-1">
                @if($card->url)
                    <a href="{{ $card->url }}" class="text-decoration-none">{{ $card->title }}</a>
                @else
                    {{ $card->title }}
                @endif
            </h6>

            <p class="mb-1 text-muted small">{{ $card->sourceLabel }}</p>

            @if($card->locationLine)
                <p class="mb-1 small">{{ $card->locationLine }}</p>
            @endif

            @if($card->factsLine)
                <p class="mb-1 small text-muted">{{ $card->factsLine }}</p>
            @endif

            @if($card->priceDisplay)
                <p class="mb-1"><b>{{ $card->priceDisplay }}</b></p>
            @endif
        @else
            <h6 class="card-title mb-1 text-muted">This listing is no longer available</h6>
            <p class="mb-1 text-muted small">
                {{ $card->sourceLabel }} — it may have been sold, rented or withdrawn.
                Your choice below is still yours to change or remove.
            </p>
        @endif
    </div>

    @if(count($shownReasons) > 0)
        <div class="px-3 pb-2" data-lp-card-reasons>
            <span class="small text-muted me-1">Your reasons:</span>
            @foreach($shownReasons as $label)
                <span class="badge bg-light text-dark border fw-normal me-1 mb-1">{{ $label }}</span>
            @endforeach
            @if($moreReasons > 0)
                <span class="small text-muted">+{{ $moreReasons }} more</span>
            @endif
        </div>
    @endif

    @if($showControl)
        <div class="card-footer bg-light">
            {{--
                The SAME shared control every other surface renders, in its card
                layout. `surface="account"` selects this area's own write routes,
                so the event records where the choice was actually made — the
                surface is a route default, never something the browser sends.
            --}}
            <x-listing-preference.control
                :listing-type="$card->ref->type->value"
                :listing-id="$card->ref->id"
                :compact="true"
                surface="account" />
        </div>
    @endif
</div>
