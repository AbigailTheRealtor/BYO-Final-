@props(['title', 'id' => null, 'open' => false])
@php
    $accordionId = $id ?? 'accordion-' . \Illuminate\Support\Str::slug($title) . '-' . uniqid();
@endphp
<div class="accordion mb-3" id="wrap-{{ $accordionId }}">
    <div class="accordion-item border-0 shadow-sm">
        <h2 class="accordion-header" id="heading-{{ $accordionId }}">
            <button class="accordion-button {{ $open ? '' : 'collapsed' }} fw-bold" type="button"
                data-bs-toggle="collapse" data-bs-target="#collapse-{{ $accordionId }}"
                aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="collapse-{{ $accordionId }}">
                {{ $title }}
            </button>
        </h2>
        <div id="collapse-{{ $accordionId }}" class="accordion-collapse collapse {{ $open ? 'show' : '' }}"
            aria-labelledby="heading-{{ $accordionId }}" data-bs-parent="#wrap-{{ $accordionId }}">
            <div class="accordion-body">
                <div class="row">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</div>
