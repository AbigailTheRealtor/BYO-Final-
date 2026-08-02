{{--
    VIHO — key/value row.

    The most repeated pattern in either product: ~540 `$row()` calls across the four Create Offer
    views and ~340 label/value rows across the four Hire Agent views.

    TWO LAYOUTS, BOTH REAL. Create Offer renders a 5/7 two-column grid; Hire Agent renders the
    label and value inline on one full-width line. Neither is a mistake and neither is newer, so
    `layout` selects between them rather than one being quietly converted into the other. That
    conversion is a visual decision for M3/M8, not something a primitive should pre-empt.

    IT DOES NOT CALCULATE. `$value` is displayed exactly as handed over. There is no formatter, no
    currency helper, no date parsing and no truthiness rule beyond "was anything supplied" — a
    shared component that decided what counts as an empty value would be making that decision for
    both products at once, and they do not agree today. When nothing is supplied the row renders
    `$empty` if the caller provided one, and otherwise renders nothing at all.

    Note that `$value` is echoed, not raw: `{{ }}` escapes it. A caller with genuinely
    pre-rendered markup passes it through the default slot instead, which is the same trust
    boundary Blade applies everywhere else.

    @param string $label
    @param mixed  $value      preformatted display value
    @param string $empty      text to show when the value is absent; omit to render nothing
    @param string $icon       optional icon class, decorative (aria-hidden)
    @param string $layout     'split' (5/7 columns) | 'inline' (one line)
    @param string $emphasis   'default' | 'emphasized' | 'muted'
    @param slot   $slot       alternative to $value for pre-rendered content
--}}
@props([
    'label',
    'value'    => null,
    'empty'    => null,
    'icon'     => null,
    'layout'   => 'split',
    'emphasis' => 'default',
])

@php
    $hasSlot  = trim($slot ?? '') !== '';
    $hasValue = $hasSlot || ($value !== null && $value !== '' && $value !== false);

    $layoutClass = $layout === 'inline' ? 'viho-kv-inline' : 'viho-kv-split';

    $emphasisClass = match ($emphasis) {
        'emphasized' => ' viho-kv-emphasized',
        'muted'      => ' viho-kv-muted',
        default      => '',
    };
@endphp

@if ($hasValue || $empty !== null)
    <div {{ $attributes->merge(['class' => 'viho-kv ' . $layoutClass . $emphasisClass]) }}>
        <span class="viho-kv-label">
            @if ($icon)<i class="{{ $icon }} viho-kv-icon" aria-hidden="true"></i>@endif
            {{ $label }}
        </span>

        @if ($hasValue)
            <span class="viho-kv-value">{{ $hasSlot ? $slot : $value }}</span>
        @else
            <span class="viho-kv-value viho-kv-empty">{{ $empty }}</span>
        @endif
    </div>
@endif
