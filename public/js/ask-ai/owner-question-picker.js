/**
 * Ask AI owner questions (selection-based Ask AI, 2026-09-25).
 *
 * Rendered ONLY for the authenticated listing owner. Each button is one of the page's
 * existing owner suggestions; selecting it sends THAT QUESTION'S OWN TEXT — never typed
 * text, there is no text box — to the existing deterministic Ask AI endpoint, which answers
 * the owner at owner scope. The answer is shown as plain text in the modal's answer panel.
 */
(function () {
    'use strict';

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function wire(group) {
        if (group.getAttribute('data-ask-ai-owner-ready') === '1') {
            return;
        }
        group.setAttribute('data-ask-ai-owner-ready', '1');

        var root   = group.closest('[data-ask-ai-picker]') || document;
        var panel  = root.querySelector('[data-ask-ai-picker-answer]');
        var panelQ = root.querySelector('[data-ask-ai-picker-answer-question]');
        var panelA = root.querySelector('[data-ask-ai-picker-answer-text]');
        var busy   = false;

        /** One muted line under the answer, as text — never markup. */
        function note(text) {
            var el = document.createElement('div');
            el.className = 'ask-ai-picker-note';
            el.setAttribute('data-ask-ai-owner-note', '');
            el.style.cssText = 'font-size:.72rem;color:#64748b;margin-top:.35rem;';
            el.textContent = text;
            panelA.parentNode.appendChild(el);
        }

        /**
         * The endpoint's answer, its disclosures and its sources — what the retired free-text
         * renderer showed the owner. Sources are data.source_attribution.sources (a structured
         * object), never the old flat-array reading.
         */
        function clearNotes() {
            Array.prototype.forEach.call(panel.querySelectorAll('[data-ask-ai-owner-note]'), function (n) { n.remove(); });
        }

        function render(data) {
            clearNotes();
            if (data.status === 'blocked' && typeof data.refusal_message === 'string' && data.refusal_message.trim() !== '') {
                panelA.textContent = data.refusal_message;
                return;
            }
            panelA.textContent = (typeof data.answer === 'string' && data.answer.trim() !== '')
                ? data.answer
                : 'There is no verified answer for that in your listing’s details yet.';

            var disc = Array.isArray(data.disclosures) ? data.disclosures.join(' ') : (data.disclosures ? String(data.disclosures) : '');
            if (disc.trim() !== '') {
                note(disc);
            }
            var src = data.source_attribution && Array.isArray(data.source_attribution.sources) ? data.source_attribution.sources : [];
            var labels = src.map(function (s) { return (s && (s.label || s.key)) || ''; }).filter(function (l) { return l !== ''; });
            if (labels.length > 0) {
                note('Source: ' + labels.join(', '));
            }
        }

        group.addEventListener('click', function (event) {
            var btn = event.target.closest ? event.target.closest('[data-ask-ai-owner-question]') : null;
            if (!btn || busy || !panel) {
                return;
            }
            event.preventDefault();
            busy = true;
            var question = btn.getAttribute('data-ask-ai-owner-question');
            var questionKey = btn.getAttribute('data-ask-ai-owner-key');
            Array.prototype.forEach.call(root.querySelectorAll('[data-ask-ai-pick],[data-ask-ai-owner-question]'), function (b) {
                b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
            });
            clearNotes();
            panelQ.textContent = question;
            panelA.textContent = 'Looking up your listing’s details…';
            panel.hidden = false;

            fetch('/ask-ai/listing-question', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({
                    listing_type: group.getAttribute('data-listing-type'),
                    listing_id: parseInt(group.getAttribute('data-listing-id'), 10),
                    question_key: questionKey
                })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    render(data || {});
                })
                .catch(function () {
                    panelA.textContent = 'Ask AI is unavailable right now. Please try again.';
                })
                .then(function () { busy = false; });
        });
    }

    function init() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-ask-ai-owner-picker]'), wire);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
