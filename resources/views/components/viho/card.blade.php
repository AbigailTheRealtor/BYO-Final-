{{--
    VIHO — card.

    Neutral container. Geometry is byte-identical across all four Create Offer views (43 usages)
    and close to Hire Agent's `.card.description` (13 in Seller alone), so there was nothing to
    reconcile: this reproduces what both already render.

    It makes no decision about what it contains. There is no role prop, no status prop and no
    branch on either — a card that knew it was "the seller's compensation card" would be a product
    component wearing a neutral name.

    The header region is emitted only when there is something to put in it, so a body-only card
    renders exactly the markup a plain container would.

    @param string $title            optional heading text
    @param string $subtitle         optional supporting line
    @param string $icon             optional icon class, decorative (aria-hidden)
    @param string $titleTag         element for the heading — caller owns document outline
    @param bool   $compact          tighter padding
    @param bool   $accent           accent border; colour comes from --viho-accent
    @param slot   $slot             body
    @param slot   $actions          optional header-right region
    @param slot   $footer           optional footer region
--}}
@props([
    'title'    => null,
    'subtitle' => null,
    'icon'     => null,
    'titleTag' => 'h3',
    'compact'  => false,
    'accent'   => false,
])

@php
    // Only ever one of a fixed set of element names — never an attribute position, and never
    // caller-controlled markup.
    $tag = in_array($titleTag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p'], true) ? $titleTag : 'h3';

    $hasHead = $title !== null || $subtitle !== null || isset($actions);
@endphp

<div {{ $attributes->merge(['class' => 'viho-card' . ($compact ? ' viho-card-compact' : '') . ($accent ? ' viho-card-accent' : '')]) }}>
    @if ($hasHead)
        <div class="viho-card-head">
            <div class="viho-card-head-main">
                @if ($title !== null)
                    <{{ $tag }} class="viho-section-header-title">
                        @if ($icon)<i class="{{ $icon }} viho-section-header-icon" aria-hidden="true"></i>@endif
                        {{ $title }}
                    </{{ $tag }}>
                @endif

                @if ($subtitle !== null)
                    <p class="viho-section-header-desc">{{ $subtitle }}</p>
                @endif
            </div>

            @isset($actions)
                <div class="viho-card-head-actions">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="viho-card-body">{{ $slot }}</div>

    @isset($footer)
        <div class="viho-card-foot">{{ $footer }}</div>
    @endisset
</div>
