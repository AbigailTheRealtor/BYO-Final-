/*
 * Virtual Drive observation sheet — INTERNAL, DEVELOPMENT ONLY.
 *
 * The comparison is partly a human judgement ("does this feel like driving
 * through a neighbourhood shopping for homes?"), so the page records it beside
 * the imagery: per provider, per home, the same checklist on both pages, with
 * the facts the page measured itself (coverage, panorama distance, imagery
 * date, time to imagery) filled in automatically from the shell's
 * `virtual-drive:fact` events.
 *
 * Kept in this browser only (localStorage). It leaves the page only when
 * someone presses "Copy results", and it sends nothing anywhere.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'virtual-drive-observations-v1';

    var CHECKLIST = [
        ['coverage', 'Street-level imagery exists for this home'],
        ['opens_near', 'Camera opens near the home'],
        ['house_visible', 'Selected house visible when it opens'],
        ['faces_house', 'Opens facing the correct house'],
        ['quality', 'Imagery quality is good enough to judge the house'],
        ['rotate', '360° rotation works'],
        ['forward', 'Moving forward works'],
        ['backward', 'Moving backward works'],
        ['travel', 'Travelling ~200 m down the street works'],
        ['responsive', 'Feels responsive'],
        ['sign_rotate', 'Sign stays on the house while rotating'],
        ['sign_180', 'Sign stays on the house after turning 180°'],
        ['sign_move', 'Sign stays on the house after moving forward / back'],
        ['sign_believable', 'Sign size and height feel believable, not intrusive'],
        ['nearby', 'Nearby listings are shown without clutter'],
        ['neighbours', 'Neighbouring homes stay distinguishable'],
        ['condo', 'Units sharing one coordinate are usable'],
        ['card', 'Listing card shows the right home'],
        ['photos', 'Photos'],
        ['tour', '3D Tour'],
        ['details', 'Details'],
        ['ask', 'Ask a Question'],
        ['showing', 'Schedule Showing'],
        ['mobile', 'Mobile / touch'],
        ['shopping', 'Feels like virtual house shopping']
    ];

    var CHOICES = [['', '—'], ['yes', 'Yes'], ['partly', 'Partly'], ['no', 'No'], ['na', 'N/A']];
    var PROVIDERS = { apple: 'Apple Look Around', google: 'Google Street View' };

    var root = document.getElementById('vd-observations');

    if (!root) {
        return;
    }

    var mode = root.getAttribute('data-mode') || 'sheet';
    var shell = document.getElementById('vd-shell');
    var provider = shell ? shell.getAttribute('data-provider') : null;
    var current = null;

    function load() {
        try {
            var parsed = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '{}');

            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function save(data) {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        } catch (e) {
            // Private mode or blocked storage: the sheet still works for this visit.
        }
    }

    function entry(data, providerId, listingId) {
        data[providerId] = data[providerId] || {};
        data[providerId][listingId] = data[providerId][listingId] || { label: '', answers: {}, notes: '', facts: {} };

        return data[providerId][listingId];
    }

    function node(tag, className, text) {
        var el = document.createElement(tag);

        if (className) {
            el.className = className;
        }

        if (text !== undefined && text !== null) {
            el.textContent = text;
        }

        return el;
    }

    function describe(listing) {
        return listing.sign_label + ' · ' + (listing.display_price || 'price not published') + ' · '
            + (listing.address || listing.city || 'address withheld');
    }

    // ------------------------------------------------------------ export

    function toMarkdown(data) {
        var lines = ['## Virtual Drive observations', '', 'Exported ' + new Date().toISOString(), ''];

        Object.keys(PROVIDERS).forEach(function (providerId) {
            var homes = data[providerId] || {};
            var ids = Object.keys(homes);

            lines.push('### ' + PROVIDERS[providerId], '');

            if (!ids.length) {
                lines.push('_No homes recorded._', '');

                return;
            }

            ids.forEach(function (id) {
                var home = homes[id];

                lines.push('#### ' + (home.label || id) + ' (`' + id + '`)', '');

                Object.keys(home.facts || {}).forEach(function (label) {
                    lines.push('- Measured — ' + label + ': ' + home.facts[label]);
                });

                lines.push('', '| Check | Result |', '|---|---|');

                CHECKLIST.forEach(function (item) {
                    var answer = (home.answers || {})[item[0]] || '';
                    var shown = CHOICES.filter(function (c) { return c[0] === answer; })[0];

                    lines.push('| ' + item[1] + ' | ' + (shown && answer ? shown[1] : '—') + ' |');
                });

                lines.push('', 'Notes: ' + (home.notes ? home.notes.replace(/\s+/g, ' ') : '—'), '');
            });
        });

        return lines.join('\n');
    }

    function renderExport(container) {
        var data = load();
        var summary = Object.keys(PROVIDERS).map(function (providerId) {
            return PROVIDERS[providerId] + ': ' + Object.keys(data[providerId] || {}).length + ' home(s) recorded';
        }).join(' · ');

        var row = node('div', 'vd-obs-export');
        var copy = node('button', 'vd-obs-button', 'Copy results (both providers)');
        var clear = node('button', 'vd-obs-button vd-obs-button-quiet', 'Clear all');
        var status = node('span', 'vd-obs-status');
        var output = node('textarea', 'vd-obs-output');

        copy.type = 'button';
        copy.id = 'vd-obs-copy';
        clear.type = 'button';
        clear.id = 'vd-obs-clear';
        output.id = 'vd-obs-output';
        output.readOnly = true;
        output.hidden = true;
        output.setAttribute('aria-label', 'Exported observations');

        copy.addEventListener('click', function () {
            var text = toMarkdown(load());

            output.value = text;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () {
                    status.textContent = 'Copied. Paste it back into the conversation.';
                }, function () {
                    output.hidden = false;
                    output.select();
                    status.textContent = 'Select the text below and copy it.';
                });
            } else {
                output.hidden = false;
                output.select();
                status.textContent = 'Select the text below and copy it.';
            }
        });

        clear.addEventListener('click', function () {
            if (window.confirm('Clear every recorded observation in this browser?')) {
                save({});
                render();
            }
        });

        row.appendChild(copy);
        row.appendChild(clear);
        row.appendChild(status);
        container.appendChild(node('p', 'vd-obs-summary', summary));
        container.appendChild(row);
        container.appendChild(output);
    }

    // ------------------------------------------------------------ the sheet

    function renderFacts(container, home) {
        container.textContent = '';

        var labels = Object.keys(home.facts || {});

        if (!labels.length) {
            container.appendChild(node('p', 'vd-muted', 'Measured values appear here once this home has been opened on this provider.'));

            return;
        }

        var list = node('dl', 'vd-obs-facts');

        labels.forEach(function (label) {
            list.appendChild(node('dt', null, label));
            list.appendChild(node('dd', null, home.facts[label]));
        });

        container.appendChild(list);
    }

    function renderSheet(container) {
        var data = load();
        var home = entry(data, provider, current.id);
        var facts = node('div', 'vd-obs-facts-wrap');

        facts.id = 'vd-obs-facts';
        container.appendChild(node('p', 'vd-obs-home', (PROVIDERS[provider] || provider) + ' · ' + current.label));
        container.appendChild(facts);
        renderFacts(facts, home);

        var grid = node('div', 'vd-obs-grid');

        CHECKLIST.forEach(function (item) {
            var id = 'vd-obs-' + item[0];
            var label = node('label', 'vd-obs-label', item[1]);
            var select = node('select', 'vd-obs-select');

            label.htmlFor = id;
            select.id = id;

            CHOICES.forEach(function (choice) {
                var option = node('option', null, choice[1]);

                option.value = choice[0];
                select.appendChild(option);
            });

            select.value = (home.answers || {})[item[0]] || '';
            select.addEventListener('change', function () {
                var fresh = load();

                entry(fresh, provider, current.id).answers[item[0]] = select.value;
                save(fresh);
            });

            grid.appendChild(label);
            grid.appendChild(select);
        });

        container.appendChild(grid);

        var notesLabel = node('label', 'vd-obs-label', 'Notes for this home on this provider');
        var notes = node('textarea', 'vd-obs-notes');

        notesLabel.htmlFor = 'vd-obs-notes';
        notes.id = 'vd-obs-notes';
        notes.rows = 3;
        notes.value = home.notes || '';
        notes.addEventListener('input', function () {
            var fresh = load();

            entry(fresh, provider, current.id).notes = notes.value;
            save(fresh);
        });

        container.appendChild(notesLabel);
        container.appendChild(notes);
    }

    function render() {
        root.textContent = '';

        var details = node('details', 'vd-obs');
        var summary = node('summary', null, 'Observation sheet');
        var body = node('div', 'vd-obs-body');

        details.open = true;
        details.appendChild(summary);
        details.appendChild(body);

        if (mode === 'sheet' && current && provider) {
            renderSheet(body);
        } else if (mode === 'sheet') {
            body.appendChild(node('p', 'vd-muted', 'Choose a home to record observations.'));
        }

        renderExport(body);
        root.appendChild(details);
    }

    document.addEventListener('virtual-drive:selected', function (event) {
        var listing = event.detail && event.detail.listing;

        if (!listing || !provider) {
            return;
        }

        current = { id: listing.id, label: describe(listing) };

        var data = load();

        entry(data, provider, listing.id).label = current.label;
        save(data);
        render();
    });

    document.addEventListener('virtual-drive:fact', function (event) {
        var detail = event.detail || {};

        if (!detail.provider || !detail.listingId) {
            return;
        }

        var data = load();

        entry(data, detail.provider, detail.listingId).facts[detail.label] = detail.value;
        save(data);

        var container = document.getElementById('vd-obs-facts');

        if (container && current && current.id === detail.listingId && detail.provider === provider) {
            renderFacts(container, entry(data, provider, current.id));
        }
    });

    render();
})();
