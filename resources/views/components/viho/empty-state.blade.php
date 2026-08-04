{{--
    VIHO — empty state.

    THE ONE PRIMITIVE HERE THAT IS NOT AN EXTRACTION, and it is worth being explicit about that.
    The audit looked for a repeated empty-state treatment and did not find one. Both products do
    say "No Record Found!", "No listings found" and "No bids yet on this listing.", but as bare
    strings with no shared markup or class, so there was no existing appearance to preserve. What
    the four Create Offer views share instead is an `.fst-italic` span — and that turned out to be
    the Ask-AI example placeholder, driven by JavaScript through a product-specific id, not an
    empty state at all.

    So this is a forward-looking neutral contract rather than something lifted from the pages. Its
    geometry and colour come from tokens only. Nothing renders it yet, and the milestone that first
    does should check it against a real design rather than treat its presence here as approval.

    The icon is decorative: it repeats what the title already says, so it is hidden from assistive
    technology rather than announced twice.

    @param string $icon         optional icon class, decorative (aria-hidden)
    @param string $title
    @param string $description  optional
    @param slot   $action       optional call to action
--}}
@props([
    'icon'        => null,
    'title',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'viho-empty-state']) }}>
    @if ($icon)
        <div class="viho-empty-state-icon"><i class="{{ $icon }}" aria-hidden="true"></i></div>
    @endif

    <p class="viho-empty-state-title">{{ $title }}</p>

    @if ($description !== null)
        <p class="viho-empty-state-desc">{{ $description }}</p>
    @endif

    @isset($action)
        <div class="viho-empty-state-action">{{ $action }}</div>
    @endisset
</div>
