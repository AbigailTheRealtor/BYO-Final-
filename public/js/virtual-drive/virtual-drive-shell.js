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
 * action buttons, the fallback and the instrumentation. A provider owns only
 * the street-level pixels and whatever camera information it chooses to expose.
 * The shell never branches on WHICH provider is loaded — only on the
 * capabilities it declares — so both halves of the comparison get the same UI.
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
 *   show(listing)      -> Promise<{coverage: boolean, note: string}>
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
        provider:       shell.getAttribute('data-provider'),
        credential:     shell.getAttribute('data-credential') || '',
        credentialName: shell.getAttribute('data-credential-name') || '',
        libraryUrl:     shell.getAttribute('data-library-url') || '',
        apiVersion:     shell.getAttribute('data-api-version') || '',
        endpoint:       shell.getAttribute('data-listings-endpoint'),
        nearbyRadius:   parseInt(shell.getAttribute('data-nearby-radius'), 10) || 400
    };

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
        lightbox: null
    };

    function $(id) {
        return document.getElementById(id);
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

        if (state.lastQueryCenter && meters(state.lastQueryCenter, center) < cfg.nearbyRadius * 0.6) {
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

        if (!listing || !state.provider || anchored || state.coverage[listing.id] === false) {
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
            var button = node('button', 'vd-nearby-item', row.listing.sign_label + ' · '
                + (row.listing.display_price || 'price not published') + ' · '
                + (row.listing.address || row.listing.city || 'address withheld')
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
        log('Selected ' + listing.sign_label, id + ' via ' + via);

        renderCard(listing);
        renderSign(listing);
        renderNearby();
        maybeQueryNearby(point(listing), 'selected home');

        if (!state.provider) {
            return;
        }

        if (move) {
            showInProvider(listing);
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

        state.provider.show(listing).then(function (result) {
            // A newer selection overtook this one; its answer is not about coverage.
            if (result.superseded) {
                return;
            }

            state.coverage[listing.id] = result.coverage;
            setCounter('Listings with coverage', Object.keys(state.coverage).filter(function (k) { return state.coverage[k]; }).length);
            setCounter('Listings without coverage', Object.keys(state.coverage).filter(function (k) { return !state.coverage[k]; }).length);
            log(result.coverage ? 'Coverage YES' : 'Coverage NO', listing.id + (result.note ? ' — ' + result.note : ''));

            setImageryStatus(result.coverage ? 'ok' : 'none', result.coverage
                ? result.note
                : 'No street-level imagery for this home. ' + (result.note || '') + ' The listing stays fully available.');

            if (state.selectedId === listing.id) {
                renderCard(listing);
                renderSign(listing);
            }
        }, function (err) {
            log('Provider show failed', err.message);
            setImageryStatus('none', 'Street-level imagery failed: ' + err.message + ' The listing stays fully available.');
        });
    }

    // ------------------------------------------------------------ fallback

    function showFallback(message) {
        var street = $('vd-street');
        var box = node('div', 'vd-fallback');

        street.textContent = '';
        street.classList.add('is-fallback');

        box.appendChild(node('h2', null, 'Virtual Drive unavailable'));
        box.appendChild(node('p', null, message));
        box.appendChild(node('p', 'vd-muted', 'Listings stay fully usable: pick a home and open it normally. '
            + 'In production this state hands over to the ordinary MapLibre map — on its own screen, '
            + 'never beside Street View imagery.'));
        street.appendChild(box);

        $('vd-sign').hidden = true;
        setImageryStatus('none', '');
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
        selectedListing: selected,
        addControl: function (el) { $('vd-provider-controls').appendChild(el); },
        reshow: function () {
            var listing = selected();

            if (listing && state.provider) {
                showInProvider(listing);
            }
        },
        onSelect: function (id, via) {
            selectById(id, via, { move: false });
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
        }
    };

    // ------------------------------------------------------------ start

    function startProvider() {
        var provider = state.registered;
        var first = state.walk[0].id;

        if (!provider) {
            showFallback('No street-level provider registered on this page.');
            selectById(first, 'initial', { move: false });

            return;
        }

        setCounter('Provider', provider.id);

        if (!cfg.credential) {
            log('Provider not loaded', cfg.credentialName + ' is not configured — no request sent to ' + provider.id);
            showFallback('Street-level imagery is unavailable because ' + cfg.credentialName
                + ' is not configured. No request was sent to the provider.');
            selectById(first, 'initial', { move: false });

            return;
        }

        provider.load(cfg, hooks).then(function () {
            provider.mount($('vd-street'));
            state.provider = provider;
            provider.setListings(knownList());
            selectById(first, 'initial');
        }, function (err) {
            log('Provider failed to load', err.message);
            showFallback('The ' + provider.id + ' street-level provider failed to load: ' + err.message);
            selectById(first, 'initial', { move: false });
        });
    }

    function start() {
        $('vd-prev').addEventListener('click', function () { step(-1); });
        $('vd-next').addEventListener('click', function () { step(1); });
        $('vd-sign-cta').addEventListener('click', function () {
            var listing = selected();

            if (listing) {
                log('Sign clicked', 'listing ' + listing.id);
            }

            $('vd-card').scrollIntoView({ behavior: 'smooth' });
        });
        wireLightbox();

        fetchListings({ set: 'test' }).then(function (data) {
            state.walk = data.listings;
            $('vd-attribution').textContent = data.attribution || '';

            if (data.unavailable_keys && data.unavailable_keys.length) {
                log('Test keys not published', data.unavailable_keys.length
                    + ' key(s) missing from stored data or ineligible: ' + data.unavailable_keys.join(', '));
            }

            if (!state.walk.length) {
                showFallback('No eligible test listings exist in the stored MLS data here.');
                $('vd-card-body').textContent = 'No eligible listings.';

                return;
            }

            remember(state.walk);
            startProvider();
        }).catch(function (err) {
            showFallback('Listing data could not be loaded (' + err.message + ').');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        setTimeout(start, 0);
    }
})();
