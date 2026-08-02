{{--
    VIHO — stat item.

    A label with a value beside it. Taken from the Create Offer activity row, which appears in all
    four views.

    IT DOES NOT CALCULATE. `$value` is rendered exactly as supplied. No counting, no summing, no
    formatting, no unit inference — everything this component could plausibly compute is something
    one of the two products defines differently, and computing it here would settle that for both.

    DISTINCT FROM kv. The key/value row is for a field on a record; this is for a figure with a
    caption, and they carry different type scales in both products today. Merging them would have
    meant one of the two changing size.

    @param string $label
    @param mixed  $value    preformatted display value
    @param string $support  optional supporting line beneath the value
    @param string $icon     optional icon class, decorative (aria-hidden)
    @param bool   $accent   colour the value from --viho-accent
--}}
@props([
    'label',
    'value'   => null,
    'support' => null,
    'icon'    => null,
    'accent'  => false,
])

<div {{ $attributes->merge(['class' => 'viho-stat' . ($accent ? ' viho-stat-accent' : '')]) }}>
    <span class="viho-stat-label">
        @if ($icon)<i class="{{ $icon }} viho-stat-icon" aria-hidden="true"></i>@endif
        {{ $label }}
    </span>

    <span class="viho-stat-value">
        {{ trim($slot ?? '') !== '' ? $slot : $value }}
        @if ($support !== null)
            <span class="viho-stat-support">{{ $support }}</span>
        @endif
    </span>
</div>
