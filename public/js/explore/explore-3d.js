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

            fetch(endpoints.listings + '?' + params.toString(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then(function (response) {
                    return response.json().then(function (body) {
                        return { ok: response.ok, body: body };
                    });
                })
                .then(function (result) {
                    // The stale-response guard. An older response that arrives
                    // after a newer one is dropped rather than painted.
                    const responseSeq = typeof result.body.seq === 'number' ? result.body.seq : -1;
                    if (responseSeq < state.lastRenderedSeq) return;
                    state.lastRenderedSeq = responseSeq;

                    if (!result.ok) {
                        setStatus(result.body.error || 'Listings are unavailable for this view.');
                        return;
                    }

                    state.listings = result.body.listings || [];
                    renderResults(result.body);
                    renderMarkers();
                })
                .catch(function () {
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

        const count = state.listings.length;
        setStatus(
            count === 0
                ? 'No listings in this view.'
                : count + (payload.truncated ? '+ listings in view' : ' listings in view')
        );

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

    function loadGoogleMaps() {
        return new Promise(function (resolve, reject) {
            const key = shell.dataset.googleKey;
            if (!key) {
                reject(new Error('no browser key'));
                return;
            }

            const params = new URLSearchParams({
                key: key,
                v: shell.dataset.googleVersion || 'alpha',
                libraries: shell.dataset.googleLibraries || 'maps3d',
            });

            const script = document.createElement('script');
            script.src = 'https://maps.googleapis.com/maps/api/js?' + params.toString();
            script.async = true;
            script.onload = resolve;
            script.onerror = function () { reject(new Error('google maps failed to load')); };
            document.head.appendChild(script);
        });
    }

    function buildMap() {
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

        // Camera movement is debounced into one request per gesture, never one
        // per frame. `gmp-centerchange` fires continuously while flying.
        ['gmp-centerchange', 'gmp-rangechange'].forEach(function (event) {
            map.addEventListener(event, function () { requestListings(false); });
        });

        installKeyboardDrive(map);
        installTouchDrive(map);
    }

    /*
     | Drive-like movement, MVP.
     |
     | W/S and the arrow keys move the camera along its own heading; A/D turn it.
     | This is camera movement over the photorealistic world, not vehicle physics
     | and not road-following: snapping to road geometry needs the Roads/Routes
     | APIs, which are out of scope for this phase and documented as Phase 2.
     */
    function installKeyboardDrive(map) {
        const STEP_M = 45;
        const TURN_DEG = 7;

        document.addEventListener('keydown', function (event) {
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
    function installTouchDrive(map) {
        let origin = null;

        els.map.addEventListener('touchstart', function (event) {
            origin = event.touches.length === 1
                ? { x: event.touches[0].clientX, y: event.touches[0].clientY }
                : null;
        }, { passive: true });

        els.map.addEventListener('touchmove', function (event) {
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
