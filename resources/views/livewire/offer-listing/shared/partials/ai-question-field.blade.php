{{--
    Single Ask AI knowledge-base question field.
    Expects: $q = ['key','label','placeholder','tooltip','public_safe'], $genericPlaceholder (string).

    `public_safe` marks the curated questions whose answer may be restated on the PUBLIC
    listing page. It is disclosure only — there is no checkbox, no toggle, no consent
    field, no stored value and no column. An owner who would rather not publish something
    controls that the way they always have: by leaving the box empty or rewriting it.
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
    @if (! empty($q['public_safe']))
        <div class="ai-faq-public-note small text-muted mb-1" data-ai-faq-public-safe="{{ $q['key'] }}">
            <i class="fa-solid fa-eye me-1" aria-hidden="true"></i>May appear in the public Ask AI section when answered.
        </div>
    @endif
    <textarea class="form-control ai-faq-textarea" rows="2"
        wire:model.defer="listing_ai_faq.{{ $q['key'] }}"
        placeholder="{{ $q['placeholder'] ?? $genericPlaceholder }}"></textarea>
</div>
