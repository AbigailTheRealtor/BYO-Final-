@props(['label', 'value' => null, 'hide' => false])
@if (!$hide && \App\Helpers\ListingDisplayHelper::hasValue($value ?? ($slot->isEmpty() ? null : $slot->toHtml())))
<div class="col-md-12 col-12 pt-2 fw-bold">
    {{ $label }}:
    <span class="removeBold">{{ $value ?? $slot }}</span>
</div>
@endif
