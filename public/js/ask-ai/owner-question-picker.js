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

        group.addEventListener('click', function (event) {
            var btn = event.target.closest ? event.target.closest('[data-ask-ai-owner-question]') : null;
            if (!btn || busy || !panel) {
                return;
            }
            event.preventDefault();
            busy = true;
            var question = btn.getAttribute('data-ask-ai-owner-question');
            Array.prototype.forEach.call(root.querySelectorAll('[data-ask-ai-pick],[data-ask-ai-owner-question]'), function (b) {
                b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
            });
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
                    question: question
                })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    panelA.textContent = (data && typeof data.answer === 'string' && data.answer.trim() !== '')
                        ? data.answer
                        : 'There is no verified answer for that in your listing’s details yet.';
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
