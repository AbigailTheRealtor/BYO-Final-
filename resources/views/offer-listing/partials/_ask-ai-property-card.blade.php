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

    Batch 2d added the two CRITERIA roles to this same card rather than building a second
    one. A buyer or tenant listing is a search request, not a property, so only the heading
    and the empty-state wording change; the mechanism — precomputed pairs, a <details>
    toggle, no script, no request — is identical, and so is the guarantee that comes with it.

    @param array  $questions      list of {id, question, answer, source_path}
    @param string $role           'seller' | 'landlord' | 'buyer' | 'tenant'
    @param string $prefix         the page's class prefix: 'sol' | 'lol' | 'bol' | 'tcl'
    @param bool   $viewerIsOwner  true only for the authenticated listing owner
    @param string $modalId        the owner Ask AI modal id
    @param string $heading        optional; defaults to the property wording
    @param string $emptyText      optional; defaults to the property wording
    @param string $subject        optional; the noun in the empty state ('property')
--}}
@php
    /* A criteria listing describes what someone is LOOKING FOR. Saying "Questions About
       This Property" over a buyer's search request would name a property that does not
       exist. Resolved from the role so a caller cannot pair the wrong heading with a role. */
    $askAiPqSubject = $subject ?? (in_array($role, ['buyer', 'tenant'], true) ? 'criteria' : 'property');
    $askAiPqHeading = $heading ?? match ($role) {
        'buyer'  => "Questions About This Buyer's Criteria",
        'tenant' => "Questions About This Tenant's Criteria",
        default  => 'Questions About This Property',
    };
    $askAiPqEmpty = $emptyText ?? "No verified {$askAiPqSubject} questions are available yet.";

    /* One neutral placeholder for all four roles: it describes what the box does, and a
       role-specific wording would have to be re-checked every time a role's noun changes. */
    $askAiPqAskPlaceholder = 'Type a question about this listing…';
    $askAiPqAskLabel       = 'Type a question about this listing';
@endphp
@once
@push('scripts')
{{-- A plain static asset: no imports, no build step, and nothing on this page depends on a
     bundle. Pushed to the layout's script stack so the card markup itself stays free of
     <script>, which the card's structural tests continue to assert. --}}
<script src="{{ asset('js/ask-ai/deterministic-question-matcher.js') }}" defer></script>
@endpush
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

/* Typed question row. Full width of the card, wraps on narrow screens, and never
   introduces its own scroll area. */
.ask-ai-pq-ask { margin-top: .55rem; }
.ask-ai-pq-ask-label {
    position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
    overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0;
}
.ask-ai-pq-ask-row { display: flex; flex-wrap: wrap; gap: .35rem; align-items: stretch; }
.ask-ai-pq-ask-input {
    flex: 1 1 12rem; min-width: 0; font-size: .74rem; line-height: 1.3;
    padding: .34rem .55rem; border: 1px solid #cbd5e1; border-radius: .5rem;
    background: #fff; color: #1e293b;
}
.ask-ai-pq-ask-input:focus-visible { outline: 2px solid #2563eb; outline-offset: 1px; }
.ask-ai-pq-ask-button {
    flex: 0 0 auto; font-size: .72rem; font-weight: 700; padding: .34rem .7rem;
    border-radius: .5rem; border: 1px solid #2563eb; background: #eff6ff; color: #1d4ed8;
    cursor: pointer; white-space: nowrap;
}
.ask-ai-pq-ask-button:hover { background: #dbeafe; }
.ask-ai-pq-ask-button:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
.ask-ai-pq-ask-status { font-size: .68rem; color: #475569; line-height: 1.4; margin: .3rem 0 0; }
.ask-ai-pq-ask-status:empty { margin: 0; }
/* Landlord and tenant carry the teal accent their cards already use. */
.lol-view-page .ask-ai-pq-ask-button,
.tcl-view-page .ask-ai-pq-ask-button { border-color: #0f766e; background: #f0fdfa; color: #0f766e; }
.lol-view-page .ask-ai-pq-ask-button:hover,
.tcl-view-page .ask-ai-pq-ask-button:hover { background: #ccfbf1; }
.lol-view-page .ask-ai-pq-ask-input:focus-visible,
.tcl-view-page .ask-ai-pq-ask-input:focus-visible { outline-color: #0f766e; }
/* Landlord and tenant pages use the teal palette their old Ask AI chips used. Buyer keeps
   the blue default above, which is the buyer page's own accent. */
.lol-view-page .ask-ai-pq-item,
.tcl-view-page .ask-ai-pq-item { border-color: #99f6e4; background: #f0fdfa; }
.lol-view-page .ask-ai-pq-item[open],
.tcl-view-page .ask-ai-pq-item[open] { background: #fff; }
.lol-view-page .ask-ai-pq-question,
.tcl-view-page .ask-ai-pq-question { color: #0f766e; }
.lol-view-page .ask-ai-pq-question::after,
.tcl-view-page .ask-ai-pq-question::after { color: #0f766e; }

/* The criteria pages render this card on its own in the left column rather than inside a
   quick-actions grid, so it has no cell to stretch and needs no grid-column override. It
   still must not grow a horizontal scrollbar at phone width. */
.bol-view-page #bol-ask-ai-card,
.tcl-view-page #tcl-ask-ai-card { margin-top: 1.25rem; }
.ask-ai-pq-list > * { min-width: 0; }
.ask-ai-pq-question, .ask-ai-pq-answer { overflow-wrap: anywhere; }
</style>
@endpush
@endonce
<div class="{{ $prefix }}-interaction-card" id="{{ $prefix }}-ask-ai-card" data-ask-ai-property-questions="{{ $role }}">
    <div class="{{ $prefix }}-interaction-card-icon"><i class="fa-solid fa-robot"></i></div>
    <div class="{{ $prefix }}-interaction-card-label">Ask AI</div>
    @if(!empty($questions))
        <div class="ask-ai-pq-heading">{{ $askAiPqHeading }}</div>
        <div class="ask-ai-pq-list">
            @foreach($questions as $q)
            <details class="ask-ai-pq-item" data-property-question="{{ $q['id'] }}"
                     data-question-aliases="{{ implode('|', $q['aliases'] ?? []) }}">
                <summary class="ask-ai-pq-question">{{ $q['question'] }}</summary>
                <p class="ask-ai-pq-answer" data-property-answer="{{ $q['id'] }}">{{ $q['answer'] }}</p>
            </details>
            @endforeach
        </div>
        {{-- Typed question (Batch 3). Matching happens in the browser against the questions
             rendered above and nothing else.

             THERE IS NO <form> AND THE INPUT HAS NO name. That is not tidiness: without a
             form there is nothing for Enter to submit, and without a name there is nothing
             a form could carry. The typed text cannot reach Laravel even by accident, which
             is the property the structural tests assert. --}}
        <div class="ask-ai-pq-ask">
            <label class="ask-ai-pq-ask-label" for="{{ $prefix }}-ask-ai-ask-input">{{ $askAiPqAskLabel }}</label>
            <div class="ask-ai-pq-ask-row">
                <input type="text"
                       id="{{ $prefix }}-ask-ai-ask-input"
                       class="ask-ai-pq-ask-input"
                       data-ask-ai-ask-input
                       autocomplete="off"
                       enterkeyhint="search"
                       maxlength="200"
                       placeholder="{{ $askAiPqAskPlaceholder }}">
                <button type="button" class="ask-ai-pq-ask-button" data-ask-ai-ask-button>Find answer</button>
            </div>
            <p class="ask-ai-pq-ask-status" role="status" aria-live="polite" data-ask-ai-ask-status></p>
        </div>
        <div class="ask-ai-pq-note">Answers come directly from this listing's details.</div>
    @else
        <div class="{{ $prefix }}-interaction-card-helper">{{ $askAiPqEmpty }}</div>
    @endif
    @if($viewerIsOwner)
        <button type="button" class="{{ $prefix }}-interaction-cta {{ $prefix }}-interaction-cta-outline"
                data-bs-toggle="modal" data-bs-target="#{{ $modalId }}"
                aria-label="Ask AI a question about your listing">
            <i class="fa-solid fa-robot"></i>Ask AI
        </button>
    @endif
</div>
