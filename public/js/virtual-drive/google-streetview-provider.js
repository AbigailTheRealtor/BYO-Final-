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
 * What this file does choose is the sign's documented icon.scaledSize and
 * visibility: as the camera moves, each sign's size is re-fitted so it stays
 * readable and clickable (VirtualDriveSigns explains the measured compensation),
 * and a sign beyond useful range is hidden rather than drawn as a dot. Listings
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
        signIconUpdates: 0,           // documented setIcon calls (size / selection changes)
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

    function now() {
        return window.performance && performance.now ? performance.now() : Date.now();
    }

    function importLibrary(name) {
        diag.importLibraryCalls++;

        return google.maps.importLibrary(name);
    }

    // Latched: one request per page, and a failed load stays failed — no retry
    // against a billed API.
    function loadLibrary(settings) {
        if (loadPromise) {
            return loadPromise;
        }

        loadPromise = new Promise(function (resolve, reject) {
            // Documented hook for a rejected key (referrer or API restriction).
            window.gm_authFailure = function () {
                diag.authFailed = true;
                hooks.fatal('Google rejected the browser key (referrer or API restriction). Nothing will be retried.');
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

    function escapeXml(value) {
        return String(value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;' }[c];
        });
    }

    function svgText(y, size, weight, value) {
        return '<text x="100" y="' + y + '" font-family="Arial,Helvetica,sans-serif" font-size="' + size + '" font-weight="' + weight
            + '" fill="#ffffff" text-anchor="middle">' + escapeXml(value) + '</text>';
    }

    // The sign itself. Its words identify the property (house number, FOR SALE /
    // FOR RENT, price — or, for a building, the unit count), so neighbours never
    // depend on the selection outline to be told apart.
    function signIcon(place, isSelected, width) {
        var fill = place.tone === 'rent' ? '#1d4ed8' : (place.tone === 'sale' ? '#b91c1c' : '#6d28d9');
        var l = place.lines;
        var titleSize = l.title && l.title.length > 8 ? 24 : 30;
        var body = l.title
            ? svgText(36, titleSize, 800, l.title) + svgText(62, 18, 700, l.label) + svgText(88, 20, 700, l.detail)
            : svgText(44, 22, 800, l.label) + svgText(78, 22, 700, l.detail);
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="124" viewBox="0 0 200 124">'
            + '<rect x="4" y="4" width="192" height="94" rx="12" fill="' + fill + '" stroke="'
            + (isSelected ? '#facc15' : '#ffffff') + '" stroke-width="' + (isSelected ? 8 : 4) + '"/>'
            + body
            + '<path d="M86 97 L100 122 L114 97 Z" fill="' + fill + '"/>'
            + '</svg>';
        var height = Math.round(width * 124 / 200);

        return {
            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
            scaledSize: new lib.core.Size(width, height),
            anchor: new lib.core.Point(width / 2, height)
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
                visible: false
            });

            marker.addListener('click', function () { onSignClick(id); });
            places[id] = { place: place, marker: marker, width: 0, visible: false, selected: null };
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
            var width = Signs.iconWidth(distance, viewport, cfg.signs);
            var isSelected = entry.place.listings.some(function (l) { return l.id === selectedId; });

            report.push({
                id: id,
                units: entry.place.listings.length,
                distance: Math.round(distance),
                screenWidth: Signs.screenWidth(distance, cfg.signs),
                iconWidth: width
            });

            if (!width) {
                if (entry.visible) {
                    entry.marker.setVisible(false);
                    entry.visible = false;
                }

                return;
            }

            shown++;

            if (!entry.width || Math.abs(width - entry.width) / entry.width > 0.06 || entry.selected !== isSelected) {
                entry.marker.setIcon(signIcon(entry.place, isSelected, width));
                entry.width = width;
                entry.selected = isSelected;
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
        var relativeHeading = ((heading - panorama.getPov().heading + 540) % 360) - 180;

        hooks.onRelative({ distance: distance, relativeHeading: relativeHeading });
    }

    function aimAtSelected() {
        if (!panorama || !selectedId || !listings[selectedId] || !panorama.getPosition()) {
            return;
        }

        panorama.setPov({
            heading: lib.geometry.spherical.computeHeading(panorama.getPosition(), latLng(listings[selectedId])),
            pitch: 0
        });
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

                if (!panorama) {
                    constructPanorama(data.location.pano, heading);
                } else {
                    diag.setPanoCalls++;
                    panorama.setPano(data.location.pano);
                    panorama.setPov({ heading: heading, pitch: 0 });
                }

                updateSigns();

                return {
                    coverage: true,
                    near: coverage.near,
                    gap: Math.round(gap),
                    note: coverage.message + ' Camera turned to heading ' + Math.round((heading + 360) % 360) + '° to face it.'
                        + (data.imageDate ? ' Imagery captured ' + data.imageDate + '.' : '')
                };
            });
        }
    };

    if (window.VirtualDrive) {
        window.VirtualDrive.register(provider);
    }
})();
