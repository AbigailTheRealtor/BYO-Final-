{{--
    Hire Agent Listing Detail Framework — one label/value row.

    The four detail views contain 340 rows of this exact shape:

        <div class="col-md-12 col-12 pt-2 fw-bold">Label:
            <span class="removeBold">value</span>
        </div>

    The markup and classes are reproduced verbatim so that adopting the component changes nothing
    about how a row renders — the existing shared CSS already targets
    `.col-md-12.col-12.pt-2.fw-bold` and `.removeBold`, and both still apply.

    The row renders nothing at all when the value is absent, matching the @if (…!= null) guard
    that wraps essentially every one of those 340 rows today.

    @param string $label
    @param mixed  $value  omitted entirely when the display helper counts it as absent
    @param string $width  column width class, for the few rows that sit half-width
--}}
@props(['label', 'value' => null, 'width' => 'col-md-12 col-12'])

@if (\App\Helpers\ListingDisplayHelper::hasValue($value) || isset($slot) && trim($slot) !== '')
    <div class="{{ $width }} pt-2 fw-bold">{{ $label }}
        <span class="removeBold">{{ trim($slot) !== '' ? $slot : $value }}</span>
    </div>
@endif
