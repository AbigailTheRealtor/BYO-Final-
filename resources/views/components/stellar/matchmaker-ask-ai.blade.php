{{--
  matchmaker-ask-ai — Ask AI widget for questions about the viewer's OWN listing/criteria.
  Section 2 (BidYourOffer Matchmaker Intelligence).

  The Ask AI engine answers about the requester's private offer-listing / criteria
  (it has no public MLS support), and the endpoint is authenticated + owner-scoped.
  So the widget posts the consumer's own (criteria_type, criteria_id) and only
  renders when a criteria context is present (i.e. a logged-in consumer viewing
  their match). Without it, there is nothing the engine can answer.

  Props: $listingKey (string, for unique element ids), $criteriaId (int|string|null),
         $criteriaType (string: 'buyer'|'tenant')
--}}
@props(['listingKey', 'criteriaId' => null, 'criteriaType' => 'buyer'])

@php
    $suffix      = substr(md5($listingKey . '|' . ($criteriaId ?? '')), 0, 8);
    $formId      = 'ask-ai-form-' . $suffix;
    $responseId  = 'ask-ai-resp-' . $suffix;
    $inputId     = 'ask-ai-q-'    . $suffix;
@endphp

<div class="card shadow-sm border-0 mb-4" style="border-left:3px solid #8b5cf6 !important;">
    <div class="card-header bg-white border-0 pt-3 pb-1">
        <h6 class="mb-0 fw-semibold" style="font-size:.9rem;color:#374151;">
            <i class="fas fa-robot me-2" style="color:#8b5cf6;"></i>Ask AI
        </h6>
        <div class="text-muted" style="font-size:.78rem;">Ask anything about your matched listing</div>
    </div>
    <div class="card-body pt-2 pb-3">

        @if(empty($criteriaId))
            <div class="text-muted" style="font-size:.82rem;">
                Ask AI is available when you view this property from your saved buyer or
                tenant criteria.
            </div>
        @else
            <form id="{{ $formId }}" onsubmit="return sbAskAi(event, '{{ $formId }}', '{{ $responseId }}');">
                @csrf
                <input type="hidden" name="listing_type" value="{{ $criteriaType }}">
                <input type="hidden" name="listing_id"   value="{{ (int) $criteriaId }}">
                <div class="mb-2">
                    <textarea id="{{ $inputId }}"
                              name="question"
                              class="form-control"
                              rows="3"
                              placeholder="e.g. Is this a good investment for my goals? How does this compare to my criteria? What are the HOA restrictions?"
                              style="font-size:.875rem;resize:vertical;"
                              required
                              maxlength="500"></textarea>
                </div>
                <button type="submit" class="btn btn-sm w-100" style="background:#8b5cf6;color:#fff;">
                    <i class="fas fa-paper-plane me-1"></i>Ask AI
                </button>
            </form>

            <div id="{{ $responseId }}" class="mt-3" style="display:none;">
                <div class="p-3 rounded" style="background:#f5f3ff;border:1px solid #ddd6fe;font-size:.875rem;line-height:1.7;">
                    <div class="ask-ai-content"></div>
                    <div class="ask-ai-thinking text-muted fst-italic" style="display:none;">
                        <i class="fas fa-circle-notch fa-spin me-1"></i>Thinking&hellip;
                    </div>
                    <div class="ask-ai-error text-danger" style="display:none;font-size:.82rem;"></div>
                </div>
            </div>
        @endif

    </div>
</div>

@once
@push('scripts')
<script>
window.sbAskAi = function(e, formId, responseId) {
    e.preventDefault();
    var form    = document.getElementById(formId);
    var respBox = document.getElementById(responseId);
    var content = respBox.querySelector('.ask-ai-content');
    var thinking = respBox.querySelector('.ask-ai-thinking');
    var errBox  = respBox.querySelector('.ask-ai-error');

    content.textContent  = '';
    errBox.style.display = 'none';
    errBox.textContent   = '';
    thinking.style.display = '';
    respBox.style.display  = '';

    var data = new FormData(form);
    var ok   = true;
    fetch('{{ route('ask-ai.listing-question') }}', {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': data.get('_token') },
        body: data,
    })
    .then(function(r) { ok = r.ok; return r.json().catch(function() { return {}; }); })
    .then(function(json) {
        thinking.style.display = 'none';
        // Only render a genuine answer; never surface validation/error payloads as an answer.
        if (ok && json.answer) {
            content.textContent = json.answer;
        } else {
            errBox.textContent   = json.error || json.refusal_message || 'Ask AI could not answer that right now. Please try again.';
            errBox.style.display = '';
        }
    })
    .catch(function() {
        thinking.style.display = 'none';
        errBox.textContent   = 'Unable to reach Ask AI. Please try again.';
        errBox.style.display = '';
    });
    return false;
};
</script>
@endpush
@endonce
