/*
 | BidYourOffer Explore — Google Photorealistic 3D renderer.
 |
 | WHAT THIS LOADS, AND WHAT IT DELIBERATELY DOES NOT
 | --------------------------------------------------
 | The Google Maps JavaScript API with the `maps3d` library only — the
 | photorealistic 3D surface and Map3DElement. The library list comes from
 | server config (config/explore.php) and is not written here, so adding one is
 | a config diff a reviewer sees.
 |
 | NOT loaded, and none of them called: Places, Autocomplete, Routes, Roads,
 | Directions, Geocoding. Marker positions are MLS coordinates, which are
 | authoritative; geocoding every listing through Google would spend money to
 | make Google the source of a property's position, which it must never be.
 |
 | NO KEY, NO REQUEST
 | ------------------
 | When the server reports no browser credential this module renders the results
 | list and never touches maps.googleapis.com. A misconfigured environment issues
 | zero Google requests rather than failing ones.
 |
 | FETCHING IS DEBOUNCED AND SEQUENCED
 | -----------------------------------
 | The camera fires continuously while a user moves. Fetching per frame, per
 | heading degree or per tilt change would turn one gesture into hundreds of
 | requests against a public endpoint. Movement is debounced, and every request
 | carries a monotonically increasing `seq` which the server echoes: a response
 | whose seq is not the newest issued is DISCARDED, because responses do not
 | necessarily return in the order they were sent and an older one landing last
 | repaints the map for a viewport that has moved on.
 |
 | THE SERVER DECIDES WHAT IS SHOWN
 | --------------------------------
 | This file renders what it is given. It performs no eligibility check, no
 | status interpretation and no price labelling — display_price arrives already
 | formatted, because whether 475 is a monthly rent or a purchase price is a
 | question the server answers.
 */

(function () {
    'use strict';

    const shell = document.getElementById('explore-shell');
    if (!shell) return;

    const MOVE_DEBOUNCE_MS = 450;

    const endpoints = {
        listings: shell.dataset.listingsEndpoint,
        listing: shell.dataset.listingEndpoint,
    };

    const googleReady = shell.dataset.googleReady === '1';
    const maxResults = parseInt(shell.dataset.maxResults, 10) || 150;
    const maxSpan = parseFloat(shell.dataset.maxSpan) || 1.0;

    let camera;
    try {
        camera = JSON.parse(shell.dataset.camera || '{}');
    } catch (e) {
        camera = {};
    }

    const state = {
        filter: '',
        seq: 0,
        lastRenderedSeq: -1,
        listings: [],
        markers: new Map(),
        selected: null,
        map3d: null,
        fetchTimer: null,
        discovery: null,

        // Google is loaded at most ONCE per page lifecycle. The promise is the
        // latch: a second caller receives this same promise rather than
        // inserting a second <script>, and a caller arriving after it settles
        // receives the settled one. See loadGoogleMaps().
        googleLoadPromise: null,

        // The 3D world is created at most ONCE. Driving means moving this
        // camera; it never means destroying and recreating the world.
        worldBuilt: false,

        // Drive listeners are bound to document/els.map, which outlive any
        // single map instance, so binding them twice would double every
        // keystroke and touch event.
        driveInstalled: false,

        // The viewport fetch currently in flight, so a newer one can abort it
        // rather than leaving it to land late and be discarded by the seq guard.
        inFlight: null,
    };

    const els = {
        map: document.getElementById('explore-map'),
        panel: document.getElementById('explore-panel'),
        results: document.getElementById('explore-results'),
        status: document.getElementById('explore-status'),
        attribution: document.getElementById('explore-attribution'),
    };

    /* ── filters ─────────────────────────────────────────────────────────── */

    document.querySelectorAll('.explore-filter').forEach(function (button) {
        button.addEventListener('click', function () {
            if (button.dataset.filter === 'intelligence') return;

            document.querySelectorAll('.explore-filter').forEach(function (b) {
                b.classList.remove('is-active');
            });
            button.classList.add('is-active');
            state.filter = button.dataset.filter || '';
            closePanel();
            requestListings(true);
        });
    });

    /* ── viewport ────────────────────────────────────────────────────────── */

    /*
     | The bounding box we ask for.
     |
     | Map3DElement does not expose a viewport rectangle the way a 2D map does,
     | so the box is derived from the camera centre and its range. It is then
     | clamped to the server's own span ceiling BEFORE being sent — not to make
     | the server lenient, but so a tilted, zoomed-out camera asks a question the
     | server will answer instead of collecting a 422 on every pan. The server
     | still enforces its own limit; this is a client courtesy, never the rule.
     */
    function currentBbox() {
        const centre = state.map3d && state.map3d.center
            ? state.map3d.center
            : { lat: camera.latitude, lng: camera.longitude };

        const range = (state.map3d && state.map3d.range) || camera.range || 2500;

        // Degrees covered by the visible ground radius, with headroom for tilt.
        let halfLat = Math.min((range / 111320) * 1.6, maxSpan / 2);
        const cosLat = Math.max(Math.cos((centre.lat * Math.PI) / 180), 0.1);
        let halfLng = Math.min(halfLat / cosLat, maxSpan / 2);

        halfLat = Math.max(halfLat, 0.0008);
        halfLng = Math.max(halfLng, 0.0008);

        return [
            (centre.lat - halfLat).toFixed(6),
            (centre.lng - halfLng).toFixed(6),
            (centre.lat + halfLat).toFixed(6),
            (centre.lng + halfLng).toFixed(6),
        ].join(',');
    }

    function requestListings(immediate) {
        window.clearTimeout(state.fetchTimer);

        const run = function () {
            const seq = ++state.seq;
            const params = new URLSearchParams({
                bbox: currentBbox(),
                limit: String(maxResults),
                seq: String(seq),
            });

            if (state.filter) params.set('transaction_type', state.filter);

            setStatus('Loading listings…');

            // Abort the previous viewport fetch rather than letting it land.
            //
            // The `seq` guard already stops a stale response REPAINTING the
            // map, but a discarded response has still been served — and on a
            // cold tile that means the server already spent provider requests
            // answering a question nobody is asking any more. Aborting turns a
            // rapid pan into one useful request instead of a queue of them.
            //
            // AbortController is native and universally available in every
            // browser that can render Map3DElement; nothing is added for it.
            if (state.inFlight) {
                state.inFlight.abort();
            }

            const controller = typeof AbortController === 'function' ? new AbortController() : null;
            state.inFlight = controller;

            fetch(endpoints.listings + '?' + params.toString(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller ? controller.signal : undefined,
            })
                .then(function (response) {
                    return response.json().then(function (body) {
                        return { ok: response.ok, body: body };
                    });
                })
                .then(function (result) {
                    if (state.inFlight === controller) state.inFlight = null;

                    // The stale-response guard, kept even though aborts now
                    // remove most of what it caught. Abort is best-effort — a
                    // response already in the socket buffer still arrives — and
                    // this is the half that cannot be raced.
                    const responseSeq = typeof result.body.seq === 'number' ? result.body.seq : -1;
                    if (responseSeq < state.lastRenderedSeq) return;
                    state.lastRenderedSeq = responseSeq;

                    if (!result.ok) {
                        setStatus(result.body.error || 'Listings are unavailable for this view.');
                        return;
                    }

                    state.listings = result.body.listings || [];
                    state.discovery = result.body.discovery || null;
                    renderResults(result.body);
                    renderMarkers();
                })
                .catch(function (error) {
                    // An abort is this module cancelling its own request, not a
                    // failure. Reporting it would tell the user something broke
                    // every time they moved the camera.
                    if (error && error.name === 'AbortError') return;

                    if (state.inFlight === controller) state.inFlight = null;
                    setStatus('Listings could not be loaded.');
                });
        };

        if (immediate) run();
        else state.fetchTimer = window.setTimeout(run, MOVE_DEBOUNCE_MS);
    }

    /* ── rendering ───────────────────────────────────────────────────────── */

    function setStatus(text) {
        if (els.status) els.status.textContent = text;
    }

    function priceLabel(listing) {
        return listing.display_price || 'Price on request';
    }

    function typeLabel(listing) {
        return listing.transaction_type === 'rent' ? 'For Rent' : 'For Sale';
    }

    function renderResults(payload) {
        if (els.attribution) els.attribution.textContent = payload.attribution || '';

        setStatus(inventoryStatusLine(payload));

        if (!els.results) return;

        els.results.innerHTML = '';

        state.listings.forEach(function (listing) {
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'explore-card explore-card-' + listing.transaction_type;
            card.dataset.id = listing.id;

            const thumb = listing.primary_thumbnail
                ? '<img class="explore-card-thumb" src="' + escapeAttr(listing.primary_thumbnail) + '" alt="" loading="lazy">'
                : '<span class="explore-card-thumb explore-card-thumb-empty"></span>';

            card.innerHTML =
                thumb +
                '<span class="explore-card-body">' +
                '<span class="explore-card-type">' + typeLabel(listing) + '</span>' +
                '<span class="explore-card-price">' + escapeHtml(priceLabel(listing)) + '</span>' +
                '<span class="explore-card-meta">' + escapeHtml(metaLine(listing)) + '</span>' +
                '</span>';

            card.addEventListener('click', function () {
                selectListing(listing.id);
            });

            els.results.appendChild(card);
        });
    }

    /*
     | What the count actually means.
     |
     | "No listings in this view" is a claim about a neighbourhood. It is only
     | true when the server confirmed the whole viewport against the provider.
     | When discovery is off, truncated, or the provider was unreachable, an
     | empty or thin result is a fact about our data and must not be worded as a
     | fact about the market — the server sends `discovery.complete` and
     | `discovery.degraded` precisely so this distinction survives to the screen.
     */
    function inventoryStatusLine(payload) {
        const count = state.listings.length;
        const discovery = payload.discovery || {};

        if (discovery.degraded) {
            // "Temporarily unavailable" and never "no homes here". A ceiling we
            // chose to impose, and a provider we could not reach, are both
            // facts about US — stating either as a fact about the market would
            // be a false claim about somebody's neighbourhood.
            return count === 0
                ? 'Listings are temporarily unavailable. Please try again shortly.'
                : 'Showing last known listings — live MLS data is temporarily unavailable.';
        }

        if (count === 0) {
            return discovery.complete
                ? 'No listings in this view.'
                : 'No listings loaded for this view yet.';
        }

        const suffix = payload.truncated ? '+ listings in view' : ' listings in view';

        return count + suffix + (discovery.complete ? '' : ' (partial)');
    }

    function metaLine(listing) {
        const parts = [];
        if (listing.beds != null) parts.push(listing.beds + ' bd');
        if (listing.baths != null) parts.push(listing.baths + ' ba');
        if (listing.living_area != null) parts.push(listing.living_area.toLocaleString() + ' sf');
        // The address is absent when the MLS forbids displaying it. The listing
        // still shows; only the street line is withheld.
        if (listing.address) parts.push(listing.address);
        else if (listing.city) parts.push(listing.city);
        return parts.join(' · ');
    }

    /* ── markers ─────────────────────────────────────────────────────────── */

    function renderMarkers() {
        if (!state.map3d) return;

        state.markers.forEach(function (marker) {
            if (marker.parentNode) marker.parentNode.removeChild(marker);
        });
        state.markers.clear();

        const Marker3D = window.google
            && window.google.maps
            && window.google.maps.maps3d
            && window.google.maps.maps3d.Marker3DInteractiveElement;

        if (!Marker3D) return;

        state.listings.forEach(function (listing) {
            const marker = new Marker3D({
                position: { lat: listing.latitude, lng: listing.longitude, altitude: 12 },
                altitudeMode: 'RELATIVE_TO_GROUND',
                extruded: true,
                // Sale and rent must never be indistinguishable marker types.
                label: typeLabel(listing) + ' · ' + priceLabel(listing),
            });

            marker.addEventListener('gmp-click', function () {
                selectListing(listing.id);
            });

            state.map3d.appendChild(marker);
            state.markers.set(listing.id, marker);
        });
    }

    /* ── property panel ──────────────────────────────────────────────────── */

    function selectListing(id) {
        const listing = state.listings.find(function (l) { return l.id === id; });
        if (!listing) return;

        state.selected = id;
        flyTo(listing);

        // Re-fetched rather than reused: the panel is a second publication of
        // the same record and the server re-decides eligibility for it.
        fetch(endpoints.listing + '/' + encodeURIComponent(id), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (payload) {
                if (payload && payload.listing) renderPanel(payload.listing, payload.attribution);
                else renderPanel(listing, '');
            })
            .catch(function () { renderPanel(listing, ''); });
    }

    function flyTo(listing) {
        if (!state.map3d) return;

        state.map3d.flyCameraTo({
            endCamera: {
                center: { lat: listing.latitude, lng: listing.longitude, altitude: 40 },
                tilt: 67.5,
                range: 320,
            },
            durationMillis: 1600,
        });
    }

    function renderPanel(listing, attribution) {
        if (!els.panel) return;

        const actions = [];

        // Media priority: Tour, then Video, then Photos. Google Photorealistic
        // 3D is the exterior world and is never offered as an interior tour.
        if (listing.has_virtual_tour) {
            actions.push(link(listing.virtual_tour_url, 'Tour Property', 'primary'));
        } else if (listing.has_video) {
            actions.push(link(listing.video_url, 'Watch Video', 'primary'));
        } else if (listing.has_photos) {
            actions.push('<button type="button" class="explore-action explore-action-primary" data-action="photos">Photos</button>');
        }

        // Only actions that actually exist are rendered. No dead buttons: a
        // showing can be requested only against a real BidYourOffer listing,
        // and full MLS details only by a signed-in visitor.
        if (listing.canonical_url) {
            actions.push(link(listing.canonical_url, 'View Full Listing', 'secondary'));
        } else if (listing.detail_url) {
            actions.push(link(listing.detail_url, 'View Property Details', 'secondary'));
        }

        if (listing.showing_available && listing.canonical_url) {
            actions.push(link(listing.canonical_url + '#showing', 'Schedule Showing', 'secondary'));
        }

        els.panel.innerHTML =
            '<button type="button" class="explore-panel-close" aria-label="Close">&times;</button>' +
            (listing.primary_thumbnail
                ? '<img class="explore-panel-image" src="' + escapeAttr(listing.primary_thumbnail) + '" alt="">'
                : '') +
            '<div class="explore-panel-body">' +
            '<div class="explore-panel-type explore-panel-type-' + listing.transaction_type + '">' + typeLabel(listing) + '</div>' +
            '<div class="explore-panel-price">' + escapeHtml(priceLabel(listing)) + '</div>' +
            (listing.address ? '<div class="explore-panel-address">' + escapeHtml(listing.address) + '</div>' : '') +
            '<div class="explore-panel-locality">' + escapeHtml([listing.city, listing.state, listing.postal_code].filter(Boolean).join(', ')) + '</div>' +
            '<div class="explore-panel-facts">' + escapeHtml(metaLine(listing)) + '</div>' +
            '<div class="explore-panel-status">' + escapeHtml(listing.effective_status || '') +
            (listing.property_type ? ' · ' + escapeHtml(listing.property_type) : '') + '</div>' +
            '<div class="explore-panel-actions">' + actions.join('') + '</div>' +
            '<p class="explore-panel-attribution">' + escapeHtml(attribution || listing.attribution || '') + '</p>' +
            '</div>';

        els.panel.hidden = false;

        const close = els.panel.querySelector('.explore-panel-close');
        if (close) close.addEventListener('click', closePanel);

        const photos = els.panel.querySelector('[data-action="photos"]');
        if (photos) photos.addEventListener('click', function () { renderGallery(listing); });
    }

    function renderGallery(listing) {
        const target = els.panel.querySelector('.explore-panel-actions');
        if (!target || !listing.photo_urls || !listing.photo_urls.length) return;

        const strip = document.createElement('div');
        strip.className = 'explore-panel-gallery';
        listing.photo_urls.forEach(function (url) {
            const img = document.createElement('img');
            img.src = url;
            img.loading = 'lazy';
            img.alt = '';
            strip.appendChild(img);
        });
        target.insertAdjacentElement('afterend', strip);
    }

    function closePanel() {
        state.selected = null;
        if (els.panel) {
            els.panel.hidden = true;
            els.panel.innerHTML = '';
        }
    }

    function link(href, label, variant) {
        if (!href) return '';
        return '<a class="explore-action explore-action-' + variant + '" href="' + escapeAttr(href) + '">' + escapeHtml(label) + '</a>';
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/"/g, '&quot;');
    }

    /* ── Google Photorealistic 3D ────────────────────────────────────────── */

    const GOOGLE_SCRIPT_PREFIX = 'https://maps.googleapis.com/maps/api/js';

    /*
     | Load the Google Maps JavaScript API — AT MOST ONCE, EVER.
     |
     | WHY THE LATCH IS THE IMPORTANT PART
     | -----------------------------------
     | This is a billed provider reached from a browser, where a server-side
     | budget cannot see it, let alone stop it. The protection has to be that a
     | second load is structurally impossible rather than merely unlikely — a
     | rendering path nobody expected to run twice is exactly how an earlier
     | integration in this application produced roughly 16,000 unexpected
     | requests.
     |
     | Three callers, three correct answers, no second <script>:
     |   · first caller            → creates the promise, inserts the script
     |   · caller while pending    → receives the SAME promise and waits
     |   · caller after settled    → receives the settled promise immediately,
     |                               resolved or rejected as it actually went
     |
     | A failed load stays failed. The rejected promise is deliberately NOT
     | cleared, so a retry cannot happen by accident — a caller that re-enters
     | after a failure gets the rejection back rather than a fresh attempt. That
     | is the difference between a stated unavailable state and a loop that
     | reloads a paid script until somebody notices the bill.
     |
     | The DOM is checked as well as the promise. The promise alone would be
     | enough within this module, but a script tag inserted by anything else on
     | the page — a future partial, a copy of this file loaded twice — is a real
     | second load, and adopting it costs nothing to check.
     */
    function loadGoogleMaps() {
        if (state.googleLoadPromise) return state.googleLoadPromise;

        state.googleLoadPromise = new Promise(function (resolve, reject) {
            const key = shell.dataset.googleKey;
            if (!key) {
                // No credential means no request. Not "ask and fail" — a
                // malformed request to a billed endpoint is still a request.
                reject(new Error('no browser key'));
                return;
            }

            // Already present? Adopt it rather than adding a second.
            const existing = document.querySelector('script[src^="' + GOOGLE_SCRIPT_PREFIX + '"]');
            if (existing) {
                if (window.google && window.google.maps) { resolve(); return; }
                existing.addEventListener('load', function () { resolve(); });
                existing.addEventListener('error', function () { reject(new Error('google maps failed to load')); });
                return;
            }

            const params = new URLSearchParams({
                key: key,
                v: shell.dataset.googleVersion || 'alpha',
                // From server config, never written here — see ExploreGoogleConfig.
                // Only `maps3d`. No Places, no Geocoding, no Routes, no Roads.
                libraries: shell.dataset.googleLibraries || 'maps3d',
            });

            const script = document.createElement('script');
            script.src = GOOGLE_SCRIPT_PREFIX + '?' + params.toString();
            script.async = true;
            script.dataset.exploreGoogleLoader = '1';
            script.onload = function () { resolve(); };
            script.onerror = function () { reject(new Error('google maps failed to load')); };
            document.head.appendChild(script);
        });

        return state.googleLoadPromise;
    }

    /*
     | Create the ONE 3D world for this page lifecycle.
     |
     | Driving around means moving this camera. It must never mean destroy-world
     | / create-world on every movement: each Map3DElement is a live tile
     | consumer, and a leaked one keeps consuming while an orphan of it sits
     | detached in memory. The latch is checked before the element is created
     | rather than after, so a second call cannot even briefly exist.
     |
     | Markers are a separate layer with their own lifecycle — renderMarkers()
     | updates them against this world and never touches the world itself.
     */
    function buildMap() {
        if (state.worldBuilt) return;

        const Map3D = window.google
            && window.google.maps
            && window.google.maps.maps3d
            && window.google.maps.maps3d.Map3DElement;

        if (!Map3D) throw new Error('maps3d unavailable');

        const map = new Map3D({
            center: { lat: camera.latitude, lng: camera.longitude, altitude: camera.altitude || 0 },
            range: camera.range || 2500,
            tilt: camera.tilt || 67.5,
            heading: camera.heading || 0,
            mode: 'HYBRID',
        });

        if (shell.dataset.googleMapId) map.mapId = shell.dataset.googleMapId;

        map.style.width = '100%';
        map.style.height = '100%';
        els.map.appendChild(map);
        state.map3d = map;
        state.worldBuilt = true;

        // Camera movement is debounced into one request per gesture, never one
        // per frame. `gmp-centerchange` fires continuously while flying.
        ['gmp-centerchange', 'gmp-rangechange'].forEach(function (event) {
            map.addEventListener(event, function () { requestListings(false); });
        });

        // Drive listeners bind to `document` and to the map CONTAINER, both of
        // which outlive any single world, so binding them twice would double
        // every keystroke and every touch move — and each of those calls
        // requestListings(). Bound once, for the life of the page.
        if (! state.driveInstalled) {
            installKeyboardDrive();
            installTouchDrive();
            state.driveInstalled = true;
        }
    }

    /*
     | Drive-like movement, MVP.
     |
     | W/S and the arrow keys move the camera along its own heading; A/D turn it.
     | This is camera movement over the photorealistic world, not vehicle physics
     | and not road-following: snapping to road geometry needs the Roads/Routes
     | APIs, which are out of scope for this phase and documented as Phase 2.
     */
    function installKeyboardDrive() {
        const STEP_M = 45;
        const TURN_DEG = 7;

        document.addEventListener('keydown', function (event) {
            // The live world, read per event rather than captured once. These
            // listeners outlive any single Map3DElement, and a captured
            // reference to a detached one would keep it alive and consuming.
            const map = state.map3d;
            if (!map) return;

            if (event.target && /^(INPUT|TEXTAREA|SELECT)$/.test(event.target.tagName)) return;

            const key = event.key.toLowerCase();
            let forward = 0;
            let turn = 0;

            if (key === 'w' || key === 'arrowup') forward = 1;
            else if (key === 's' || key === 'arrowdown') forward = -1;
            else if (key === 'a' || key === 'arrowleft') turn = -1;
            else if (key === 'd' || key === 'arrowright') turn = 1;
            else return;

            event.preventDefault();

            if (turn !== 0) {
                map.heading = ((map.heading || 0) + turn * TURN_DEG + 360) % 360;
                return;
            }

            const heading = ((map.heading || 0) * Math.PI) / 180;
            const centre = map.center || { lat: camera.latitude, lng: camera.longitude, altitude: 0 };
            const dLat = (Math.cos(heading) * STEP_M * forward) / 111320;
            const cosLat = Math.max(Math.cos((centre.lat * Math.PI) / 180), 0.1);
            const dLng = (Math.sin(heading) * STEP_M * forward) / (111320 * cosLat);

            map.center = { lat: centre.lat + dLat, lng: centre.lng + dLng, altitude: centre.altitude || 0 };
            requestListings(false);
        });
    }

    /*
     | Mobile: a one-finger drag turns and moves. Map3DElement handles pinch and
     | two-finger tilt itself, so only the drive gesture is added.
     */
    function installTouchDrive() {
        let origin = null;

        els.map.addEventListener('touchstart', function (event) {
            origin = event.touches.length === 1
                ? { x: event.touches[0].clientX, y: event.touches[0].clientY }
                : null;
        }, { passive: true });

        els.map.addEventListener('touchmove', function (event) {
            const map = state.map3d;
            if (!map) return;

            if (!origin || event.touches.length !== 1) return;

            const dx = event.touches[0].clientX - origin.x;
            const dy = event.touches[0].clientY - origin.y;

            if (Math.abs(dx) < 6 && Math.abs(dy) < 6) return;

            map.heading = ((map.heading || 0) + dx * 0.12 + 360) % 360;

            const heading = ((map.heading || 0) * Math.PI) / 180;
            const centre = map.center || { lat: camera.latitude, lng: camera.longitude, altitude: 0 };
            const step = -dy * 0.6;
            const cosLat = Math.max(Math.cos((centre.lat * Math.PI) / 180), 0.1);

            map.center = {
                lat: centre.lat + (Math.cos(heading) * step) / 111320,
                lng: centre.lng + (Math.sin(heading) * step) / (111320 * cosLat),
                altitude: centre.altitude || 0,
            };

            origin = { x: event.touches[0].clientX, y: event.touches[0].clientY };
            requestListings(false);
        }, { passive: true });

        els.map.addEventListener('touchend', function () { origin = null; }, { passive: true });
    }

    /* ── boot ────────────────────────────────────────────────────────────── */

    // Listings are fetched whether or not the map renders. An environment with
    // no map credential still shows real inventory in the results list rather
    // than an empty page.
    requestListings(true);

    /*
     | Google is touched from HERE AND NOWHERE ELSE.
     |
     | One entry point, one time, behind the server's own readiness answer —
     | which is false when the renderer is switched off AND when no browser
     | credential exists. Either way no <script> is inserted and
     | maps.googleapis.com is never contacted: the expensive provider stays
     | untouched when it is disabled, rather than being loaded and then hidden.
     |
     | A failure sets a stated unavailable message and STOPS. There is no timer,
     | no backoff, no automatic second attempt: an automatic retry against a
     | billed script is how a broken page becomes a recurring charge, and a
     | human who reloads is a bounded retry with a person behind it.
     */
    if (googleReady) {
        loadGoogleMaps()
            .then(function () {
                buildMap();
                renderMarkers();
            })
            .catch(function () {
                setStatus('The 3D map could not be started. Listings are still shown below.');
            });
    }
})();
