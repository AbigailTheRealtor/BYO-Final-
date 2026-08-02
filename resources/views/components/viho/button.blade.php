{{--
    VIHO — button.

    Renders a real `<button>` or a real `<a>`, never a clickable `<div>`.

    WHICH ELEMENT, AND WHY IT MATTERS. Passing `href` produces an anchor; otherwise a button. That
    is not cosmetic: a `<div onclick>` is invisible to keyboard users and to assistive technology,
    and both products contain enough action surfaces that a primitive getting this wrong would
    multiply the mistake rather than contain it.

    `type` DEFAULTS TO "button". An HTML `<button>` inside a form defaults to `type="submit"`, so a
    "Share" control dropped into one of these pages would silently submit it. Callers that mean
    submit say so.

    A DISABLED ANCHOR HAS NO href. `pointer-events:none` alone stops the mouse and nothing else —
    the link stays in the tab order and still activates on Enter. Dropping `href` is what actually
    removes it from the tab order; `aria-disabled` then explains the state, and the class handles
    appearance. All three are needed and none substitutes for the others.

    ICON-ONLY REQUIRES A LABEL, AND SAYS SO LOUDLY. An icon-only control with no accessible name
    is announced as "button" and nothing more. Rather than render that quietly, this throws — a
    failure at author time is recoverable, one that ships is not.

    NO ROUTES. `href` arrives from the caller. Nothing here calls `route()`, and it cannot: route
    names are product vocabulary.

    @param string $href      presence selects an anchor over a button
    @param string $type      button|submit|reset — ignored for anchors
    @param string $variant   primary|secondary|outline|subtle|success|danger
    @param bool   $block     full-width, left-aligned
    @param bool   $iconOnly  icon with no visible text — requires $label
    @param string $icon      optional icon class, decorative (aria-hidden)
    @param string $label     accessible name; required when $iconOnly
    @param bool   $disabled
    @param bool   $loading
--}}
@props([
    'href'     => null,
    'type'     => 'button',
    'variant'  => 'primary',
    'block'    => false,
    'iconOnly' => false,
    'icon'     => null,
    'label'    => null,
    'disabled' => false,
    'loading'  => false,
])

@php
    $allowed = ['primary', 'secondary', 'outline', 'subtle', 'success', 'danger'];
    $tone    = in_array($variant, $allowed, true) ? $variant : 'primary';

    $isAnchor  = $href !== null;
    $isBlocked = $disabled || $loading;

    // An icon-only control needs a name from somewhere. `label` is the supported route; an
    // explicit aria-label passed through $attributes is accepted as an equivalent so this does not
    // fight a caller who has already done the right thing another way.
    if ($iconOnly && ($label === null || trim((string) $label) === '') && ! $attributes->has('aria-label')) {
        throw new \InvalidArgumentException(
            'x-viho.button: icon-only buttons must supply :label (or an explicit aria-label). '
            . 'Without one the control is announced as "button" with no indication of what it does.'
        );
    }

    $classes = 'viho-btn viho-btn-' . $tone
        . ($block ? ' viho-btn-block' : '')
        . ($iconOnly ? ' viho-btn-icon' : '')
        . ($isBlocked ? ' viho-btn-disabled' : '')
        . ($loading ? ' viho-btn-loading' : '');

    $shared = ['class' => $classes];

    if ($label !== null && ! $attributes->has('aria-label')) {
        $shared['aria-label'] = $label;
    }
    if ($loading) {
        $shared['aria-busy'] = 'true';
    }
    if ($isBlocked) {
        $shared['aria-disabled'] = 'true';
    }

    $buttonType = in_array($type, ['button', 'submit', 'reset'], true) ? $type : 'button';
@endphp

@if ($isAnchor)
    {{-- href is omitted entirely when blocked: that, not CSS, is what takes it out of the tab order. --}}
    <a {{ $attributes->merge($shared) }} @if (! $isBlocked) href="{{ $href }}" @endif>
        @if ($icon)<i class="{{ $icon }}" aria-hidden="true"></i>@endif
        @unless ($iconOnly){{ $slot }}@endunless
    </a>
@else
    <button {{ $attributes->merge($shared) }} type="{{ $buttonType }}" @disabled($isBlocked)>
        @if ($icon)<i class="{{ $icon }}" aria-hidden="true"></i>@endif
        @unless ($iconOnly){{ $slot }}@endunless
    </button>
@endif
