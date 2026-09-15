{{--
    Ask AI — Questions About This Property (Seller + Landlord quick-actions card).

    The one shopper-facing Ask AI surface on these pages. Every question and answer is
    precomputed server-side by App\Services\AskAi\AskAiPublicPropertyQuestionService from
    listing data the controller already assembled, and passed in as $questions. Only
    questions whose answer exists are passed, so an unavailable question never renders.

    Revealing an answer is a native <details> toggle: the answer text is already in this
    markup, so opening it sends no request, runs no script, and reaches no classifier,
    knowledge search or language model. This partial therefore contains no <script>, no
    form, no input and no link — keep it that way; the card test scans for all of them.

    The free-text Ask AI modal is owner-only (the endpoint behind it is owner-scoped), so
    its trigger renders for the listing owner alone. A shopper gets the questions, or a
    plain empty state, and never a text box that can only answer "owner only".

    @param array  $questions      list of {id, question, answer, source_path}
    @param string $role           'seller' | 'landlord'
    @param string $prefix         the page's class prefix: 'sol' | 'lol'
    @param bool   $viewerIsOwner  true only for the authenticated listing owner
    @param string $modalId        the owner Ask AI modal id: 'solAiModal' | 'lolAiModal'
--}}
@once
@push('styles')
<style>
.ask-ai-pq-heading { font-size: .72rem; font-weight: 600; color: #475569; margin-top: -.15rem; }
/* No inner scroll box: every available question stays visible. A capped, scrolling list hid
   most of a seller's ten questions behind a scrollbar that macOS and touch devices do not show. */
.ask-ai-pq-list { display: grid; grid-template-columns: 1fr; gap: .3rem .5rem; align-items: start; }

/* Full-width row. In a grid cell this card's height (up to 17 questions) stretched every
   Quick Action card in its row and pushed their buttons far from their headings. From 480px
   it spans the whole grid; from 768px, where the grid has 3+ columns, it also sits after the
   other quick actions so it never leaves empty cells beside them. Below 480px the grid is a
   single column already and nothing changes. */
@media (min-width: 480px) {
    .sol-view-page #sol-ask-ai-card,
    .lol-view-page #lol-ask-ai-card { grid-column: 1 / -1; }
}
@media (min-width: 768px) {
    .sol-view-page #sol-ask-ai-card,
    .lol-view-page #lol-ask-ai-card { order: 99; }
    .ask-ai-pq-list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (min-width: 1200px) {
    .ask-ai-pq-list { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
.ask-ai-pq-item { border: 1px solid #bfdbfe; border-radius: .5rem; background: #eff6ff; }
.ask-ai-pq-item[open] { background: #fff; }
.ask-ai-pq-question {
    list-style: none; cursor: pointer; font-size: .72rem; font-weight: 600; color: #1d4ed8;
    padding: .32rem .55rem; line-height: 1.35; display: flex; justify-content: space-between; gap: .4rem;
}
.ask-ai-pq-question::-webkit-details-marker { display: none; }
.ask-ai-pq-question::after { content: '+'; font-weight: 700; color: #3b82f6; flex-shrink: 0; }
.ask-ai-pq-item[open] > .ask-ai-pq-question::after { content: '\2212'; }
.ask-ai-pq-question:focus-visible { outline: 2px solid #2563eb; outline-offset: 1px; border-radius: .5rem; }
.ask-ai-pq-answer { font-size: .72rem; color: #1e293b; line-height: 1.45; margin: 0; padding: 0 .55rem .45rem; }
.ask-ai-pq-note { font-size: .66rem; color: #64748b; line-height: 1.4; }
/* Landlord pages use the teal palette their old Ask AI chips used. */
.lol-view-page .ask-ai-pq-item { border-color: #99f6e4; background: #f0fdfa; }
.lol-view-page .ask-ai-pq-item[open] { background: #fff; }
.lol-view-page .ask-ai-pq-question { color: #0f766e; }
.lol-view-page .ask-ai-pq-question::after { color: #0f766e; }
</style>
@endpush
@endonce
<div class="{{ $prefix }}-interaction-card" id="{{ $prefix }}-ask-ai-card" data-ask-ai-property-questions="{{ $role }}">
    <div class="{{ $prefix }}-interaction-card-icon"><i class="fa-solid fa-robot"></i></div>
    <div class="{{ $prefix }}-interaction-card-label">Ask AI</div>
    @if(!empty($questions))
        <div class="ask-ai-pq-heading">Questions About This Property</div>
        <div class="ask-ai-pq-list">
            @foreach($questions as $q)
            <details class="ask-ai-pq-item" data-property-question="{{ $q['id'] }}">
                <summary class="ask-ai-pq-question">{{ $q['question'] }}</summary>
                <p class="ask-ai-pq-answer" data-property-answer="{{ $q['id'] }}">{{ $q['answer'] }}</p>
            </details>
            @endforeach
        </div>
        <div class="ask-ai-pq-note">Answers come directly from this listing's details.</div>
    @else
        <div class="{{ $prefix }}-interaction-card-helper">No verified property questions are available yet.</div>
    @endif
    @if($viewerIsOwner)
        <button type="button" class="{{ $prefix }}-interaction-cta {{ $prefix }}-interaction-cta-outline"
                data-bs-toggle="modal" data-bs-target="#{{ $modalId }}"
                aria-label="Ask AI a question about your listing">
            <i class="fa-solid fa-robot"></i>Ask AI
        </button>
    @endif
</div>
