{{--
    Questions About This Property — verified property FAQs (Batch 1, Seller + Landlord).

    Every question and answer is precomputed server-side by
    App\Services\AskAi\AskAiPublicPropertyQuestionService from listing data the page
    already assembled. Opening a question only toggles a native <details> element: no
    request, no classifier, no generated text. Only questions whose answer exists are
    passed in, so an unavailable question is never rendered at all.

    Deliberately separate from the Ask AI modal: this does not prefill its textbox and
    does not touch the free-form Ask AI flow.

    @param array  $questions  list of {id, question, answer, source_path}
    @param string $role       'seller' | 'landlord'
--}}
@if(!empty($questions))
<div class="card section-card" id="section-property-questions" data-property-questions="{{ $role }}">
    <div class="card-header"><i class="fa-solid fa-circle-question me-2"></i>Questions About This Property</div>
    <div class="card-body">
        <p class="text-muted mb-3" style="font-size:.85rem;">Answers come directly from this listing's details.</p>
        @foreach($questions as $q)
        <details class="mb-2" data-property-question="{{ $q['id'] }}" style="border:1px solid #e2e8f0;border-radius:.5rem;padding:.6rem .85rem;">
            <summary class="fw-semibold" style="cursor:pointer;font-size:.9rem;">{{ $q['question'] }}</summary>
            <p class="mb-0 mt-2" data-property-answer="{{ $q['id'] }}" style="font-size:.9rem;">{{ $q['answer'] }}</p>
        </details>
        @endforeach
    </div>
</div>
@endif
