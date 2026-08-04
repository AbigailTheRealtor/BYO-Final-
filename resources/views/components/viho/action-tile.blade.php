{{--
    VIHO — action tile.

    The icon + label + description tile Create Offer uses in its interaction grid, present in all
    four views.

    ELEMENT CHOICE FOLLOWS THE SAME RULE AS THE BUTTON. With `href` it is an anchor; with an
    `action` slot the caller supplies its own control (a form button, most often); with neither it
    is an inert `<div>` — which is correct precisely because a tile with nothing to activate should
    not pretend to be interactive.

    NO ROUTE NAMES. `href` comes from the caller.

    @param string $href
    @param string $icon         optional icon class, decorative (aria-hidden)
    @param string $label
    @param string $description  optional
    @param bool   $active
    @param bool   $disabled
    @param slot   $action       caller-supplied control, used instead of $href
--}}
@props([
    'href'        => null,
    'icon'        => null,
    'label',
    'description' => null,
    'active'      => false,
    'disabled'    => false,
])

@php
    $classes = 'viho-action-tile'
        . ($active ? ' viho-action-tile-active' : '')
        . ($disabled ? ' viho-action-tile-disabled' : '');

    $isAnchor = $href !== null && ! isset($action);
    $shared   = ['class' => $classes];

    if ($disabled) {
        $shared['aria-disabled'] = 'true';
    }
@endphp

@if ($isAnchor)
    <a {{ $attributes->merge($shared) }} @if (! $disabled) href="{{ $href }}" @endif>
        @if ($icon)<i class="{{ $icon }} viho-action-tile-icon" aria-hidden="true"></i>@endif
        <span class="viho-action-tile-label">{{ $label }}</span>
        @if ($description !== null)
            <span class="viho-action-tile-desc">{{ $description }}</span>
        @endif
        {{ $slot }}
    </a>
@else
    <div {{ $attributes->merge($shared) }}>
        @if ($icon)<i class="{{ $icon }} viho-action-tile-icon" aria-hidden="true"></i>@endif
        <span class="viho-action-tile-label">{{ $label }}</span>
        @if ($description !== null)
            <span class="viho-action-tile-desc">{{ $description }}</span>
        @endif
        {{ $slot }}
        @isset($action)
            <div class="viho-action-tile-action">{{ $action }}</div>
        @endisset
    </div>
@endif
