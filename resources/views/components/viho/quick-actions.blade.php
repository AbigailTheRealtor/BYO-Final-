{{--
    VIHO — quick actions.

    A labelled band of action tiles. Released from deferral in M5.3, the third release after `hero`
    and `section-nav`.

    IT IS A CONTAINER, NOT A MENU. The tiles arrive through the default slot, already built and
    already ordered by the caller. This is the whole design: an `items` array would have to grow a
    vocabulary for forms, modal triggers, multiple CTAs per tile and disabled placeholders — every
    one of which is product vocabulary — and the primitive would end up deciding what an action is.
    Composing x-viho.action-tile children keeps that where it belongs.

    IT DECIDES NOTHING ABOUT WHO MAY SEE WHAT. It cannot see the viewer, so it cannot leak to one.
    That is load-bearing rather than incidental: an action band is precisely where an authorization
    mistake becomes visible, because a tile advertises both that a workflow exists and what it is
    called. Whether a viewer may be offered an action is settled before the slot is composed, and
    the product is required to classify every tile it adds — public, authenticated, agent-only, or
    listing-owner-only — with owner-only and agent-only workflows kept out of a public band
    entirely.

    NO COLUMN COUNT. The grid is `auto-fit`/`minmax`, so the track count follows the available
    width. A `columns` prop would let a caller hard-code the number that happens to look right on
    their widest breakpoint and be wrong at every other one — and the caller cannot know how much
    room the band has, because that depends on where the host page puts it.

    NO SCRIPT. Copy-to-clipboard, native share sheets and modal triggers are behaviour, and
    behaviour belongs to the product; the guard tests forbid `<script>`, `document.` and `window.`
    in a primitive. A caller attaches those to its own controls inside the slot.

    NOTHING IS RENDERED FOR AN EMPTY SLOT. A band with a heading and no tiles is worse than no band:
    it reads as a section that failed to load.

    @param ?string $label      optional band heading ("Quick Actions")
    @param ?string $icon       optional icon for the heading, decorative (aria-hidden)
    @param ?string $ariaLabel  accessible name for the region landmark
    @param slot    $default    the tiles, caller-ordered
--}}
@props([
    'label'     => null,
    'icon'      => null,
    'ariaLabel' => null,
])

@php
    // `$slot->isEmpty()` is not enough on its own: a caller whose tiles are all conditioned out
    // still hands over a slot full of whitespace and Blade comments, which is exactly what an
    // authorization-gated band looks like when the viewer may see none of it.
    $hasTiles = trim((string) $slot) !== '';
@endphp

@if ($hasTiles)
    <section {{ $attributes->merge(['class' => 'viho-quick-actions']) }}
             data-viho-quick-actions
             @if ($ariaLabel) aria-label="{{ $ariaLabel }}" @endif>
        @if ($label !== null)
            <p class="viho-quick-actions-label">
                @if ($icon)<i class="{{ $icon }} viho-quick-actions-label-icon" aria-hidden="true"></i>@endif
                {{ $label }}
            </p>
        @endif

        <div class="viho-quick-actions-grid">{{ $slot }}</div>
    </section>
@endif
