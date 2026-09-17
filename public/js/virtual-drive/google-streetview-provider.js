/*
 * Google Street View provider — Maps JavaScript API, documented API only.
 *
 * INTERNAL, DEVELOPMENT ONLY. Loaded only by the Google proof page, and it
 * does nothing at all until the shell's launch() calls load() — which happens
 * only after a deliberate press of "Drive with Google".
 *
 * ONE PANORAMA PER PAGE — A CEILING, NOT A HABIT
 * ----------------------------------------------
 * Dynamic Street View bills per panorama OBJECT instantiated
 * (StreetViewPanorama() or Map.getStreetView()). This file creates at most
 * MAX_PANORAMAS_PER_PAGE (1) for the life of the page, lazily, on the first
 * home that has coverage, and moves it with setPano() from then on: rotating,
 * walking, changing homes, clicking signs, opening the shopper card, choosing a
 * unit in a building and every photo or card action all reuse it.
 * constructPanorama() is the only construction site and it REFUSES a second
 * construction — stopping the page and saying so — rather than performing it.
 * The counters in window.VirtualDriveDiagnostics.google are how a live session
 * and the browser specs see that it held.
 *
 * No hidden panorama, no preload, no prefetch, no retry: the library is
 * requested once (a failed load stays failed), and a rejected key
 * (gm_authFailure) stops the page. No google.maps.Map is created, so there is
 * no Dynamic Maps load either. Libraries requested: streetView, marker,
 * geometry, core. No Places.
 *
 * SIGNS: GEO-ANCHORED, READABLE, ONE PER HOME OR BUILDING
 * -------------------------------------------------------
 * The Street View guide documents overlays on a StreetViewPanorama — "the
 * types of overlays which are supported on Street View panoramas are limited
 * to Markers, InfoWindows and custom OverlayViews" — and it documents the
 * camera: getPosition(), getPov(), and the position_changed / pov_changed /
 * pano_changed events. So each sign is a Marker at the MLS coordinate, and it
 * is Google, not this file, that keeps it on the house as the camera turns and
 * moves.
 *
 * What this file does choose is the sign's documented icon (its drawing and
 * scaledSize) and visibility: as the camera moves, each sign's size is
 * re-fitted so it stays readable and clickable (VirtualDriveSigns explains the
 * measured compensation), its detail level follows its distance — full card,
 * compact card, then a number on a map pin — and a sign beyond useful range is
 * hidden rather than drawn as a dot. Listings
 * that share a building coordinate get ONE building sign ("FOR RENT · 12 UNITS")
 * that opens a unit chooser, never a stack of signs on one point.
 *
 * The legacy google.maps.Marker is used because it is the class the Street
 * View guide names. It is deprecated (February 2024) but Google states it "is
 * not scheduled to be discontinued" and promises at least 12 months' notice;
 * AdvancedMarkerElement is not listed as a Street View overlay.
 */
(function () {
    'use strict';

    var MAX_PANORAMAS_PER_PAGE = 1;
    var Signs = window.VirtualDriveSigns;

    var diagnostics = window.VirtualDriveDiagnostics = window.VirtualDriveDiagnostics || {};
    var diag = diagnostics.google = {
        libraryRequested: 0,          // <script> tags added for the Maps JavaScript API
        adoptedExistingApi: false,    // an API already on the page was used instead
        importLibraryCalls: 0,
        streetViewInitializing: false,
        streetViewInitialized: false,
        panoramaConstructions: 0,     // StreetViewPanorama constructor calls made by this file
        refusedConstructions: 0,      // constructions the ceiling stopped
        getPanoramaRequests: 0,       // StreetViewService lookups (metadata, not a panorama load)
        setPanoCalls: 0,              // moves of the ONE panorama to another home
        authFailed: false,
        authError: null,              // { code, authorized_url, source } — never the key
        consoleMapsMessages: 0,       // Maps JavaScript API messages seen in the console
        signIconUpdates: 0,          // documented setIcon calls (size / selection changes)
        signsShown: 0,                // signs currently within range
        signs: []                     // per sign: distance, intended screen width, icon width
    };

    var loadPromise = null;
    var hooks = null;
    var cfg = {};
    var element = null;
    var lib = null;
    var panorama = null;
    var service = null;
    var places = {};     // place id -> { place, marker, width, visible, selected }
    var listings = {};   // listing id -> listing
    var selectedId = null;
    var generation = 0;
    var pendingTiming = null;
    var requestedHeading = null; // the last heading this file asked setPov() for (diagnostic only)

    function now() {
        return window.performance && performance.now ? performance.now() : Date.now();
    }

    function importLibrary(name) {
        diag.importLibraryCalls++;

        return google.maps.importLibrary(name);
    }

    // ------------------------------------------------------------ a rejected key, in Google's own words
    //
    // gm_authFailure is called with NO argument: it says "rejected" and not why.
    // The why — RefererNotAllowedMapError, ApiTargetBlockedMapError, … and "Your
    // site URL to be authorized: …" — is printed by the Maps JavaScript API to the
    // browser console and nowhere else. So before the library can print anything,
    // console.error and console.warn are wrapped (every message still reaches the
    // console unchanged) and a Maps error is read out of what passes through.
    //
    // Whatever is kept has the key removed: the credential this page was handed,
    // anything shaped like a Google API key, and any key= query parameter.
    //
    // No timer lives here. The shell decides when to report, and reports once.

    // Authorization failures documented in the Maps JavaScript API error-messages
    // reference. A code outside this list is logged, never treated as a rejection.
    var AUTH_ERROR_CODES = [
        'ApiNotActivatedMapError', 'ApiProjectMapError', 'ApiTargetBlockedMapError',
        'BillingNotEnabledMapError', 'DeletedApiProjectMapError', 'ExpiredKeyMapError',
        'InvalidClientIdMapError', 'InvalidKeyMapError', 'MissingKeyMapError', 'OverQuotaMapError',
        'ProjectDeniedMapError', 'RefererDeniedMapError', 'RefererNotAllowedMapError',
        'UnauthorizedURLForClientIdMapError'
    ];

    var authDetail = null;       // { code, message, authorized_url, source }
    var consoleCaptured = false;
    var credentialForRedaction = '';

    function redact(text) {
        var out = String(text);

        if (credentialForRedaction) {
            out = out.split(credentialForRedaction).join('[redacted key]');
        }

        return out
            .replace(/AIza[0-9A-Za-z_\-]{10,}/g, '[redacted key]')
            .replace(/([?&]key=)[^&\s#]+/gi, '$1[redacted]');
    }

    // A Maps JavaScript API error found in one console call, or null.
    function readMapsConsoleMessage(args) {
        var text = Array.prototype.map.call(args, function (arg) {
            if (typeof arg === 'string') {
                return arg;
            }

            return arg && typeof arg.message === 'string' ? arg.message : String(arg);
        }).join(' ');

        var code = /\b([A-Z][A-Za-z]+MapError)\b/.exec(text);

        if (!code && text.indexOf('Google Maps JavaScript API') === -1) {
            return null;
        }

        var authorized = /Your site URL to be authorized:\s*(\S+)/i.exec(text);

        return {
            code: code ? code[1] : null,
            message: redact(text).slice(0, 600),
            authorized_url: authorized ? redact(authorized[1]).slice(0, 300) : null
        };
    }

    function captureMapsConsole() {
        if (consoleCaptured || !window.console) {
            return;
        }

        consoleCaptured = true;

        ['error', 'warn'].forEach(function (level) {
            var original = console[level];

            if (typeof original !== 'function') {
                return;
            }

            console[level] = function () {
                original.apply(console, arguments);

                var found;

                try {
                    found = readMapsConsoleMessage(arguments);
                } catch (e) {
                    found = null;
                }

                if (!found) {
                    return;
                }

                diag.consoleMapsMessages++;

                if (found.code && AUTH_ERROR_CODES.indexOf(found.code) !== -1) {
                    noteAuthFailure('console', found);
                } else if (hooks) {
                    hooks.log('Google console ' + level, found.message);
                }
            };
        });
    }

    // Every signal of a rejection lands here, in whatever order Google sends
    // them. The first one stops the page; later ones only add detail.
    function noteAuthFailure(source, found) {
        var first = authDetail === null;

        authDetail = authDetail || { code: null, message: null, authorized_url: null, source: null };

        if (found) {
            authDetail.code = authDetail.code || found.code;
            authDetail.message = authDetail.message || found.message;
            authDetail.authorized_url = authDetail.authorized_url || found.authorized_url;
        }

        authDetail.source = !authDetail.source || authDetail.source === source
            ? source
            : 'gm_authFailure+console';

        diag.authFailed = true;
        diag.authError = { code: authDetail.code, authorized_url: authDetail.authorized_url, source: authDetail.source };

        if (!hooks) {
            return;
        }

        var copy = {
            code: authDetail.code,
            message: authDetail.message,
            authorized_url: authDetail.authorized_url,
            source: authDetail.source
        };

        if (typeof hooks.authFailure === 'function') {
            hooks.authFailure(copy);
        } else if (first) {
            hooks.fatal('Google rejected the browser key' + (copy.code ? ' (' + copy.code + ')' : '')
                + '. Nothing will be retried.');
        }
    }

    // Latched: one request per page, and a failed load stays failed — no retry
    // against a billed API.
    function loadLibrary(settings) {
        if (loadPromise) {
            return loadPromise;
        }

        credentialForRedaction = settings.credential || '';
        captureMapsConsole();

        loadPromise = new Promise(function (resolve, reject) {
            // Documented hook for a rejected key (referrer or API restriction).
            window.gm_authFailure = function () {
                noteAuthFailure('gm_authFailure', null);
            };

            // An API already on the page is adopted, never loaded a second time —
            // the same rule as the Explore loader.
            if (window.google && window.google.maps && typeof window.google.maps.importLibrary === 'function') {
                diag.adoptedExistingApi = true;
                resolve();

                return;
            }

            window.__virtualDriveGoogleReady = function () { resolve(); };

            var script = document.createElement('script');

            script.src = 'https://maps.googleapis.com/maps/api/js'
                + '?key=' + encodeURIComponent(settings.credential)
                + '&v=' + encodeURIComponent(settings.apiVersion || 'weekly')
                + '&loading=async&callback=__virtualDriveGoogleReady';
            script.async = true;
            script.onerror = function () { reject(new Error('the Maps JavaScript API script could not be loaded')); };
            diag.libraryRequested++;
            document.head.appendChild(script);
        }).then(function () {
            return Promise.all([
                importLibrary('streetView'),
                importLibrary('marker'),
                importLibrary('geometry'),
                importLibrary('core')
            ]);
        }).then(function (libraries) {
            lib = { sv: libraries[0], marker: libraries[1], geometry: libraries[2], core: libraries[3] };
            service = new lib.sv.StreetViewService();
        });

        return loadPromise;
    }

    function latLng(item) {
        return { lat: item.latitude !== undefined ? item.latitude : item.lat, lng: item.longitude !== undefined ? item.longitude : item.lng };
    }

    // The selected sign is always drawn above its neighbours (documented
    // MarkerOptions.zIndex); the stacking order never depends on creation order.
    var Z_SELECTED = 1000;
    var Z_NEIGHBOUR = 1;

    // The marker itself, drawn by VirtualDriveSigns.signDrawing at this sign's
    // detail level: the house number is the largest line, so neighbours never
    // depend on the selection treatment to be told apart.
    function signIcon(place, isSelected, width, level) {
        var drawing = Signs.signDrawing(place, isSelected, level);
        var scale = width / drawing.width;
        var height = Math.round(drawing.height * scale);

        return {
            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(drawing.svg),
            scaledSize: new lib.core.Size(width, height),
            // The drawing's own anchor — the dot under a card, the tip of a pin —
            // is the point Google places on the MLS coordinate.
            anchor: new lib.core.Point(Math.round(drawing.anchorX * scale), Math.round(drawing.anchorY * scale))
        };
    }

    function signTitle(place) {
        var first = place.listings[0];
        var where = first.address || first.city || 'address withheld';

        if (place.kind === 'home') {
            return first.sign_label + ' ' + (first.display_price || '') + ' — ' + where;
        }

        return place.lines.label + ' · ' + place.listings.length + ' units — ' + where;
    }

    function onSignClick(id) {
        var entry = places[id];

        if (!entry) {
            return;
        }

        hooks.count('Sign clicks');

        var list = entry.place.listings;

        // The click carries THIS sign's listing(s) and nothing else.
        if (list.length === 1) {
            hooks.onSelect(list[0].id, 'sign click');
        } else {
            hooks.onChooseBuilding(list.map(function (l) { return l.id; }), 'building sign click');
        }
    }

    // One marker per home or building. Membership changes (a unit arriving from a
    // nearby query) replace that place's marker; the panorama is never touched.
    function syncMarkers() {
        if (!panorama) {
            return;
        }

        var next = Signs.places(Object.keys(listings).map(function (id) { return listings[id]; }), cfg.signs);
        var keep = {};

        next.forEach(function (place) {
            keep[place.id] = true;

            if (places[place.id]) {
                places[place.id].place = place;

                return;
            }

            var id = place.id;
            var marker = new lib.marker.Marker({
                position: latLng(place),
                map: panorama,
                title: signTitle(place),
                visible: false,
                zIndex: Z_NEIGHBOUR
            });

            marker.addListener('click', function () { onSignClick(id); });
            places[id] = { place: place, marker: marker, width: 0, visible: false, selected: null, level: null };
        });

        Object.keys(places).forEach(function (id) {
            if (!keep[id]) {
                places[id].marker.setMap(null);
                delete places[id];
            }
        });

        hooks.setCounter('Markers placed in the panorama', Object.keys(places).length);
        updateSigns();
    }

    // Re-fit every sign to the camera's distance from it: shown and readable
    // within range, hidden beyond it. Only documented Marker setters are used.
    function updateSigns() {
        if (!panorama || !lib) {
            return;
        }

        var here = panorama.getPosition();

        if (!here) {
            return;
        }

        var viewport = element ? element.clientWidth : 0;
        var shown = 0;
        var report = [];

        Object.keys(places).forEach(function (id) {
            var entry = places[id];
            var distance = lib.geometry.spherical.computeDistanceBetween(here, latLng(entry.place));
            var isSelected = entry.place.listings.some(function (l) { return l.id === selectedId; });
            var width = Signs.iconWidth(distance, viewport, cfg.signs, isSelected);
            var level = Signs.detailLevel(distance, cfg.signs, isSelected);

            report.push({
                id: id,
                units: entry.place.listings.length,
                selected: isSelected,
                distance: Math.round(distance),
                screenWidth: Signs.screenWidth(distance, cfg.signs, isSelected),
                iconWidth: width,
                level: level
            });

            if (!width) {
                if (entry.visible) {
                    entry.marker.setVisible(false);
                    entry.visible = false;
                }

                return;
            }

            shown++;

            if (entry.selected !== isSelected) {
                entry.marker.setZIndex(isSelected ? Z_SELECTED : Z_NEIGHBOUR);
            }

            if (!entry.width || Math.abs(width - entry.width) / entry.width > 0.06 || entry.selected !== isSelected || entry.level !== level) {
                entry.marker.setIcon(signIcon(entry.place, isSelected, width, level));
                entry.width = width;
                entry.selected = isSelected;
                entry.level = level;
                diag.signIconUpdates++;
            }

            if (!entry.visible) {
                entry.marker.setVisible(true);
                entry.visible = true;
            }
        });

        diag.signs = report;
        diag.signsShown = shown;
        hooks.setCounter('Signs within range', shown);
    }

    // Where the selected home is relative to the camera — possible only because
    // Google documents the camera's position and heading.
    function relative() {
        if (!panorama || !selectedId || !listings[selectedId]) {
            hooks.onRelative(null);

            return;
        }

        var here = panorama.getPosition();

        if (!here) {
            return;
        }

        var target = latLng(listings[selectedId]);
        var heading = lib.geometry.spherical.computeHeading(here, target);
        var distance = lib.geometry.spherical.computeDistanceBetween(here, target);
        var cameraHeading = panorama.getPov().heading;
        var relativeHeading = ((heading - cameraHeading + 540) % 360) - 180;

        hooks.onRelative({
            distance: distance,
            relativeHeading: relativeHeading,
            // Developer diagnostics only; the shopper readout uses the two above.
            bearing: heading,
            cameraHeading: cameraHeading,
            requestedHeading: requestedHeading
        });
    }

    // Turns the camera; never moves it. The position — and so the distance to
    // the home — is exactly what it was before the press.
    function aimAtSelected() {
        if (!panorama || !selectedId || !listings[selectedId] || !panorama.getPosition()) {
            return;
        }

        requestedHeading = lib.geometry.spherical.computeHeading(panorama.getPosition(), latLng(listings[selectedId]));
        panorama.setPov({ heading: requestedHeading, pitch: 0 });
        hooks.log('Camera aimed at the selected home', selectedId);
    }

    function bind() {
        panorama.addListener('pano_changed', function () {
            hooks.count('pano_changed (moved to another panorama)');

            if (pendingTiming) {
                hooks.fact(pendingTiming.id, 'Google time to imagery', Math.round(now() - pendingTiming.startedAt) + ' ms');
                pendingTiming = null;
            }
        });

        panorama.addListener('position_changed', function () {
            var p = panorama.getPosition();

            if (!p) {
                return;
            }

            hooks.onMoved({ lat: p.lat(), lng: p.lng() });
            relative();
            updateSigns();
        });

        panorama.addListener('pov_changed', function () {
            hooks.count('pov_changed');
            relative();
        });

        panorama.addListener('status_changed', function () {
            var status = panorama.getStatus();

            if (status !== lib.sv.StreetViewStatus.OK) {
                hooks.log('Panorama status', String(status));
            }
        });
    }

    // THE ONLY CONSTRUCTION SITE.
    function constructPanorama(pano, heading) {
        if (panorama) {
            return panorama;
        }

        if (diag.panoramaConstructions >= MAX_PANORAMAS_PER_PAGE) {
            diag.refusedConstructions++;
            hooks.fatal('STOP: a second StreetViewPanorama construction was refused on this page. '
                + 'Stop the live test and report it.');

            throw new Error('a second Street View panorama was refused');
        }

        diag.streetViewInitializing = true;
        diag.panoramaConstructions++;
        hooks.setCounter('StreetViewPanorama constructions', diag.panoramaConstructions);

        try {
            panorama = new lib.sv.StreetViewPanorama(element, {
                pano: pano,
                pov: { heading: heading, pitch: 0 },
                zoom: 0,
                addressControl: false,
                fullscreenControl: false,
                motionTracking: false,
                motionTrackingControl: false,
                enableCloseButton: false,
                linksControl: true,
                panControl: true,
                zoomControl: true,
                clickToGo: true,
                showRoadLabels: true
            });
        } finally {
            diag.streetViewInitializing = false;
        }

        diag.streetViewInitialized = true;
        hooks.log('StreetViewPanorama constructed', 'billable Dynamic Street View load '
            + diag.panoramaConstructions + ' of ' + MAX_PANORAMAS_PER_PAGE + ' allowed on this page');
        bind();
        syncMarkers();

        return panorama;
    }

    // Outdoor Google imagery only — not user-contributed or indoor panoramas.
    function nearestPanorama(target, radius) {
        return new Promise(function (resolve) {
            diag.getPanoramaRequests++;
            hooks.count('StreetViewService.getPanorama lookups');

            service.getPanorama({
                location: target,
                radius: radius,
                sources: [lib.sv.StreetViewSource.OUTDOOR],
                preference: lib.sv.StreetViewPreference.NEAREST
            }, function (data, status) {
                resolve(status === lib.sv.StreetViewStatus.OK ? data : null);
            });
        });
    }

    var provider = {
        id: 'google',
        capabilities: { geoAnchoredMarkers: true, cameraState: true },

        load: function (settings, shellHooks) {
            hooks = shellHooks;
            cfg = settings;
            hooks.log('Loading Maps JavaScript API', 'libraries: streetView, marker, geometry, core');

            return loadLibrary(settings);
        },

        mount: function (el) {
            element = el;

            var aim = document.createElement('button');

            aim.type = 'button';
            aim.className = 'vd-control';
            aim.textContent = 'Face the selected home';
            aim.addEventListener('click', aimAtSelected);
            hooks.addControl(aim);

            // A narrower or wider panorama changes how big Google draws a sign.
            window.addEventListener('resize', updateSigns);
        },

        setListings: function (list) {
            list.forEach(function (listing) {
                listings[listing.id] = listing;
            });

            syncMarkers();
        },

        select: function (listing) {
            selectedId = listing.id;
            listings[listing.id] = listing;
            updateSigns();
            relative();
        },

        show: function (listing) {
            if (diag.authFailed) {
                return Promise.reject(new Error('Google rejected the browser key.'));
            }

            var target = latLng(listing);
            var mine = ++generation;
            var startedAt = now();

            provider.select(listing);

            return nearestPanorama(target, 50).then(function (data) {
                return data || nearestPanorama(target, 150);
            }).then(function (data) {
                // A newer selection overtook this lookup; it must not move the camera.
                if (mine !== generation) {
                    return { superseded: true, coverage: false, note: '' };
                }

                if (diag.authFailed) {
                    throw new Error('Google rejected the browser key.');
                }

                if (!data) {
                    hooks.fact(listing.id, 'Google coverage', 'none within 150 m');

                    return { coverage: false, near: false, note: 'No outdoor Google panorama within 150 m of the MLS coordinate.' };
                }

                var from = data.location.latLng;
                var heading = lib.geometry.spherical.computeHeading(from, target);
                var gap = lib.geometry.spherical.computeDistanceBetween(from, target);
                var coverage = Signs.coverage(gap, cfg.signs);

                hooks.fact(listing.id, 'Google coverage', coverage.near ? 'yes' : 'nearby only');
                hooks.fact(listing.id, 'Google panorama distance from MLS coordinate', Math.round(gap) + ' m');
                hooks.fact(listing.id, 'Google imagery date', data.imageDate || 'not provided');
                pendingTiming = { id: listing.id, startedAt: startedAt };
                requestedHeading = heading;

                if (!panorama) {
                    constructPanorama(data.location.pano, heading);
                } else {
                    diag.setPanoCalls++;
                    panorama.setPano(data.location.pano);
                    panorama.setPov({ heading: heading, pitch: 0 });
                }

                updateSigns();

                // `gap` is measured from the panorama matched NOW, not from wherever
                // the camera goes next. `note` is the labelled developer diagnostic;
                // `customerNote` is what a shopper may see (often nothing). The live
                // distance lives in onRelative, recomputed on position_changed.
                return {
                    coverage: true,
                    near: coverage.near,
                    gap: Math.round(gap),
                    requestedHeading: heading,
                    imageDate: data.imageDate || null,
                    note: coverage.message
                        + ' Requested initial heading ' + Math.round((heading + 360) % 360) + '°.'
                        + (data.imageDate ? ' Imagery captured ' + data.imageDate + '.' : ''),
                    customerNote: coverage.customerMessage
                };
            });
        }
    };

    if (window.VirtualDrive) {
        window.VirtualDrive.register(provider);
    }
})();
