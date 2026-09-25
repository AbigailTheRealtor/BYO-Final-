{{--
    Ask AI modal — SELECTION-BASED (2026-09-25). Shared by the Seller, Landlord, Buyer and
    Tenant Offer Listing pages; each passes its own prefix, title and modal id so the design
    each page already had is unchanged.

    Ask AI answers only verified questions derived from listing data, so the viewer SELECTS a
    question: recommended questions at the top, "View all questions" for the complete set in a
    capped, scrolling list, and "Search questions..." to filter that set. Selecting a question
    shows its precomputed answer, already in this markup. There is no free-text submission:
    the search box carries no `name`, sits in no <form>, and only filters — typed text is never
    answered and never leaves the browser (question-picker.js performs no request at all).

    WHAT IS LISTED is exactly the set AskAiPublicPropertyQuestionService already built for this
    viewer ($questions): visibility, property type, ConditionalTerms and Fair Housing are
    decided there. AskAiQuestionPresentation only orders, subsets and rewords it, so a question
    withheld from the page can appear in neither the recommended row, the full list nor search.

    OWNER QUESTIONS. The listing owner additionally gets this page's owner suggestions that
    name a canonical fact, as selectable questions answered at owner scope by the existing
    deterministic endpoint. Only the selected question's registry KEY is sent, resolved on the
    server by exact match (AskAiOwnerQuestionSelection); there is still no text box.

    @param string $role            'seller' | 'landlord' | 'buyer' | 'tenant'
    @param string $prefix          'sol' | 'lol' | 'bol' | 'tcl'
    @param string $modalId         the modal element id
    @param string $title           the modal title
    @param array  $questions       the service's answerable rows for this viewer
    @param array  $meta            decoded listing meta (property type for the featured set)
    @param bool   $viewerIsOwner   true only for the authenticated listing owner
    @param int    $listingId       the listing id (owner questions only)
    @param array  $chipContext     Ask AI chip context (owner suggestions only)
    @param string $disclaimer      optional disclaimer line
--}}
@php
    $askAiMSet      = \App\Support\AskAi\AskAiQuestionPresentation::build($role, $questions ?? [], $meta ?? []);
    $askAiMFeatured = $askAiMSet['featured'];
    $askAiMAll      = $askAiMSet['all'];
    // Only suggestions that name a canonical fact, each with its registry key — see
    // AskAiOwnerQuestionSelection. The picker sends the key; the server resolves it exactly.
    $askAiMOwner    = ($viewerIsOwner ?? false)
        ? \App\Support\AskAi\AskAiOwnerQuestionSelection::forOwner($role, $chipContext ?? [])
        : [];
    $askAiMSubject  = in_array($role, ['buyer', 'tenant'], true) ? 'listing' : 'property';
@endphp
@once
@push('scripts')
<script src="{{ asset('js/ask-ai/question-picker.js') }}" defer></script>
@endpush
@push('styles')
<style>
.ask-ai-picker-intro { font-size: .85rem; color: #64748b; margin-bottom: .85rem; }
.ask-ai-picker-label { font-size: .7rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .04em; margin: .2rem 0 .4rem; }
.ask-ai-picker-recommended, .ask-ai-picker-owner { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .85rem; }
.ask-ai-picker-q {
    font-size: .78rem; line-height: 1.35; text-align: left; padding: .38rem .65rem; border-radius: .5rem;
    border: 1px solid #bfdbfe; background: #eff6ff; color: #1d4ed8; cursor: pointer; max-width: 100%;
    overflow-wrap: anywhere;
}
.ask-ai-picker-q:hover { background: #dbeafe; }
.ask-ai-picker-q:focus-visible { outline: 2px solid #2563eb; outline-offset: 1px; }
.ask-ai-picker-q[aria-pressed="true"] { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
.ask-ai-picker-answer { border: 1px solid #e2e8f0; background: #f8fafc; border-radius: .6rem; padding: .7rem .85rem; margin-bottom: .9rem; }
.ask-ai-picker-answer-q { font-size: .78rem; font-weight: 700; color: #0f172a; margin-bottom: .25rem; }
.ask-ai-picker-answer-text { font-size: .85rem; color: #1e293b; line-height: 1.5; margin: 0; white-space: pre-line; }
.ask-ai-picker-search-row { display: flex; gap: .4rem; align-items: center; margin-bottom: .5rem; flex-wrap: wrap; }
.ask-ai-picker-search { flex: 1 1 12rem; min-width: 0; font-size: .82rem; padding: .42rem .65rem; border: 1px solid #cbd5e1; border-radius: .5rem; }
.ask-ai-picker-search:focus-visible { outline: 2px solid #2563eb; outline-offset: 1px; }
.ask-ai-picker-toggle { font-size: .78rem; font-weight: 700; padding: .42rem .7rem; border-radius: .5rem; border: 1px solid #cbd5e1; background: #fff; color: #334155; cursor: pointer; white-space: nowrap; }
.ask-ai-picker-toggle:hover { background: #f1f5f9; }
/* The complete list is capped and scrolls inside the modal, so the modal never grows
   without bound however many questions a listing can answer. */
.ask-ai-picker-all { max-height: 18rem; overflow-y: auto; overscroll-behavior: contain; border: 1px solid #e2e8f0; border-radius: .6rem; padding: .5rem; display: flex; flex-direction: column; gap: .35rem; margin-bottom: .85rem; }
.ask-ai-picker-all[hidden], .ask-ai-picker-answer[hidden], .ask-ai-picker-empty[hidden] { display: none; }
.ask-ai-picker-all .ask-ai-picker-q { width: 100%; }
.ask-ai-picker-empty { font-size: .78rem; color: #64748b; margin: .2rem 0 .7rem; }
.ask-ai-picker-sr { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }
.ask-ai-picker-disclaimer { font-size: .73rem; color: #64748b; border-top: 1px solid #f1f5f9; padding-top: .6rem; line-height: 1.45; margin: .4rem 0 0; }
/* Landlord and tenant keep their teal accent. */
.lol-view-page .ask-ai-picker-q, .tcl-view-page .ask-ai-picker-q { border-color: #99f6e4; background: #f0fdfa; color: #0f766e; }
.lol-view-page .ask-ai-picker-q:hover, .tcl-view-page .ask-ai-picker-q:hover { background: #ccfbf1; }
.lol-view-page .ask-ai-picker-q[aria-pressed="true"], .tcl-view-page .ask-ai-picker-q[aria-pressed="true"] { background: #0f766e; border-color: #0f766e; color: #fff; }
</style>
@endpush
@endonce
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content" style="border-radius:.85rem;overflow:hidden;border:none;">
            <div class="modal-header {{ $prefix }}-modal-header">
                <h5 class="modal-title fw-bold" id="{{ $modalId }}Label"><i class="fa-solid fa-robot me-2"></i>{{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter:invert(1);"></button>
            </div>
            <div class="modal-body p-4" data-ask-ai-picker="{{ $role }}"
                 data-ask-ai-question-total="{{ count($askAiMAll) }}" data-ask-ai-featured-count="{{ count($askAiMFeatured) }}">
                @if(empty($askAiMAll) && empty($askAiMOwner))
                    <p class="ask-ai-picker-intro">No verified questions are available for this {{ $askAiMSubject }} yet.</p>
                @else
                    <p class="ask-ai-picker-intro">Choose a question. Every answer comes directly from this {{ $askAiMSubject }}'s verified details.</p>

                    <div class="ask-ai-picker-answer" data-ask-ai-picker-answer aria-live="polite" hidden>
                        <div class="ask-ai-picker-answer-q" data-ask-ai-picker-answer-question></div>
                        <p class="ask-ai-picker-answer-text" data-ask-ai-picker-answer-text></p>
                    </div>

                    @if(!empty($askAiMFeatured))
                    <div class="ask-ai-picker-label" id="{{ $prefix }}AiRecommendedLabel">Recommended questions</div>
                    <div class="ask-ai-picker-recommended" role="group" aria-labelledby="{{ $prefix }}AiRecommendedLabel">
                        @foreach($askAiMFeatured as $q)
                        <button type="button" class="ask-ai-picker-q" data-ask-ai-pick="{{ $q['id'] }}" aria-pressed="false">{{ $q['display'] }}</button>
                        @endforeach
                    </div>
                    @endif

                    @if(!empty($askAiMAll))
                    <div class="ask-ai-picker-search-row">
                        <label class="ask-ai-picker-sr" for="{{ $prefix }}AiQuestionSearch">Search questions</label>
                        <input type="search" id="{{ $prefix }}AiQuestionSearch" class="ask-ai-picker-search"
                               data-ask-ai-picker-search autocomplete="off" maxlength="100"
                               placeholder="Search questions..." aria-controls="{{ $prefix }}AiAllQuestions">
                        <button type="button" class="ask-ai-picker-toggle" data-ask-ai-picker-toggle
                                aria-expanded="false" aria-controls="{{ $prefix }}AiAllQuestions"
                                data-label-show="View all questions ({{ count($askAiMAll) }})" data-label-hide="Hide all questions">View all questions ({{ count($askAiMAll) }})</button>
                    </div>
                    <p class="ask-ai-picker-empty" data-ask-ai-picker-empty role="status" hidden>No matching questions. Try another word, or view all questions.</p>
                    <div class="ask-ai-picker-all" id="{{ $prefix }}AiAllQuestions" data-ask-ai-picker-all role="group" aria-label="All questions" hidden>
                        @foreach($askAiMAll as $q)
                        <button type="button" class="ask-ai-picker-q" data-ask-ai-pick="{{ $q['id'] }}" aria-pressed="false"
                                data-ask-ai-search-terms="{{ implode('|', $q['search_terms']) }}">{{ $q['display'] }}</button>
                        @endforeach
                    </div>
                    <div hidden data-ask-ai-picker-answers>
                        @foreach($askAiMAll as $q)
                        <p data-ask-ai-answer-for="{{ $q['id'] }}" data-ask-ai-question-display="{{ $q['display'] }}">{{ $q['answer'] }}</p>
                        @endforeach
                    </div>
                    @endif

                    @if(!empty($askAiMOwner))
                    @once
                    @push('scripts')
                    <script src="{{ asset('js/ask-ai/owner-question-picker.js') }}" defer></script>
                    @endpush
                    @endonce
                    <div class="ask-ai-picker-label" id="{{ $prefix }}AiOwnerLabel">Your listing — owner questions</div>
                    <div class="ask-ai-picker-owner" role="group" aria-labelledby="{{ $prefix }}AiOwnerLabel"
                         data-ask-ai-owner-picker data-listing-type="{{ $role }}" data-listing-id="{{ (int) ($listingId ?? 0) }}">
                        @foreach($askAiMOwner as $q)
                        <button type="button" class="ask-ai-picker-q" data-ask-ai-owner-question="{{ $q['question'] }}" data-ask-ai-owner-key="{{ $q['key'] }}" aria-pressed="false">{{ $q['question'] }}</button>
                        @endforeach
                    </div>
                    @endif
                @endif
                <p class="ask-ai-picker-disclaimer">
                    <i class="fa-solid fa-shield-halved me-1 text-secondary" aria-hidden="true"></i>{{ $disclaimer ?? 'Ask AI provides informational summaries based on listing data and platform content only. It is not a licensed real estate broker, attorney, lender, tax advisor, or financial advisor. Nothing here constitutes professional advice. Always consult a qualified professional before making real estate decisions.' }}
                </p>
            </div>
            <div class="modal-footer border-0 pb-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
