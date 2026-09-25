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

    SELECTION-BASED (2026-09-25). Ask AI answers only verified questions derived from listing
    data, so the viewer SELECTS a question rather than typing one. This card shows the small
    FEATURED subset (AskAiQuestionPresentation, config/ask_ai_question_presentation.php) and
    one button that opens the page's Ask AI modal, where every answerable question is listed,
    searchable and selectable (_ask-ai-question-modal). The card's former typed-question box is
    gone: search lives in the modal, and it filters questions — it never answers typed text.
    Featured is a presentation subset only; a question left out of it is still in the modal.

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
    @param array  $meta           the listing's decoded meta (property type for the featured set)
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

    /* Featured subset and full count, from the service's own answerable set. */
    $askAiPqSet      = \App\Support\AskAi\AskAiQuestionPresentation::build($role, $questions ?? [], $meta ?? []);
    $askAiPqFeatured = $askAiPqSet['featured'];
    $askAiPqTotal    = count($askAiPqSet['all']);
@endphp
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

/* The one control: opens the modal with every answerable question. */
.ask-ai-pq-open { margin-top: .55rem; }
.ask-ai-pq-count { font-weight: 500; opacity: .85; }
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
<div class="{{ $prefix }}-interaction-card" id="{{ $prefix }}-ask-ai-card" data-ask-ai-property-questions="{{ $role }}"
     data-ask-ai-question-total="{{ $askAiPqTotal }}" data-ask-ai-featured-count="{{ count($askAiPqFeatured) }}">
    <div class="{{ $prefix }}-interaction-card-icon"><i class="fa-solid fa-robot"></i></div>
    <div class="{{ $prefix }}-interaction-card-label">Ask AI</div>
    @if(!empty($askAiPqFeatured))
        <div class="ask-ai-pq-heading">{{ $askAiPqHeading }}</div>
        <div class="ask-ai-pq-list">
            @foreach($askAiPqFeatured as $q)
            <details class="ask-ai-pq-item" data-property-question="{{ $q['id'] }}"
                     data-question-aliases="{{ implode('|', $q['aliases'] ?? []) }}">
                <summary class="ask-ai-pq-question">{{ $q['display'] }}</summary>
                <p class="ask-ai-pq-answer" data-property-answer="{{ $q['id'] }}">{{ $q['answer'] }}</p>
            </details>
            @endforeach
        </div>
        <button type="button" class="{{ $prefix }}-interaction-cta {{ $prefix }}-interaction-cta-outline ask-ai-pq-open"
                data-bs-toggle="modal" data-bs-target="#{{ $modalId }}" data-ask-ai-open-all
                aria-label="Ask AI: view all {{ $askAiPqTotal }} questions about this listing">
            <i class="fa-solid fa-robot"></i>@if($askAiPqTotal > count($askAiPqFeatured))View all questions <span class="ask-ai-pq-count">({{ $askAiPqTotal }})</span>@else Ask AI @endif
        </button>
        <div class="ask-ai-pq-note">Answers come directly from this listing's details.</div>
    @else
        <div class="{{ $prefix }}-interaction-card-helper">{{ $askAiPqEmpty }}</div>
        @if($viewerIsOwner)
        <button type="button" class="{{ $prefix }}-interaction-cta {{ $prefix }}-interaction-cta-outline"
                data-bs-toggle="modal" data-bs-target="#{{ $modalId }}"
                aria-label="Ask AI a question about your listing">
            <i class="fa-solid fa-robot"></i>Ask AI
        </button>
        @endif
    @endif
</div>
