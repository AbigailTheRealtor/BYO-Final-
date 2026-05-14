{{--
    Editable AI Questions tab content.
    Consumes Livewire component props: $user_type, $property_type, $listing_ai_faq (array).
    No variables need to be passed from the parent @include.
--}}
@php
    $propertyType = $property_type ?? '';
    $configMap = [
        'seller'   => 'ai_faq_seller',
        'buyer'    => 'ai_faq_buyer',
        'landlord' => 'ai_faq_landlord',
    ];
    $configKey = $configMap[$user_type] ?? null;
    $isTenant  = ($user_type === 'tenant');
    $genericPlaceholder = 'Enter your answer here (e.g., provide as much detail as you\'d like — all fields are optional)';
@endphp

<div class="ai-questions-tab-content py-2">
    <p class="text-muted small mb-4">
        These answers power the AI assistant for your listing. All fields are optional — fill in what you know and leave the rest blank.
    </p>

    @if ($isTenant)
        {{-- Tenant: flat array with commercial_only filtering and per-question placeholder --}}
        @php
            $allQ   = config('tenant_ai_faq.questions', []);
            $groups = [];
            foreach ($allQ as $q) {
                if (!empty($q['commercial_only']) && $propertyType !== 'Commercial Property') continue;
                $groups[$q['category']][] = $q;
            }
        @endphp

        @foreach ($groups as $category => $questions)
            <h6 class="mt-4 mb-2 fw-bold border-bottom pb-1">{{ $category }}</h6>
            @foreach ($questions as $q)
                <div class="form-group mb-3">
                    <label class="form-label">{{ $q['label'] }}</label>
                    <textarea class="form-control ai-faq-textarea" rows="2"
                        wire:model.defer="listing_ai_faq.{{ $q['key'] }}"
                        placeholder="{{ $q['placeholder'] ?? $genericPlaceholder }}"></textarea>
                </div>
            @endforeach
        @endforeach

    @elseif ($configKey)
        {{-- Seller / Buyer / Landlord: nested category → questions (array shape or legacy string shape) + addons --}}
        @php
            $baseGroups  = config($configKey . '.questions', []);
            $addonGroups = config($configKey . '.addons', []);
        @endphp

        @foreach ($baseGroups as $category => $questions)
            <h6 class="mt-4 mb-2 fw-bold border-bottom pb-1">{{ $category }}</h6>
            @foreach ($questions as $key => $entry)
                @php
                    if (is_array($entry)) {
                        $label       = $entry['label'] ?? '';
                        $placeholder = $entry['placeholder'] ?? $genericPlaceholder;
                    } else {
                        $label       = $entry;
                        $placeholder = $genericPlaceholder;
                    }
                @endphp
                <div class="form-group mb-3">
                    <label class="form-label">{{ $label }}</label>
                    <textarea class="form-control ai-faq-textarea" rows="2"
                        wire:model.defer="listing_ai_faq.{{ $key }}"
                        placeholder="{{ $placeholder }}"></textarea>
                </div>
            @endforeach
        @endforeach

        @foreach ($addonGroups as $addon)
            @if (in_array($propertyType, $addon['visible_for'] ?? []))
                <div class="ai-faq-addon-group mt-4 pt-2 border-top">
                    <h6 class="mb-3 fw-bold text-primary">{{ $addon['label'] }}</h6>
                    @foreach ($addon['questions'] as $key => $entry)
                        @php
                            if (is_array($entry)) {
                                $label       = $entry['label'] ?? '';
                                $placeholder = $entry['placeholder'] ?? $genericPlaceholder;
                            } else {
                                $label       = $entry;
                                $placeholder = $genericPlaceholder;
                            }
                        @endphp
                        <div class="form-group mb-3">
                            <label class="form-label">{{ $label }}</label>
                            <textarea class="form-control ai-faq-textarea" rows="2"
                                wire:model.defer="listing_ai_faq.{{ $key }}"
                                placeholder="{{ $placeholder }}"></textarea>
                        </div>
                    @endforeach
                </div>
            @endif
        @endforeach

    @else
        <p class="text-muted">No AI questions are configured for this listing type.</p>
    @endif
</div>
