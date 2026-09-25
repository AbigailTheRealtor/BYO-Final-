/**
 * Ask AI question picker (selection-based Ask AI, 2026-09-25).
 *
 * WHAT THIS IS. The Ask AI modal lists the verified questions the server ALREADY rendered
 * for this listing and viewer, with their precomputed answers. This file lets a viewer find
 * one and read its answer:
 *
 *   - "View all questions" reveals the complete list (capped and scrolling in CSS);
 *   - "Search questions..." FILTERS that list by each question's own wording and its
 *     deterministic aliases ("baths", "sq ft", "age of roof", "taxes", "utilities");
 *   - selecting a question shows the answer that is already in the markup.
 *
 * WHAT THIS DELIBERATELY IS NOT. Typing never answers anything: search only hides and shows
 * questions, and the viewer must then select one. Enter in the search box moves focus to the
 * first match; it selects nothing and submits nothing. This file performs no request of any
 * kind, opens no socket, navigates nowhere, names no endpoint, and never stores or logs typed
 * text. The search box has no `name` and sits in no <form>, so there is nothing to submit.
 * A test SCANS THIS SOURCE for the API names that would break those promises, which is why
 * none of them is spelled out here.
 *
 * THE PAGE IS THE SECURITY BOUNDARY. Every question and search term is read from elements the
 * server rendered for this viewer; a question this listing cannot answer, or this viewer may
 * not see, has no element here and so can never be found or shown.
 */
(function () {
    'use strict';

    var SELECTOR = '[data-ask-ai-picker]';

    /** Normalises case, curly quotes, dashes and punctuation so "sq-ft?" finds "sq ft". */
    function normalize(text) {
        if (typeof text !== 'string') {
            return '';
        }

        return text
            .replace(/[‘’ʼ]/g, "'")
            .replace(/[“”]/g, '"')
            .replace(/[‐‑‒–—―]/g, '-')
            .toLowerCase()
            .replace(/[-_/]/g, ' ')
            .replace(/[?!.,:;"()]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    /**
     * A question is shown when EVERY word of the query appears in its wording or aliases.
     * This is discovery, not answering: several questions may match, and the viewer chooses.
     */
    function matches(haystack, words) {
        for (var i = 0; i < words.length; i++) {
            if (haystack.indexOf(words[i]) === -1) {
                return false;
            }
        }

        return true;
    }

    function wire(root) {
        if (root.getAttribute('data-ask-ai-picker-ready') === '1') {
            return;
        }
        root.setAttribute('data-ask-ai-picker-ready', '1');

        var search  = root.querySelector('[data-ask-ai-picker-search]');
        var toggle  = root.querySelector('[data-ask-ai-picker-toggle]');
        var list    = root.querySelector('[data-ask-ai-picker-all]');
        var empty   = root.querySelector('[data-ask-ai-picker-empty]');
        var panel   = root.querySelector('[data-ask-ai-picker-answer]');
        var panelQ  = root.querySelector('[data-ask-ai-picker-answer-question]');
        var panelA  = root.querySelector('[data-ask-ai-picker-answer-text]');
        var answers = {};
        var expanded = false;

        Array.prototype.forEach.call(root.querySelectorAll('[data-ask-ai-answer-for]'), function (p) {
            answers[p.getAttribute('data-ask-ai-answer-for')] = {
                question: p.getAttribute('data-ask-ai-question-display') || '',
                answer: p.textContent.trim()
            };
        });

        var items = list ? Array.prototype.map.call(list.querySelectorAll('[data-ask-ai-pick]'), function (btn) {
            var terms = (btn.getAttribute('data-ask-ai-search-terms') || btn.textContent).split('|');
            return { el: btn, haystack: ' ' + terms.map(normalize).join(' | ') + ' ' };
        }) : [];

        function setExpanded(open) {
            expanded = open;
            if (toggle) {
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                toggle.textContent = toggle.getAttribute(open ? 'data-label-hide' : 'data-label-show') || toggle.textContent;
            }
            applyFilter();
        }

        function applyFilter() {
            if (!list) {
                return;
            }
            var query = normalize(search ? search.value : '');
            var words = query === '' ? [] : query.split(' ');
            var shown = 0;

            items.forEach(function (item) {
                var visible = words.length === 0 || matches(item.haystack, words);
                item.el.hidden = !visible;
                if (visible) {
                    shown++;
                }
            });

            // Searching reveals the list; clearing the search returns it to the toggle's state.
            list.hidden = !(expanded || words.length > 0) || shown === 0;
            if (empty) {
                empty.hidden = !(words.length > 0 && shown === 0);
            }
        }

        function select(id) {
            var entry = answers[id];
            if (!entry || !panel) {
                return;
            }
            Array.prototype.forEach.call(root.querySelectorAll('[data-ask-ai-pick]'), function (b) {
                b.setAttribute('aria-pressed', b.getAttribute('data-ask-ai-pick') === id ? 'true' : 'false');
            });
            // An owner answer's disclosure / source lines belong to that answer only.
            Array.prototype.forEach.call(panel.querySelectorAll('[data-ask-ai-owner-note]'), function (n) { n.remove(); });
            panelQ.textContent = entry.question;
            panelA.textContent = entry.answer;
            panel.hidden = false;
            panel.setAttribute('data-ask-ai-selected', id);
            if (typeof panel.scrollIntoView === 'function') {
                panel.scrollIntoView({ block: 'nearest' });
            }
        }

        root.addEventListener('click', function (event) {
            var btn = event.target.closest ? event.target.closest('[data-ask-ai-pick]') : null;
            if (btn && root.contains(btn)) {
                event.preventDefault();
                select(btn.getAttribute('data-ask-ai-pick'));
            }
        });

        if (toggle) {
            toggle.addEventListener('click', function (event) {
                event.preventDefault();
                setExpanded(!expanded);
            });
        }

        if (search) {
            search.addEventListener('input', applyFilter);
            search.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter') {
                    return;
                }
                // Never a submission: move to the first match so the viewer can choose it.
                event.preventDefault();
                var first = items.filter(function (i) { return !i.el.hidden; })[0];
                if (first && list && !list.hidden) {
                    first.el.focus();
                }
            });
        }

        applyFilter();
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
