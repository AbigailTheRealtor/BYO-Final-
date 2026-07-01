{{--
    Single Ask AI knowledge-base question field.
    Expects: $q = ['key','label','placeholder','tooltip'], $genericPlaceholder (string).
--}}
<div class="form-group mb-3">
    <label class="form-label">
        {{ $q['label'] }}
        @if (! empty($q['tooltip']))
            <i class="fa-solid fa-circle-info ms-1 text-muted"
               data-bs-toggle="tooltip"
               title="{{ $q['tooltip'] }}"></i>
        @endif
    </label>
    <textarea class="form-control ai-faq-textarea" rows="2"
        wire:model.defer="listing_ai_faq.{{ $q['key'] }}"
        placeholder="{{ $q['placeholder'] ?? $genericPlaceholder }}"></textarea>
</div>
