{{--
    VIHO — badge / status pill.

    One component for both treatments: the compact badge Create Offer uses for listing attributes,
    and the larger `pill` variant used for a status chip. They differ only in type scale and
    padding, so a second component would have been the same rules twice.

    IT DOES NOT INFER STATUS. `variant` is supplied by the caller. Nothing here maps "Active" to
    green or "Expired" to grey — that mapping is product vocabulary, it differs between Hire Agent
    and Create Offer, and a shared component that owned it would own the vocabulary of both.

    ROLE ACCENT IS NEVER GUESSED. The `accent` variant paints itself from
    `--viho-accent-bg/-fg/-border`, which the caller sets. This primitive cannot see whether it is
    on a seller page or a tenant page and must not try.

    TWO M1 DISAGREEMENTS PRESERVED:
      · Truncation — Seller and Buyer clamp long badges with an ellipsis; Landlord and Tenant do
        not. Two-two, no majority, so `truncate` is opt-in and neither behaviour is imposed.
      · Landlord's teal status pill — untouched. It is a role accent, reached through the `accent`
        variant, not by this component deciding that landlords get teal.

    @param string $variant   neutral|primary|success|warning|danger|info|accent
    @param bool   $pill      larger status-pill scale
    @param bool   $truncate  opt in to ellipsis clamping
    @param string $icon      optional icon class, decorative (aria-hidden)
--}}
@props([
    'variant'  => 'neutral',
    'pill'     => false,
    'truncate' => false,
    'icon'     => null,
])

@php
    $allowed = ['neutral', 'primary', 'success', 'warning', 'danger', 'info', 'accent'];
    $tone    = in_array($variant, $allowed, true) ? $variant : 'neutral';

    $classes = 'viho-badge viho-badge-' . $tone
        . ($pill ? ' viho-badge-pill' : '')
        . ($truncate ? ' viho-badge-truncate' : '');
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    @if ($icon)<i class="{{ $icon }}" aria-hidden="true"></i>@endif
    {{ $slot }}
</span>
