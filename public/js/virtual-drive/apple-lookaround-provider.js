/*
 * Apple Look Around provider — MapKit JS 6, documented API only.
 *
 * INTERNAL, DEVELOPMENT ONLY. Loaded only by the Apple proof page, and it does
 * nothing at all until the shell's launch() calls load() — which happens only
 * after a deliberate press of "Open Look Around".
 *
 * STARTS FROM THE STORED MLS COORDINATE, WITH NO SERVICE CALL
 * -----------------------------------------------------------
 * MapKit JS 6 declares
 *     constructor(parent?: HTMLElement,
 *                 location?: CoordinateData | Place | LookAroundScene,
 *                 options?: LookAroundOptions)
 * and CoordinateData (new in 6.0) is "a plain object representation of a
 * coordinate" — `{ latitude, longitude }`. So the Stellar coordinate we already
 * store goes straight in: no PlaceLookup, no Geocoder, no service call.
 *
 * "Place" mode survives ONLY as an opt-in experiment, off by default, for one
 * specific reason: Apple's DTS says a coordinate "does not indicate a direction
 * or heading" and names a Place as the way to face the right house. It costs
 * one Geocoder service call per home, and the tester has to choose it.
 *
 * THE DOCUMENTED SURFACE THIS FILE USES — AND NOTHING ELSE
 * --------------------------------------------------------
 *   mapkit.init({ authorizationCallback })   mapkit.addEventListener('error')
 *   mapkit.LookAround(parent, location, options)
 *   mapkit.Geocoder, mapkit.Coordinate       opt-in Place mode only (reverseLookup takes a Coordinate)
 *   lookAround.readyState                    'loading' | 'complete' | 'error' | 'destroyed'
 *   lookAround.addEventListener('error')     LookAroundErrorEvent: detail.type, detail.message
 *   lookAround.scene   lookAround.destroy()
 * VirtualDriveProviderIsolationTest fails if this file reaches for anything else.
 * MapKit JS 6 documents no 'load' or 'readystatechange' event for Look Around, so
 * readiness is read from the documented readyState getter instead.
 *
 * WHY THE SIGN CANNOT FOLLOW THE HOUSE — re-verified against MapKit JS 6.0.128
 * ----------------------------------------------------------------------------
 * - AbstractLookAround declares element, scene, openDialog, readyState,
 *   isNavigationEnabled, isZoomEnabled, isScrollEnabled, showsRoadLabels,
 *   showsPointsOfInterest, padding and destroy(). Nothing else: no heading, no
 *   pitch, no position, no field of view, no way to add an annotation.
 * - LookAroundScene declares one member, copy(). It carries no coordinate,
 *   heading or pitch that a page may read.
 * - Annotations (Annotation, MarkerAnnotation, ImageAnnotation) are added to a
 *   Map, and the only coordinate-to-screen method, convertCoordinateToPointOnPage,
 *   is declared on Map alone.
 * A sign cannot be projected onto a house without the camera's position and
 * heading, so this provider declares geoAnchoredMarkers: false and the shell
 * draws a screen-fixed sign that says it is screen-fixed. Reading undocumented
 * internals to recover the camera is exactly what this proof may not do.
 *
 * COST SHAPE
 * ----------
 * Free up to 250,000 map views and 25,000 service calls per day per Apple
 * Developer Program membership. MapKit JS has no scene request, so a new home
 * means destroy() and a new LookAround (counted below). The default start makes
 * no service call at all.
 */
(function () {
    'use strict';

    var CALLBACK = '__virtualDriveMapkitReady';
    var READY_TIMEOUT_MS = 20000;

    var diagnostics = window.VirtualDriveDiagnostics = window.VirtualDriveDiagnostics || {};
    var diag = diagnostics.apple = {
        libraryRequested: 0,          // <script> tags added for MapKit JS
        adoptedExistingApi: false,    // a MapKit JS already on the page was used instead
        inits: 0,
        lookAroundConstructions: 0,
        destroys: 0,
        serviceCalls: 0               // Geocoder lookups — only ever in opt-in Place mode
    };

    var loadPromise = null;
    var hooks = null;
    var element = null;
    var lookAround = null;
    var sceneTimer = null;
    var generation = 0;
    var mode = 'coordinate';

    function now() {
        return window.performance && performance.now ? performance.now() : Date.now();
    }

    // Latched: one request per page, and a failed load stays failed.
    function loadLibrary(cfg) {
        if (loadPromise) {
            return loadPromise;
        }

        loadPromise = new Promise(function (resolve, reject) {
            // A MapKit JS already on the page is adopted, never loaded a second time.
            if (window.mapkit && typeof window.mapkit.LookAround === 'function') {
                diag.adoptedExistingApi = true;
                resolve();

                return;
            }

            // Called when the libraries finish loading, or with an error when they fail.
            window[CALLBACK] = function (error) {
                if (error) {
                    reject(new Error('MapKit JS could not load its libraries'));

                    return;
                }

                resolve();
            };

            var script = document.createElement('script');

            script.src = cfg.libraryUrl;
            script.crossOrigin = 'anonymous';
            script.async = true;
            script.setAttribute('data-callback', CALLBACK);
            // `services` is loaded only so the opt-in Place mode can work; loading
            // it makes no request of its own.
            script.setAttribute('data-libraries', 'services,look-around');
            script.onerror = function () { reject(new Error('the MapKit JS script could not be loaded')); };
            diag.libraryRequested++;
            document.head.appendChild(script);
        }).then(function () {
            mapkit.addEventListener('error', function (event) {
                var status = String((event && (event.status || (event.detail && event.detail.status))) || 'unknown');

                hooks.log('MapKit error', status);

                if (status === 'Unauthorized') {
                    hooks.fatal('Apple rejected the MapKit JS token (Unauthorized). Nothing will be retried.');
                }
            });

            mapkit.init({ authorizationCallback: function (done) { done(cfg.credential); } });
            diag.inits++;
            hooks.count('mapkit.init');
        });

        return loadPromise;
    }

    function stopWatchingScene() {
        if (sceneTimer) {
            clearInterval(sceneTimer);
            sceneTimer = null;
        }
    }

    // `scene` is documented as the Look Around scene the framework is
    // displaying, and LookAroundScene documents nothing but copy(). A change of
    // identity is therefore the most a page can learn: THAT the view moved,
    // never WHERE to. Whether it changes on every step is for the credentialed
    // run to show; the log records it.
    function watchScene() {
        var baseline = lookAround ? lookAround.scene : null;

        stopWatchingScene();

        sceneTimer = setInterval(function () {
            if (!lookAround) {
                return;
            }

            var current = lookAround.scene;

            if (current && baseline && current !== baseline) {
                hooks.count('Look Around scene reference changed');
                hooks.onSceneChanged();
            }

            baseline = current || baseline;
        }, 750);
    }

    function destroyCurrent() {
        stopWatchingScene();

        if (lookAround) {
            lookAround.destroy();
            lookAround = null;
            diag.destroys++;
            hooks.count('LookAround destroyed');
        }
    }

    // Opt-in only. reverseLookup is declared to take a Coordinate instance.
    function placeFor(listing) {
        var geocoder = new mapkit.Geocoder();

        diag.serviceCalls++;
        hooks.count('Apple service calls (Geocoder.reverseLookup, Place mode)');

        return geocoder.reverseLookup(new mapkit.Coordinate(listing.latitude, listing.longitude)).then(function (response) {
            var place = response && response.results && response.results.length ? response.results[0] : null;
            var resolved = place ? (place.formattedAddress || place.name || 'an unnamed place') : 'no Place returned';

            hooks.fact(listing.id, 'Apple Place resolved for the MLS coordinate', resolved);

            return place;
        }, function (error) {
            hooks.log('Apple reverse lookup failed', (error && error.message) || 'unknown error');

            return null;
        });
    }

    function locationFor(listing) {
        // The default: the stored MLS coordinate itself, as CoordinateData.
        var coordinate = { latitude: listing.latitude, longitude: listing.longitude };

        if (mode !== 'place') {
            return Promise.resolve({ value: coordinate, label: 'the MLS coordinate' });
        }

        return placeFor(listing).then(function (place) {
            return place
                ? { value: place, label: 'an Apple Place (reverse lookup)' }
                : { value: coordinate, label: 'the MLS coordinate (no Place found)' };
        });
    }

    var provider = {
        id: 'apple',
        capabilities: { geoAnchoredMarkers: false, cameraState: false },

        load: function (cfg, shellHooks) {
            hooks = shellHooks;
            hooks.log('Loading MapKit JS 6', 'libraries: services, look-around');

            return loadLibrary(cfg);
        },

        mount: function (el) {
            element = el;

            var label = document.createElement('label');
            var select = document.createElement('select');

            select.id = 'vd-apple-start-mode';
            label.className = 'vd-control';
            label.htmlFor = select.id;
            label.appendChild(document.createTextNode('Open Look Around from '));

            [
                ['coordinate', 'the stored MLS coordinate (no service call)'],
                ['place', 'an Apple Place (opt-in: 1 reverse-lookup call per home)']
            ].forEach(function (choice) {
                var option = document.createElement('option');

                option.value = choice[0];
                option.textContent = choice[1];
                select.appendChild(option);
            });

            select.addEventListener('change', function () {
                mode = select.value;
                hooks.log('Start mode', mode);
                hooks.reshow();
            });

            label.appendChild(select);
            hooks.addControl(label);

            var note = document.createElement('p');

            note.className = 'vd-control vd-control-note';
            note.textContent = 'Aim at the home: not possible — MapKit JS exposes no heading control.';
            hooks.addControl(note);
        },

        // Nothing to draw: Look Around documents no annotations.
        setListings: function () {},
        select: function () {},

        show: function (listing) {
            var mine = ++generation;
            var startedAt = now();
            var superseded = { superseded: true, coverage: false, note: '' };

            destroyCurrent();

            return locationFor(listing).then(function (location) {
                if (mine !== generation) {
                    return superseded;
                }

                hooks.fact(listing.id, 'Apple start location', location.label);

                return new Promise(function (resolve) {
                    var settled = false;
                    var lastState = null;
                    var poll = null;

                    function settle(result) {
                        if (settled) {
                            return;
                        }

                        settled = true;
                        clearInterval(poll);
                        resolve(mine === generation ? result : superseded);
                    }

                    lookAround = new mapkit.LookAround(element, location.value, {
                        openDialog: false,
                        showsDialogControl: false,
                        showsCloseControl: false,
                        isNavigationEnabled: true,
                        isScrollEnabled: true,
                        isZoomEnabled: true,
                        showsPointsOfInterest: false,
                        showsRoadLabels: true
                    });
                    diag.lookAroundConstructions++;
                    hooks.count('LookAround objects constructed');

                    // The documented error event: a LookAroundErrorEvent whose
                    // detail.type says why (availability-error = no imagery here).
                    lookAround.addEventListener('error', function (event) {
                        var detail = (event && event.detail) || {};

                        if (mine === generation) {
                            hooks.fact(listing.id, 'Apple coverage', 'no (' + (detail.type || 'error') + ')');
                        }

                        settle({
                            coverage: false,
                            note: 'Look Around could not open from ' + location.label
                                + (detail.type ? ' (' + detail.type + (detail.message ? ': ' + detail.message : '') + ')' : '') + '.'
                        });
                    });

                    // Readiness from the documented readyState getter — MapKit JS 6
                    // names no load event for Look Around.
                    poll = setInterval(function () {
                        if (mine !== generation || !lookAround) {
                            settle(superseded);

                            return;
                        }

                        var state = String(lookAround.readyState);

                        if (state !== lastState) {
                            lastState = state;
                            hooks.log('Look Around readyState', state);
                        }

                        if (state === 'complete') {
                            watchScene();
                            hooks.fact(listing.id, 'Apple coverage', 'yes');
                            hooks.fact(listing.id, 'Apple time to imagery', Math.round(now() - startedAt) + ' ms');
                            settle({
                                coverage: true,
                                note: 'Opened from ' + location.label + '. The starting heading is Apple\'s choice and may not face the home.'
                            });
                        } else if (state === 'error') {
                            hooks.fact(listing.id, 'Apple coverage', 'no (readyState error)');
                            settle({ coverage: false, note: 'Look Around reported an error from ' + location.label + '.' });
                        } else if (now() - startedAt > READY_TIMEOUT_MS) {
                            hooks.fact(listing.id, 'Apple coverage', 'no answer within 20 s');
                            settle({ coverage: false, note: 'Look Around did not finish loading within 20 seconds.' });
                        }
                    }, 200);
                });
            });
        }
    };

    if (window.VirtualDrive) {
        window.VirtualDrive.register(provider);
    }
})();
