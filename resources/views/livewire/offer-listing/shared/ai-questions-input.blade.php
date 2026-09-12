{{--
    Listing AI Knowledge Base tab content.
    Consumes Livewire component props: $user_type, $property_type, $listing_ai_faq (array).
    No variables need to be passed from the parent @include.

    Two-axis architecture (docs/ask-ai-kb-replacement-spec.md Part A):
    each knowledge base = the 'universal' group + the one group matching the listing's
    property type, resolved via the config 'gating' map. Questions never leak across
    property types. Within the gated groups, questions are organised into two sections
    by their 'category_type' tag (Part D): "Common Questions" and "AI Insights".
--}}
@php
    $propertyType = $property_type ?? '';

    // WHICH questions render is NOT decided here. AskAiFaqConfigService::gatedQuestions()
    // owns the role + property-type gating (the 'universal' group plus the one group the
    // config's 'gating' map names, with the short/long vocabulary alias applied and the
    // universal-only fail-safe for an absent or unrecognised type). It moved out of this
    // template when MLS Quick Import needed the same answer in PHP to decide which keys a
    // client may write: the form and the persist must agree about what belongs to a
    // Seller Commercial listing, and two copies of that rule is how they stop agreeing.
    //
    // What stays here is presentation: splitting the gated set into the two rendered
    // sections by 'category_type' (Part D — Common Questions vs AI Insights).
    //
    // Answers already entered persist in $listing_ai_faq across property-type changes
    // (no consumer prunes it), so switching type and back is safe.
    $configKey = \App\Services\AskAi\AskAiFaqConfigService::CONFIG_MAP[$user_type] ?? null;
    $genericPlaceholder = 'Enter your answer here (e.g., provide as much detail as you\'d like — all fields are optional)';

    $commonQuestions  = [];
    $insightQuestions = [];

    if ($configKey) {
        $gated = \App\Services\AskAi\AskAiFaqConfigService::gatedQuestions($user_type, $propertyType);

        foreach ($gated as $category => $questions) {
            foreach ($questions as $key => $entry) {
                $row = [
                    'key'         => $key,
                    'label'       => $entry['label'] ?? '',
                    'placeholder' => $entry['placeholder'] ?? $genericPlaceholder,
                    'tooltip'     => $entry['tooltip'] ?? '',
                    'category'    => $category,
                ];
                if (($entry['category_type'] ?? 'common') === 'insight') {
                    $insightQuestions[$category][$key] = $row;
                } else {
                    $commonQuestions[$category][$key] = $row;
                }
            }
        }
    }
@endphp

<div class="ai-questions-tab-content py-2">
    <p class="text-muted small mb-2">
        This is your <strong>Listing AI Knowledge Base</strong> — answers you provide here are used to pre-load the listing chatbot so it can answer questions from buyers, tenants, or agents accurately and automatically. Fill in what you know; all fields are optional.
    </p>

    <div class="alert alert-light border small text-muted mb-4" role="note">
        Ask AI provides educational and informational summaries based on listing data and platform content only. It is not legal, financial, tax, lending, appraisal, inspection, or professional advice. Always verify with a licensed professional before making any real estate decision.
    </div>

    @if (! $configKey)
        <p class="text-muted">No AI knowledge base questions are configured for this listing type.</p>
    @else
        @if (empty($commonQuestions) && empty($insightQuestions))
            <p class="text-muted">No AI knowledge base questions are configured for this property type.</p>
        @endif

        @if (! empty($commonQuestions))
            <h5 class="mt-3 mb-1 fw-bold">Common Questions</h5>
            <p class="text-muted small mb-3">Real questions buyers, tenants, and agents commonly ask about this listing.</p>
            @foreach ($commonQuestions as $category => $questions)
                <h6 class="mt-4 mb-2 fw-bold border-bottom pb-1">{{ $category }}</h6>
                @foreach ($questions as $q)
                    @include('livewire.offer-listing.shared.partials.ai-question-field', ['q' => $q, 'genericPlaceholder' => $genericPlaceholder])
                @endforeach
            @endforeach
        @endif

        @if (! empty($insightQuestions))
            <h5 class="mt-5 mb-1 fw-bold">AI Insights</h5>
            <p class="text-muted small mb-3">Educational prompts that help the AI explain the listing using platform data such as Property DNA, Location DNA, and Match information. Insights explain and educate only — never advice.</p>
            @foreach ($insightQuestions as $category => $questions)
                <h6 class="mt-4 mb-2 fw-bold border-bottom pb-1">{{ $category }}</h6>
                @foreach ($questions as $q)
                    @include('livewire.offer-listing.shared.partials.ai-question-field', ['q' => $q, 'genericPlaceholder' => $genericPlaceholder])
                @endforeach
            @endforeach
        @endif
    @endif
</div>
