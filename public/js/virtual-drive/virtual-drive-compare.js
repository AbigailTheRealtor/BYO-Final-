/*
 * Virtual Drive comparison page — INTERNAL, DEVELOPMENT ONLY.
 *
 * Lists the same stored homes and links each to the Apple page and the Google
 * page. It loads neither provider and never will: a link only preselects a home
 * (`?listing=`), and imagery starts only when the launch button on the provider
 * page is pressed.
 */
(function () {
    'use strict';

    var root = document.getElementById('vd-compare');

    if (!root) {
        return;
    }

    var endpoint = root.getAttribute('data-listings-endpoint');
    var appleUrl = root.getAttribute('data-apple-url');
    var googleUrl = root.getAttribute('data-google-url');
    var sharedKey = root.getAttribute('data-shared-coordinate-listing') || '';
    var rows = document.getElementById('vd-compare-rows');

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

    function link(base, id, text, view) {
        var url = new URL(base, window.location.href);
        var a = node('a', 'vd-compare-link', text);

        url.searchParams.set('listing', id);

        if (view) {
            url.searchParams.set('view', view);
        }

        a.href = url.href;

        return a;
    }

    function message(text) {
        rows.textContent = '';

        var tr = node('tr');
        var td = node('td', 'vd-muted', text);

        td.colSpan = 5;
        tr.appendChild(td);
        rows.appendChild(tr);
    }

    var url = new URL(endpoint, window.location.href);

    url.searchParams.set('set', 'test');

    fetch(url.href, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('listing endpoint answered HTTP ' + response.status);
            }

            return response.json();
        })
        .then(function (data) {
            rows.textContent = '';

            if (!data.listings.length) {
                message('No eligible test listings exist in the stored MLS data here.');

                return;
            }

            data.listings.forEach(function (listing) {
                var tr = node('tr');
                var home = node('td');
                var sign = node('td');
                var test = node('td', 'vd-compare-actions');

                home.appendChild(node('span', 'vd-compare-address', listing.address || 'Street address withheld'));
                home.appendChild(node('span', 'vd-compare-city', [listing.city, listing.state].filter(Boolean).join(', ')));

                if (listing.id === sharedKey) {
                    home.appendChild(node('span', 'vd-compare-flag', 'Shared-coordinate test: many condo units sit on this one point'));
                }

                sign.appendChild(node('span', 'vd-badge vd-badge-' + listing.transaction_type, listing.sign_label));

                test.appendChild(link(appleUrl, listing.id, 'Test Apple'));
                test.appendChild(link(googleUrl, listing.id, 'Test Google'));
                test.appendChild(link(googleUrl, listing.id, 'Customer preview', 'customer'));

                tr.appendChild(home);
                tr.appendChild(sign);
                tr.appendChild(node('td', 'vd-num', listing.display_price || '—'));
                tr.appendChild(node('td', 'vd-num vd-mono', listing.latitude.toFixed(6) + ', ' + listing.longitude.toFixed(6)));
                tr.appendChild(test);
                rows.appendChild(tr);
            });

            if (data.unavailable_keys && data.unavailable_keys.length) {
                var tr = node('tr');
                var td = node('td', 'vd-muted', data.unavailable_keys.length + ' configured test key(s) are missing or ineligible here and are not shown.');

                td.colSpan = 5;
                tr.appendChild(td);
                rows.appendChild(tr);
            }
        })
        .catch(function (err) {
            message('Listing data could not be loaded (' + err.message + ').');
        });
})();
