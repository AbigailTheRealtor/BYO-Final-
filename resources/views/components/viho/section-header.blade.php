{{--
    VIHO — section header.

    The same typography the card header uses (700 / 1.05rem / -0.01em), available on its own for
    sections that are not inside a card.

    THE HEADING LEVEL IS THE CALLER'S. Hire Agent's existing cards open with `h4.section-title`;
    Create Offer's card headers are a styled `div` with no heading semantics at all. A primitive
    that hard-coded either would impose a document outline on every page that adopted it — and on
    a page that already has an `h1`, an unconditional `h4` skips a level. `tag` therefore defaults
    to `h3` and accepts `div` or `p` for the genuinely non-heading case, which is what Create Offer
    renders today.

    @param string $title        heading text
    @param string $description  optional supporting line
    @param string $icon         optional icon class, decorative (aria-hidden)
    @param string $tag          heading element, or div/p for non-heading rendering
    @param slot   $actions      optional right-hand region
--}}
@props([
    'title',
    'description' => null,
    'icon'        => null,
    'tag'         => 'h3',
])

@php
    $element = in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p'], true) ? $tag : 'h3';
@endphp

<div {{ $attributes->merge(['class' => 'viho-section-header']) }}>
    <div class="viho-card-head-main">
        <{{ $element }} class="viho-section-header-title">
            @if ($icon)<i class="{{ $icon }} viho-section-header-icon" aria-hidden="true"></i>@endif
            {{ $title }}
        </{{ $element }}>

        @if ($description !== null)
            <p class="viho-section-header-desc">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="viho-section-header-actions">{{ $actions }}</div>
    @endisset
</div>
