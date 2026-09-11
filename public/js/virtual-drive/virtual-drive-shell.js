/*
 * Virtual Drive provider proof — shared shell.
 *
 * INTERNAL, DEVELOPMENT ONLY. Served only when VirtualDriveProofGate allows it
 * (local / development / testing AND VIRTUAL_DRIVE_PROOF_ENABLED).
 *
 * WHO OWNS WHAT
 * -------------
 * The shell owns everything that is ours: the listing data (from our own
 * endpoint, never from Apple or Google), the listing card, Previous / Next, the
 * action buttons, the launch button and the instrumentation. A provider owns
 * only the street-level pixels and whatever camera information it chooses to
 * expose. The shell never branches on WHICH provider is loaded — only on the
 * capabilities it declares — so both halves of the comparison get the same UI.
 *
 * NOTHING STREET-LEVEL LOADS UNTIL THE LAUNCH BUTTON IS PRESSED
 * -------------------------------------------------------------
 * Opening the page, choosing a home, opening its card, its photos or its tour
 * never touches the provider: there is no provider to touch until launch() has
 * run. launch() is the ONLY caller of provider.load(), it runs only from the
 * launch button, and it is locked from the first click:
 *
 *     loading → idle ──click──▶ opening ──▶ open
 *                                  │
 *                                  └──▶ retry (no imagery here; press again)
 *                                  └──▶ locked (library failed, key rejected,
 *                                               or a STOP from the provider)
 *
 * A click while `opening` or `open` does nothing. Nothing retries by itself: a
 * library that failed to load is never requested again from this page, and a
 * home with no imagery waits for another deliberate press. The URL carries the
 * selected home (`?listing=`) and never a launch, so reloading the page can
 * never start a session.
 *
 * PROVIDER CONTRACT (both provider files implement exactly this)
 * -------------------------------------------------------------
 *   id                               'apple' | 'google'
 *   capabilities.geoAnchoredMarkers  can a sign be pinned to a coordinate in the imagery?
 *   capabilities.cameraState         does the provider expose camera position / heading?
 *   load(cfg, hooks)   -> Promise    load the vendor library; reject on failure
 *   mount(element)                   take ownership of the imagery element
 *   setListings(list)                every listing known so far
 *   select(listing)                  mark a listing selected without moving the camera
 *   show(listing)      -> Promise<{coverage: boolean, note: string, superseded?: boolean}>
 *
 * MOVEMENT NEVER REACHES BRIDGE
 * -----------------------------
 * A camera move can cause at most one request to OUR listings endpoint, and
 * only once the camera has travelled most of a query radius from the last
 * query. That endpoint reads stored rows and holds no provider client.
 *
 * Listing values are only ever written as text (textContent), never parsed as
 * markup, and every URL is checked for an http(s) scheme before it becomes an
 * href or a src.
 */
(function () {
    'use strict';

    var shell = document.getElementById('vd-shell');

    if (!shell) {
        return;
    }

    var cfg = {
        provider:        shell.getAttribute('data-provider'),
        credential:      shell.getAttribute('data-credential') || '',
        credentialName:  shell.getAttribute('data-credential-name') || '',
        libraryUrl:      shell.getAttribute('data-library-url') || '',
        apiVersion:      shell.getAttribute('data-api-version') || '',
        endpoint:        shell.getAttribute('data-listings-endpoint'),
        nearbyRadius:    parseInt(shell.getAttribute('data-nearby-radius'), 10) || 400,
        selectedListing: shell.getAttribute('data-selected-listing') || '',
        launchLabel:     shell.getAttribute('data-launch-label') || 'Start',
        defaultListing:  shell.getAttribute('data-default-listing') || '',
        requeryFraction: positive(shell.getAttribute('data-nearby-requery')) || 0.6,
        viewMode:        viewMode(),
        // Sign sizing and grouping. Missing values fall back to VirtualDriveSigns.DEFAULTS.
        signs: {
            maxDistance:   positive(shell.getAttribute('data-sign-max-distance')),
            minDistance:   positive(shell.getAttribute('data-sign-min-distance')),
            nearWidth:     positive(shell.getAttribute('data-sign-near-width')),
            farWidth:      positive(shell.getAttribute('data-sign-far-width')),
            groupRadius:   positive(shell.getAttribute('data-sign-group-radius')),
            closeCoverage: positive(shell.getAttribute('data-close-coverage'))
        }
    };

    function positive(value) {
        var n = parseFloat(value);

        return isFinite(n) && n > 0 ? n : undefined;
    }

    // The page decides the view. A page that does not (a static fixture) may take
    // it from ?view=. Either way it only changes what is shown, never what loads.
    function viewMode() {
        var attr = shell.getAttribute('data-view-mode');

        if (attr === 'customer' || attr === 'dev') {
            return attr;
        }

        try {
            return new URL(window.location.href).searchParams.get('view') === 'customer' ? 'customer' : 'dev';
        } catch (e) {
            return 'dev';
        }
    }

    document.body.classList.add('vd-view-' + cfg.viewMode);

    // Read-only counters for the browser specs and for a live session. Nothing
    // reads them back to make a decision.
    var diagnostics = window.VirtualDriveDiagnostics = window.VirtualDriveDiagnostics || {};

    diagnostics.shell = { launchClicks: 0, launchesStarted: 0, launchState: 'loading', providerLoaded: false };

    var state = {
        registered: null,
        provider: null,
        walk: [],            // Previous / Next order: the curated test set
        known: {},           // every listing we have been told about, by id
        coverage: {},        // listing id -> true / false, once the provider has answered
        selectedId: null,
        position: null,      // camera position, only when the provider exposes it
        lastQueryCenter: null,
        queryInFlight: false,
        counters: {},
        lightbox: null,
        launch: 'loading',   // loading | idle | opening | open | retry | locked
        loadFailed: false,
        fatal: null,
        launchStartedAt: null,
        firstImageryLogged: false,
        attribution: '',
        coverageNotes: {},   // listing id -> "imagery is nearby, not at the home"
        shopper: null        // { listingId, building: [ids] | null, photo, choosing }
    };

    function $(id) {
        return document.getElementById(id);
    }

    function now() {
        return window.performance && performance.now ? performance.now() : Date.now();
    }

    function emit(name, detail) {
        try {
            document.dispatchEvent(new CustomEvent(name, { detail: detail }));
        } catch (e) {
            // An observer failing must never take the shell with it.
        }
    }

    // ------------------------------------------------------------ instrumentation

    function renderCounters() {
        var list = $('vd-counters');

        list.textContent = '';

        Object.keys(state.counters).forEach(function (name) {
            list.appendChild(node('dt', null, name));
            list.appendChild(node('dd', null, String(state.counters[name])));
        });
    }

    function count(name, by) {
        state.counters[name] = (state.counters[name] || 0) + (by === undefined ? 1 : by);
        renderCounters();
    }

    function setCounter(name, value) {
        state.counters[name] = value;
        renderCounters();
    }

    function log(event, detail) {
        var line = new Date().toISOString().slice(11, 23) + '  ' + event + (detail ? ' — ' + detail : '');
        var list = $('vd-events');
        var item = node('li', null, line);

        list.insertBefore(item, list.firstChild);

        while (list.children.length > 250) {
            list.removeChild(list.lastChild);
        }

        if (window.console && console.info) {
            console.info('[virtual-drive] ' + line);
        }
    }

    // A measured fact about one home on this provider, for the observation sheet.
    function fact(listingId, label, value) {
        log('Measured', label + ': ' + value + ' (' + listingId + ')');
        emit('virtual-drive:fact', { provider: cfg.provider, listingId: listingId, label: label, value: String(value) });
    }

    // ------------------------------------------------------------ helpers

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

    function safeUrl(value) {
        if (typeof value !== 'string' || value === '') {
            return null;
        }

        try {
            var parsed = new URL(value, window.location.href);

            return parsed.protocol === 'https:' || parsed.protocol === 'http:' ? parsed.href : null;
        } catch (e) {
            return null;
        }
    }

    function meters(a, b) {
        var rad = Math.PI / 180;
        var dLat = (b.lat - a.lat) * rad;
        var dLng = (b.lng - a.lng) * rad;
        var h = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(a.lat * rad) * Math.cos(b.lat * rad) * Math.sin(dLng / 2) * Math.sin(dLng / 2);

        return 2 * 6371008.8 * Math.asin(Math.min(1, Math.sqrt(h)));
    }

    function point(listing) {
        return { lat: listing.latitude, lng: listing.longitude };
    }

    function knownList() {
        return Object.keys(state.known).map(function (id) { return state.known[id]; });
    }

    function selected() {
        return state.selectedId ? state.known[state.selectedId] || null : null;
    }

    function walkIndex(id) {
        for (var i = 0; i < state.walk.length; i++) {
            if (state.walk[i].id === id) {
                return i;
            }
        }

        return -1;
    }

    function describe(listing) {
        return listing.sign_label + ' · ' + (listing.display_price || 'price not published') + ' · '
            + (listing.address || listing.city || 'address withheld');
    }

    // The selected home is part of the URL so a link or a reload lands on it.
    // A LAUNCH never is: nothing in the URL can start a session.
    function rememberSelectionInUrl(id) {
        try {
            var url = new URL(window.location.href);

            url.searchParams.set('listing', id);
            window.history.replaceState(null, '', url.href);
        } catch (e) {
            // A sandboxed frame without history access loses the convenience, nothing else.
        }
    }

    // ------------------------------------------------------------ data

    function fetchListings(params) {
        var url = new URL(cfg.endpoint, window.location.href);

        Object.keys(params).forEach(function (key) {
            url.searchParams.set(key, params[key]);
        });

        count('Listing API calls (our stored MLS data)');

        return fetch(url.href, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('listing endpoint answered HTTP ' + response.status);
                }

                return response.json();
            })
            .then(function (data) {
                count('Bridge/Stellar requests caused', data.provider_requests || 0);

                return data;
            });
    }

    function remember(listings) {
        listings.forEach(function (listing) {
            state.known[listing.id] = listing;
        });

        if (state.provider) {
            state.provider.setListings(knownList());
        }

        renderNearby();
    }

    // Movement -> at most one query to OUR endpoint per ~radius travelled.
    function maybeQueryNearby(center, reason) {
        if (state.queryInFlight) {
            return;
        }

        if (state.lastQueryCenter && meters(state.lastQueryCenter, center) < cfg.nearbyRadius * cfg.requeryFraction) {
            return;
        }

        state.queryInFlight = true;
        state.lastQueryCenter = center;

        fetchListings({ lat: center.lat.toFixed(6), lng: center.lng.toFixed(6), radius: cfg.nearbyRadius })
            .then(function (data) {
                log('Nearby query (' + reason + ')', data.count + ' eligible listing(s) within '
                    + data.radius_m + ' m — stored data, no provider request');
                remember(data.listings);
            })
            .catch(function (err) {
                log('Nearby query failed', err.message);
            })
            .then(function () {
                state.queryInFlight = false;
            });
    }

    // ------------------------------------------------------------ listing card

    function renderCard(listing) {
        var body = $('vd-card-body');

        body.textContent = '';

        var head = node('div', 'vd-card-head');
        head.appendChild(node('span', 'vd-badge vd-badge-' + listing.transaction_type, listing.sign_label));
        head.appendChild(node('span', 'vd-price', listing.display_price || 'Price not published'));
        body.appendChild(head);

        var facts = [];

        if (listing.beds !== null) { facts.push(listing.beds + ' Bed'); }
        if (listing.baths !== null) { facts.push(listing.baths + ' Bath'); }
        if (listing.living_area !== null) { facts.push(Number(listing.living_area).toLocaleString() + ' sq ft'); }
        if (listing.property_subtype) { facts.push(listing.property_subtype); }

        if (facts.length) {
            body.appendChild(node('p', 'vd-facts', facts.join(' · ')));
        }

        var place = [listing.city, listing.state].filter(Boolean).join(', ');

        body.appendChild(node('p', 'vd-address', listing.address
            ? listing.address + (place ? ', ' + place : '')
            : 'Street address withheld at the listing broker\'s request' + (place ? ' — ' + place : '')));

        body.appendChild(node('p', 'vd-status', 'Status: ' + (listing.effective_status || 'unknown')));

        var photos = Array.isArray(listing.photo_urls) ? listing.photo_urls : [];

        if (photos.length) {
            var strip = node('div', 'vd-photo-strip');

            photos.slice(0, 4).forEach(function (url, i) {
                var src = safeUrl(url);

                if (!src) {
                    return;
                }

                var button = node('button', 'vd-thumb');
                var img = node('img');

                button.type = 'button';
                button.setAttribute('aria-label', 'Open photo ' + (i + 1));
                img.src = src;
                img.alt = '';
                img.loading = 'lazy';
                button.appendChild(img);
                button.addEventListener('click', function () { openLightbox(listing, i); });
                strip.appendChild(button);
            });

            body.appendChild(strip);
        }

        // Development verification: the proof's claim is "this card is THAT listing".
        body.appendChild(node('p', 'vd-verify', 'Listing key ' + listing.id + ' · MLS coordinate '
            + listing.latitude.toFixed(6) + ', ' + listing.longitude.toFixed(6)
            + (state.coverage[listing.id] === false ? ' · no street-level coverage' : '')));

        renderActions(listing);

        var index = walkIndex(listing.id);

        $('vd-position').textContent = index >= 0 ? (index + 1) + ' of ' + state.walk.length : 'nearby listing';
    }

    function renderActions(listing) {
        var box = $('vd-actions');
        var gaps = $('vd-unavailable-actions');

        box.textContent = '';
        gaps.textContent = '';

        (listing.actions || []).forEach(function (action) {
            var button = node('button', 'vd-action vd-action-' + action.key, action.label);

            button.type = 'button';

            if (!action.available) {
                button.disabled = true;
                button.title = action.reason || 'Unavailable';
                gaps.appendChild(node('li', null, action.label + ': ' + (action.reason || 'unavailable')));
            } else {
                button.addEventListener('click', function () { runAction(listing, action); });
            }

            box.appendChild(button);
        });
    }

    function runAction(listing, action) {
        log('Action "' + action.label + '"', 'listing ' + listing.id);

        if (action.key === 'photos') {
            openLightbox(listing, 0);

            return;
        }

        var href = safeUrl(action.url);

        if (!href) {
            log('Action refused', 'no safe URL for ' + action.key);

            return;
        }

        window.open(href, '_blank', 'noopener');
    }

    // ------------------------------------------------------------ the screen-fixed sign

    function renderSign(listing) {
        var sign = $('vd-sign');
        var anchored = state.provider && state.provider.capabilities.geoAnchoredMarkers;

        if (!listing || !state.provider || anchored || state.fatal || state.coverage[listing.id] !== true) {
            sign.hidden = true;

            return;
        }

        sign.className = 'vd-sign vd-sign-' + listing.transaction_type;
        $('vd-sign-label').textContent = listing.sign_label;
        $('vd-sign-price').textContent = listing.display_price || '';
        $('vd-sign-caveat').textContent = 'Screen-fixed overlay. It is not attached to the house — '
            + 'it stays here whatever the camera does.';
        sign.hidden = false;
    }

    // ------------------------------------------------------------ nearby list

    function renderNearby() {
        var list = $('vd-nearby');
        var origin = state.position || (selected() ? point(selected()) : null);

        list.textContent = '';

        var rows = knownList().map(function (listing) {
            return { listing: listing, distance: origin ? meters(origin, point(listing)) : null };
        });

        rows.sort(function (a, b) { return (a.distance || 0) - (b.distance || 0); });

        rows.slice(0, 12).forEach(function (row) {
            var item = node('li', row.listing.id === state.selectedId ? 'is-selected' : null);
            var button = node('button', 'vd-nearby-item', describe(row.listing)
                + (row.distance !== null ? ' · ' + Math.round(row.distance) + ' m' : ''));

            button.type = 'button';
            button.addEventListener('click', function () { selectById(row.listing.id, 'nearby list'); });
            item.appendChild(button);
            list.appendChild(item);
        });

        $('vd-nearby-note').textContent = state.position ? '(distance from the camera)' : '(distance from the selected home)';
    }

    // ------------------------------------------------------------ selection

    function selectById(id, via, options) {
        var listing = state.known[id];
        var move = !options || options.move !== false;

        if (!listing) {
            log('Selection refused', 'unknown listing ' + id);

            return;
        }

        state.selectedId = id;
        rememberSelectionInUrl(id);
        log('Selected ' + listing.sign_label, id + ' via ' + via);
        emit('virtual-drive:selected', { provider: cfg.provider, listing: listing });

        renderCard(listing);
        renderSign(listing);
        renderNearby();
        renderLaunch();
        maybeQueryNearby(point(listing), 'selected home');
        openShopper(id, options && options.building ? options.building : null);

        // Before launch there is no provider to talk to — and that is the point.
        if (!state.provider || state.fatal) {
            return;
        }

        if (move) {
            showInProvider(listing).catch(function () {
                // Already reported by showInProvider.
            });
        } else {
            state.provider.select(listing);
        }
    }

    function step(delta) {
        if (!state.walk.length) {
            return;
        }

        var i = walkIndex(state.selectedId);

        i = i < 0 ? 0 : (i + delta + state.walk.length) % state.walk.length;
        selectById(state.walk[i].id, delta > 0 ? 'Next Home' : 'Previous Home');
    }

    function setImageryStatus(kind, text) {
        var status = $('vd-imagery-status');

        status.className = 'vd-imagery-status is-' + kind;
        status.textContent = text || '';
    }

    function showInProvider(listing) {
        setImageryStatus('loading', 'Loading street-level imagery at the MLS coordinate…');

        return state.provider.show(listing).then(function (result) {
            // A newer selection overtook this one; its answer is not about coverage.
            if (result.superseded) {
                return result;
            }

            state.coverage[listing.id] = result.coverage;

            // Imagery that exists only some way off is reported as exactly that.
            if (result.coverage && result.near === false) {
                state.coverageNotes[listing.id] = result.note;
            } else {
                delete state.coverageNotes[listing.id];
            }
            setCounter('Listings with coverage', Object.keys(state.coverage).filter(function (k) { return state.coverage[k]; }).length);
            setCounter('Listings without coverage', Object.keys(state.coverage).filter(function (k) { return !state.coverage[k]; }).length);
            log(result.coverage ? 'Coverage YES' : 'Coverage NO', listing.id + (result.note ? ' — ' + result.note : ''));

            if (result.coverage && !state.firstImageryLogged && state.launchStartedAt !== null) {
                state.firstImageryLogged = true;
                setCounter('Launch press → provider ready (ms)', Math.round(now() - state.launchStartedAt));
            }

            setImageryStatus(result.coverage ? (result.near === false ? 'far' : 'ok') : 'none', result.coverage
                ? result.note
                : 'No street-level imagery for this home. ' + (result.note || '') + ' The listing stays fully available.');

            if (state.selectedId === listing.id) {
                renderCard(listing);
                renderSign(listing);
                renderShopper();
            }

            return result;
        }, function (err) {
            log('Provider show failed', err.message);
            setImageryStatus('none', 'Street-level imagery failed: ' + err.message + ' The listing stays fully available.');

            throw err;
        });
    }

    // ------------------------------------------------------------ launch (the billing boundary)

    function renderLaunch() {
        var button = $('vd-launch');
        var listing = selected();

        diagnostics.shell.launchState = state.launch;
        $('vd-launch-panel').hidden = state.launch === 'open';
        $('vd-launch-home').textContent = listing ? describe(listing) : '';

        if (state.launch === 'idle') {
            button.disabled = false;
            button.textContent = cfg.launchLabel;
        } else if (state.launch === 'retry') {
            button.disabled = false;
            button.textContent = 'Try again';
        } else if (state.launch === 'opening') {
            button.disabled = true;
            button.textContent = 'Opening Virtual Drive…';
        } else if (state.launch === 'locked') {
            button.disabled = true;
            button.textContent = 'Unavailable';
        } else {
            button.disabled = true;
            button.textContent = 'Loading listings…';
        }
    }

    function lock(message) {
        state.launch = 'locked';
        $('vd-launch-note').textContent = message;
        renderLaunch();
    }

    function launch() {
        diagnostics.shell.launchClicks++;

        // THE LOCK. Only an idle page, or one waiting on a deliberate retry, may start.
        if (state.launch !== 'idle' && state.launch !== 'retry') {
            count('Launch presses ignored (already opening, open or locked)');

            return;
        }

        var provider = state.registered;
        var listing = selected();

        if (!provider || !listing || !cfg.credential || state.fatal) {
            return;
        }

        state.launch = 'opening';
        renderLaunch();

        diagnostics.shell.launchesStarted++;
        count('Deliberate launches');
        state.launchStartedAt = now();
        log('Launch pressed', provider.id + ' · listing ' + listing.id);

        var ready = state.provider
            ? Promise.resolve()
            : provider.load(cfg, hooks).then(function () {
                if (state.fatal) {
                    throw new Error(state.fatal);
                }

                provider.mount($('vd-street'));
                state.provider = provider;
                diagnostics.shell.providerLoaded = true;
                provider.setListings(knownList());
            }, function (err) {
                state.loadFailed = true;

                throw err;
            });

        ready.then(function () {
            return showInProvider(listing);
        }).then(function (result) {
            if (state.fatal) {
                return;
            }

            state.launch = result && result.coverage ? 'open' : 'retry';
            renderLaunch();
            renderSign(selected());

            if (state.launch === 'open' && selected()) {
                openShopper(selected().id, null);
            }
        }, function (err) {
            log('Launch failed', err.message);

            if (state.fatal) {
                return;
            }

            if (state.loadFailed) {
                // Never requested again from this page — a reload is the deliberate retry.
                lock('The ' + provider.id + ' library could not be loaded (' + err.message + '). Nothing will be retried; reload the page to try again.');

                return;
            }

            state.launch = 'retry';
            renderLaunch();
        });
    }

    // ------------------------------------------------------------ the shopper card
    //
    // What a sign click opens, floating over the imagery. It is ours and pure
    // DOM: opening it, switching it to another sign's listing, paging photos or
    // choosing a unit in a building never asks the provider for anything, so the
    // one panorama is never rebuilt. Only actions that exist are offered.

    function openShopper(listingId, building) {
        if (!state.provider || state.fatal || !state.known[listingId]) {
            return;
        }

        state.shopper = { listingId: listingId, building: building || null, photo: 0, choosing: false };
        renderShopper();
    }

    function openChooser(ids) {
        if (!state.provider || state.fatal) {
            return;
        }

        var known = ids.filter(function (id) { return !!state.known[id]; });

        if (!known.length) {
            return;
        }

        state.shopper = { listingId: null, building: known, photo: 0, choosing: true };
        renderShopper();
    }

    function closeShopper() {
        state.shopper = null;
        renderShopper();
    }

    function shopperButton(className, text, onClick) {
        var button = node('button', className, text);

        button.type = 'button';
        button.addEventListener('click', onClick);

        return button;
    }

    function facts(listing) {
        var out = [];

        if (listing.beds !== null && listing.beds !== undefined) { out.push(listing.beds + ' bd'); }
        if (listing.baths !== null && listing.baths !== undefined) { out.push(listing.baths + ' ba'); }
        if (listing.living_area !== null && listing.living_area !== undefined) { out.push(Number(listing.living_area).toLocaleString() + ' sq ft'); }
        if (listing.property_subtype) { out.push(listing.property_subtype); }

        return out.join(' · ');
    }

    function addressLine(listing) {
        var place = [listing.city, listing.state].filter(Boolean).join(', ');

        return listing.address
            ? listing.address + (place ? ', ' + place : '')
            : 'Street address withheld at the listing broker\'s request' + (place ? ' — ' + place : '');
    }

    function renderShopper() {
        var box = $('vd-shopper');
        var s = state.shopper;

        box.textContent = '';

        if (!s) {
            box.hidden = true;

            return;
        }

        var close = shopperButton('vd-shopper-close', '×', closeShopper);

        close.setAttribute('aria-label', 'Close listing card');
        box.appendChild(close);

        if (s.choosing) {
            renderChooser(box, s.building);
        } else {
            renderListingCard(box, s);
        }

        if (state.attribution) {
            box.appendChild(node('p', 'vd-shopper-attribution', state.attribution));
        }

        box.hidden = false;
    }

    function renderListingCard(box, s) {
        var listing = state.known[s.listingId];

        if (!listing) {
            box.hidden = true;

            return;
        }

        box.setAttribute('data-listing', listing.id);

        if (s.building) {
            box.appendChild(shopperButton('vd-shopper-back', '‹ All ' + s.building.length + ' units here', function () { openChooser(s.building); }));
        }

        var photos = (listing.photo_urls || []).map(safeUrl).filter(Boolean);

        if (photos.length) {
            var index = ((s.photo % photos.length) + photos.length) % photos.length;
            var figure = node('div', 'vd-shopper-photo');
            var img = node('img');

            img.src = photos[index];
            img.alt = 'Photo ' + (index + 1) + ' of ' + photos.length;
            figure.appendChild(img);

            if (photos.length > 1) {
                var prev = shopperButton('vd-shopper-photo-step vd-shopper-photo-prev', '‹', function () { s.photo = index - 1; renderShopper(); });
                var next = shopperButton('vd-shopper-photo-step vd-shopper-photo-next', '›', function () { s.photo = index + 1; renderShopper(); });

                prev.setAttribute('aria-label', 'Previous photo');
                next.setAttribute('aria-label', 'Next photo');
                figure.appendChild(prev);
                figure.appendChild(next);
            }

            figure.appendChild(node('span', 'vd-shopper-count', (index + 1) + ' / ' + photos.length));
            box.appendChild(figure);
        }

        var head = node('div', 'vd-shopper-head');

        head.appendChild(node('span', 'vd-badge vd-badge-' + listing.transaction_type, listing.sign_label));
        head.appendChild(node('span', 'vd-shopper-price', listing.display_price || 'Price not published'));
        box.appendChild(head);
        box.appendChild(node('p', 'vd-shopper-address', addressLine(listing)));

        var line = facts(listing);

        if (line) {
            box.appendChild(node('p', 'vd-shopper-facts', line));
        }

        if (state.coverageNotes[listing.id]) {
            box.appendChild(node('p', 'vd-shopper-coverage', state.coverageNotes[listing.id]));
        }

        var actions = node('div', 'vd-shopper-actions');

        if (photos.length) {
            actions.appendChild(shopperButton('vd-shopper-action vd-shopper-action-photos', 'View photos (' + photos.length + ')', function () {
                openLightbox(listing, ((s.photo % photos.length) + photos.length) % photos.length);
            }));
        }

        // Only what exists. The developer panel still lists the rest with reasons.
        (listing.actions || []).forEach(function (action) {
            if (!action.available || action.key === 'photos') {
                return;
            }

            actions.appendChild(shopperButton('vd-shopper-action vd-shopper-action-' + action.key, action.label, function () {
                runAction(listing, action);
            }));
        });

        box.appendChild(actions);
    }

    function renderChooser(box, ids) {
        var list = ids.map(function (id) { return state.known[id]; }).filter(Boolean);
        var sale = list.some(function (l) { return l.transaction_type === 'sale'; });
        var rent = list.some(function (l) { return l.transaction_type === 'rent'; });

        box.removeAttribute('data-listing');
        box.appendChild(node('p', 'vd-shopper-kicker', sale && rent ? 'FOR SALE & RENT' : (sale ? 'FOR SALE' : 'FOR RENT')));
        box.appendChild(node('h2', 'vd-shopper-title', list.length + ' units in this building'));
        box.appendChild(node('p', 'vd-shopper-hint', 'Choose a unit to see its listing.'));

        var menu = node('ul', 'vd-shopper-units');

        list.forEach(function (listing) {
            var unitNo = window.VirtualDriveSigns ? window.VirtualDriveSigns.unit(listing) : null;
            var item = node('li');
            var label = (unitNo ? 'Unit ' + unitNo : (listing.address || 'Address withheld'))
                + ' · ' + (listing.display_price || 'price not published')
                + (facts(listing) ? ' · ' + facts(listing) : '');
            var button = shopperButton('vd-shopper-unit', label, function () {
                selectById(listing.id, 'building chooser', { move: false, building: ids });
            });

            button.setAttribute('data-listing', listing.id);
            item.appendChild(button);
            menu.appendChild(item);
        });

        box.appendChild(menu);
    }

    // ------------------------------------------------------------ photos

    function openLightbox(listing, index) {
        var photos = (listing.photo_urls || []).map(safeUrl).filter(Boolean);

        if (!photos.length) {
            return;
        }

        state.lightbox = { listing: listing, photos: photos, index: Math.max(0, Math.min(index, photos.length - 1)) };
        renderLightbox();
        $('vd-lightbox').hidden = false;
        log('Photos opened', 'listing ' + listing.id);
    }

    function renderLightbox() {
        var box = state.lightbox;

        $('vd-lightbox-img').src = box.photos[box.index];
        $('vd-lightbox-caption').textContent = (box.index + 1) + ' / ' + box.photos.length + ' · '
            + (box.listing.address || box.listing.city || '');
    }

    function stepLightbox(delta) {
        var box = state.lightbox;

        if (!box) {
            return;
        }

        box.index = (box.index + delta + box.photos.length) % box.photos.length;
        renderLightbox();
    }

    function closeLightbox() {
        $('vd-lightbox').hidden = true;
        state.lightbox = null;
    }

    function wireLightbox() {
        $('vd-lightbox-close').addEventListener('click', closeLightbox);
        $('vd-lightbox-prev').addEventListener('click', function () { stepLightbox(-1); });
        $('vd-lightbox-next').addEventListener('click', function () { stepLightbox(1); });
        document.addEventListener('keydown', function (event) {
            if (!state.lightbox) {
                return;
            }

            if (event.key === 'Escape') { closeLightbox(); }
            if (event.key === 'ArrowLeft') { stepLightbox(-1); }
            if (event.key === 'ArrowRight') { stepLightbox(1); }
        });
    }

    // ------------------------------------------------------------ provider hooks

    var hooks = {
        log: log,
        count: count,
        setCounter: setCounter,
        fact: fact,
        selectedListing: selected,
        sinceLaunch: function () {
            return state.launchStartedAt === null ? null : Math.round(now() - state.launchStartedAt);
        },
        addControl: function (el) { $('vd-provider-controls').appendChild(el); },
        reshow: function () {
            var listing = selected();

            if (listing && state.provider && !state.fatal) {
                showInProvider(listing).catch(function () {});
            }
        },
        // The provider says stop: a rejected credential, or a refused second session.
        fatal: function (reason) {
            if (state.fatal) {
                return;
            }

            state.fatal = reason;
            log('STOPPED', reason);
            setImageryStatus('none', reason);
            lock(reason);
            renderSign(selected());
        },
        onSelect: function (id, via) {
            selectById(id, via, { move: false });
        },
        // A building sign: several listings share one point. Offer the units;
        // nothing moves and nothing is loaded.
        onChooseBuilding: function (ids, via) {
            log('Building sign', ids.length + ' units via ' + via);
            openChooser(ids);
        },
        onMoved: function (position) {
            state.position = position;
            renderNearby();
            maybeQueryNearby(position, 'camera moved');
        },
        onRelative: function (info) {
            var hud = $('vd-hud');

            if (!info) {
                hud.hidden = true;

                return;
            }

            var angle = Math.round(Math.abs(info.relativeHeading));
            var side = angle < 10 ? 'straight ahead' : angle + '° to your ' + (info.relativeHeading > 0 ? 'right' : 'left');

            hud.textContent = 'Selected home: ' + Math.round(info.distance) + ' m away, ' + side + '.';
            hud.hidden = false;
        },
        onSceneChanged: function () {
            var sign = $('vd-sign');

            if (sign.hidden) {
                return;
            }

            sign.classList.add('is-stale');
            $('vd-sign-caveat').textContent = 'The view changed. This sign cannot tell whether the house is '
                + 'still in frame — the provider does not expose where the camera is.';
        }
    };

    window.VirtualDrive = {
        register: function (provider) {
            if (state.registered) {
                log('Second provider refused', provider.id);

                return;
            }

            state.registered = provider;
        },
        // The launch button's handler, exposed so the browser specs can prove the
        // lock holds even when the button's disabled state is bypassed.
        launch: launch
    };

    // ------------------------------------------------------------ start

    function start() {
        $('vd-prev').addEventListener('click', function () { step(-1); });
        $('vd-next').addEventListener('click', function () { step(1); });
        $('vd-launch').addEventListener('click', launch);
        $('vd-sign-cta').addEventListener('click', function () {
            var listing = selected();

            if (listing) {
                log('Sign clicked', 'listing ' + listing.id);
            }

            $('vd-card').scrollIntoView({ behavior: 'smooth' });
        });
        wireLightbox();
        renderLaunch();

        fetchListings({ set: 'test' }).then(function (data) {
            state.walk = data.listings;
            state.attribution = data.attribution || '';
            $('vd-attribution').textContent = state.attribution;

            if (data.unavailable_keys && data.unavailable_keys.length) {
                log('Test keys not published', data.unavailable_keys.length
                    + ' key(s) missing from stored data or ineligible: ' + data.unavailable_keys.join(', '));
            }

            if (!state.walk.length) {
                $('vd-card-body').textContent = 'No eligible listings.';
                lock('No eligible test listings exist in the stored MLS data here.');

                return;
            }

            remember(state.walk);

            var requested = cfg.selectedListing && state.known[cfg.selectedListing] ? cfg.selectedListing : null;

            if (cfg.selectedListing && !requested) {
                log('Requested listing is not in the test set', cfg.selectedListing);
            }

            // No ?listing=: start where the imagery is close to the home, if configured.
            var fallback = !requested && cfg.defaultListing && state.known[cfg.defaultListing] ? cfg.defaultListing : null;

            selectById(requested || fallback || state.walk[0].id, requested ? 'link' : (fallback ? 'default start' : 'initial'));

            if (!state.registered) {
                lock('No street-level provider is registered on this page.');

                return;
            }

            setCounter('Provider', state.registered.id);

            if (!cfg.credential) {
                log('Provider not loaded', cfg.credentialName + ' is not configured — no request sent to ' + state.registered.id);
                lock('Street-level imagery is unavailable because ' + cfg.credentialName
                    + ' is not configured. No request was sent to the provider.');

                return;
            }

            state.launch = 'idle';
            renderLaunch();
        }).catch(function (err) {
            lock('Listing data could not be loaded (' + err.message + ').');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        setTimeout(start, 0);
    }
})();
