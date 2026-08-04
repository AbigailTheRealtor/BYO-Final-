{{--
    VIHO — section navigation.

    A horizontal bar of in-page links: one per section of a long document, in the order the caller
    supplies. Released from deferral in M5.2, the second release after `hero`.

    IT NAVIGATES NOTHING BY ITSELF. The component renders anchors and marks one of them current.
    Sticky offset, smooth scrolling and updating the current link while the reader scrolls are
    BEHAVIOUR, and behaviour belongs to the product: the guard tests forbid `<script>`, `document.`
    and `window.` inside a primitive, so a caller attaches those to `[data-viho-section-nav]`.
    A component that shipped its own scroll listener would also be deciding, on every page that
    adopted it, what "current" means.

    IT DECIDES NOTHING ABOUT WHAT IS IN THE LIST. Which sections exist, whether the viewer may see
    them, and what they are called are all resolved before the array arrives. This matters more
    here than for most primitives: a nav is exactly where an authorization mistake becomes visible,
    because an entry for a section the viewer cannot see leaks both the section's existence and its
    name. The primitive cannot make that mistake because it cannot see the viewer.

    NO IDs OF ITS OWN. Every `href` is `#` plus a caller-supplied id, and the component emits no
    `id` attribute anywhere — two navs on one page must not collide. Product-specific id prefixes
    (`hla-section-*`) live with the product.

    MALFORMED ROWS ARE DROPPED, NOT RENDERED. A row missing an id or a label would otherwise emit
    a link to `#` or a link with no accessible name. Both are worse than a shorter list.

    @param array   $items      [['id' => string, 'label' => string], …] — order is preserved
    @param ?string $current    id of the section to mark aria-current; caller decides
    @param ?string $ariaLabel  accessible name for the nav landmark
--}}
@props([
    'items'     => [],
    'current'   => null,
    'ariaLabel' => null,
])

@php
    // Defensive rather than decorative: a caller that hands over a malformed row loses that row,
    // rather than emitting a dead anchor or one with no accessible name.
    $navItems = array_values(array_filter(
        is_array($items) ? $items : [],
        fn ($i) => is_array($i)
            && isset($i['id'], $i['label'])
            && is_scalar($i['id'])
            && is_scalar($i['label'])
            && trim((string) $i['id']) !== ''
            && trim((string) $i['label']) !== ''
    ));
@endphp

@if ($navItems)
    <nav {{ $attributes->merge(['class' => 'viho-section-nav']) }}
         data-viho-section-nav
         @if ($ariaLabel) aria-label="{{ $ariaLabel }}" @endif>
        <ul class="viho-section-nav-list">
            @foreach ($navItems as $item)
                <li class="viho-section-nav-item">
                    <a class="viho-section-nav-link"
                       href="#{{ trim((string) $item['id']) }}"
                       data-viho-section-nav-link
                       @if ($current !== null && (string) $current === (string) $item['id']) aria-current="true" @endif>
                        {{ $item['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
