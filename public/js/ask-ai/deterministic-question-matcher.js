/**
 * Deterministic typed-question matcher for the Ask AI card (Batch 3).
 *
 * WHAT THIS IS. A shopper types a question; this file matches it against the questions the
 * server ALREADY rendered into this card, and opens the matching <details>. The answer is
 * already in the markup, so "matching" means revealing text that is on the page — nothing
 * is fetched, generated, ranked or guessed.
 *
 * WHAT THIS DELIBERATELY IS NOT. This file performs no request of any kind, opens no socket,
 * navigates nowhere and names no endpoint. Typed text never leaves the browser: nothing here
 * logs it, persists it to browser storage, places it in a URL, or attaches it to an element
 * that could be submitted. The input carries no `name` and sits inside no <form>, so there is
 * nothing for a stray Enter key to submit even in principle.
 *
 * A test SCANS THIS SOURCE for the API names that would break those promises, which is why
 * none of them is spelled out above: a mention in a comment would defeat the scan. (The same
 * mistake was made once already, in the flood-zone service, and caught by its own guard.)
 *
 * THE PAGE IS THE SECURITY BOUNDARY. The vocabulary is read from the rendered questions
 * themselves — each <details> carries its own aliases — so a question this listing cannot
 * answer contributes no aliases, because the element that would have carried them does not
 * exist. There is no global catalog here to fall back to and nothing to keep in sync.
 *
 * MATCHING IS EXACT, AND THAT IS A DESIGN DECISION, NOT A LIMITATION. Two stages: the
 * displayed question, then the aliases. Substring matching was considered and rejected —
 * "how big is the lot" contains the acreage alias AND the square-footage alias "how big",
 * so any substring rule has to RANK the two, and ranking is the thing a deterministic
 * surface must not do. When nothing matches exactly we say so and show the list.
 */
(function () {
    'use strict';

    var SELECTOR = '[data-ask-ai-property-questions]';

    /**
     * The browser half of the normalisation contract.
     *
     * Mirrors AskAiPublicPropertyQuestionService::normalizeQuery() step for step; a PHP test
     * drives the same inputs through that method and asserts the same outputs, because two
     * normalisers that disagree produce a question that can be read but never matched.
     */
    function normalize(text) {
        if (typeof text !== 'string') {
            return '';
        }

        return text
            // Curly quotes and the dashes people actually type.
            .replace(/[‘’ʼ]/g, "'")
            .replace(/[“”]/g, '"')
            .replace(/[‐‑‒–—―]/g, '-')
            .toLowerCase()
            // A hyphen is a space: "move-in date" and "move in date" are one question.
            .replace(/[-_/]/g, ' ')
            // Sentence punctuation only. Apostrophes stay, so "the buyer's budget" keeps
            // its shape and the displayed question stays matchable exactly as written.
            .replace(/[?!.,:;"]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    /** Every question rendered in THIS card, with the vocabulary it carries itself. */
    function readQuestions(card) {
        var items = card.querySelectorAll('details[data-property-question]');
        var out = [];

        Array.prototype.forEach.call(items, function (details) {
            var summary = details.querySelector('summary');
            var aliasAttr = details.getAttribute('data-question-aliases') || '';
            var aliases = aliasAttr.split('|').map(normalize).filter(Boolean);

            out.push({
                el: details,
                summary: summary,
                display: normalize(summary ? summary.textContent : ''),
                aliases: aliases
            });
        });

        return out;
    }

    /**
     * @returns {{status: 'match'|'none'|'ambiguous', question: object|null}}
     *
     * Ambiguity is reported, never resolved. Two questions answering to the same words is a
     * fact about the catalog, and picking one would be a guess dressed as an answer.
     */
    function match(questions, query) {
        if (!query) {
            return { status: 'none', question: null };
        }

        var byDisplay = questions.filter(function (q) { return q.display === query; });
        if (byDisplay.length === 1) {
            return { status: 'match', question: byDisplay[0] };
        }
        if (byDisplay.length > 1) {
            return { status: 'ambiguous', question: null };
        }

        var byAlias = questions.filter(function (q) { return q.aliases.indexOf(query) !== -1; });
        if (byAlias.length === 1) {
            return { status: 'match', question: byAlias[0] };
        }
        if (byAlias.length > 1) {
            return { status: 'ambiguous', question: null };
        }

        return { status: 'none', question: null };
    }

    function wire(card) {
        // Latched: a card is wired once, however many times this file is evaluated.
        if (card.getAttribute('data-ask-ai-matcher-ready') === '1') {
            return;
        }
        var input = card.querySelector('[data-ask-ai-ask-input]');
        var button = card.querySelector('[data-ask-ai-ask-button]');
        var status = card.querySelector('[data-ask-ai-ask-status]');
        if (!input || !button || !status) {
            return;
        }
        card.setAttribute('data-ask-ai-matcher-ready', '1');

        function say(message) {
            status.textContent = message;
        }

        function submit() {
            var questions = readQuestions(card);
            var result = match(questions, normalize(input.value));

            if (result.status === 'match') {
                // Collapse the others so the answer the shopper asked for is the one in view.
                questions.forEach(function (q) {
                    if (q.el !== result.question.el) {
                        q.el.removeAttribute('open');
                    }
                });
                result.question.el.setAttribute('open', 'open');

                if (result.question.summary) {
                    // Focusable for a keyboard user, and scrolled into view for everyone.
                    // Native <details>/<summary> behaviour is untouched.
                    result.question.summary.setAttribute('tabindex', '-1');
                    try {
                        result.question.summary.focus({ preventScroll: true });
                    } catch (e) {
                        result.question.summary.focus();
                    }
                    if (typeof result.question.summary.scrollIntoView === 'function') {
                        result.question.summary.scrollIntoView({ block: 'nearest' });
                    }
                }

                // A successful match clears whatever the previous attempt reported.
                say('Showing the answer to: ' + (result.question.summary ? result.question.summary.textContent.trim() : ''));
                return;
            }

            if (result.status === 'ambiguous') {
                say('I found more than one possible question. Please choose one below.');
                return;
            }

            say("I don't have a verified answer for that yet. Choose one of the available questions below.");
        }

        button.addEventListener('click', function (event) {
            event.preventDefault();
            submit();
        });

        // Explicit submission only — never on keystroke, so nothing happens while typing.
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                submit();
            }
        });
    }

    function init() {
        Array.prototype.forEach.call(document.querySelectorAll(SELECTOR), wire);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
