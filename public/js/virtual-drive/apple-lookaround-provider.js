/*
 * Apple Look Around provider — MapKit JS, documented API only.
 *
 * INTERNAL, DEVELOPMENT ONLY. Loaded only by the Apple proof page, and only
 * when VIRTUAL_DRIVE_MAPKIT_JS_TOKEN is configured.
 *
 * THE DOCUMENTED SURFACE THIS FILE USES — AND NOTHING ELSE
 * --------------------------------------------------------
 *   mapkit.init({ authorizationCallback })   mapkit.addEventListener('error')
 *   mapkit.Coordinate                        mapkit.Geocoder (reverseLookup, Place mode only)
 *   mapkit.LookAround(parent, location, options)
 *   lookAround.addEventListener ('load' / 'error' / 'readystatechange')
 *   lookAround.scene   lookAround.readyState   lookAround.destroy()
 * VirtualDriveProviderIsolationTest fails if this file reaches for anything else.
 *
 * WHY THE SIGN CANNOT FOLLOW THE HOUSE
 * ------------------------------------
 * Look Around (MapKit JS 5.79+) documents no camera heading, pitch, position
 * or field of view — not on LookAround, and not on LookAroundScene, whose only
 * documented member is copy(). There is no camera-moved event, no way to set a
 * heading, and no annotation or overlay inside the view. A sign cannot be
 * projected onto a house without knowing where the camera stands and which way
 * it faces, so this provider declares geoAnchoredMarkers: false and the shell
 * draws a screen-fixed sign that says it is screen-fixed.
 *
 * Apple's DTS confirms the orientation gap on the developer forums: "A
 * coordinate is simply a 2D point on Earth, and does not indicate a direction
 * or heading" — in that report Look Around opened at a house's coordinate
 * faced the house across the street. Apple's suggested remedy is a Place, so
 * "Place" mode asks Geocoder.reverseLookup for one (one service call), hands it
 * to LookAround instead, and logs the address Apple resolved so it can be
 * compared with the MLS address.
 *
 * Reading undocumented internals to recover the camera is exactly what this
 * proof is not allowed to do, so it does not.
 *
 * COST SHAPE
 * ----------
 * Free up to 250,000 map views and 25,000 service calls per day per Apple
 * Developer Program membership. MapKit JS has no scene request, so a new home
 * means destroy() and a new LookAround (counted below). Place mode adds one
 * Geocoder service call per home.
 */
(function () {
    'use strict';

    var CALLBACK = '__virtualDriveMapkitReady';
    var loadPromise = null;
    var hooks = null;
    var element = null;
    var lookAround = null;
    var sceneTimer = null;
    var generation = 0;
    var mode = 'coordinate';

    // Latched: one script tag per page, and a failed load stays failed.
    function loadLibrary(cfg) {
        if (loadPromise) {
            return loadPromise;
        }

        loadPromise = new Promise(function (resolve, reject) {
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
            script.setAttribute('data-libraries', 'look-around,services');
            script.onerror = function () { reject(new Error('the MapKit JS script could not be loaded')); };
            document.head.appendChild(script);
        }).then(function () {
            mapkit.addEventListener('error', function (event) {
                hooks.log('MapKit error', String((event && event.status) || 'unknown'));
            });

            mapkit.init({ authorizationCallback: function (done) { done(cfg.credential); } });
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
    // displaying. A change of identity is the only documented signal that the
    // view moved — it says THAT it moved, never WHERE to. Whether it changes on
    // every step is unverified until a credentialed run; the log records it.
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
            hooks.count('LookAround destroyed');
        }
    }

    function placeFor(listing) {
        var geocoder = new mapkit.Geocoder();

        hooks.count('Apple service calls (Geocoder.reverseLookup)');

        return geocoder.reverseLookup(new mapkit.Coordinate(listing.latitude, listing.longitude)).then(function (response) {
            var place = response && response.results && response.results.length ? response.results[0] : null;

            hooks.log('Apple reverse lookup', place
                ? 'Apple resolved "' + (place.formattedAddress || place.name || 'an unnamed place') + '" for MLS "'
                    + (listing.address || 'address withheld') + '"'
                : 'no Place returned — using the MLS coordinate');

            return place;
        }, function (error) {
            hooks.log('Apple reverse lookup failed', (error && error.message) || 'unknown error');

            return null;
        });
    }

    function locationFor(listing) {
        var coordinate = new mapkit.Coordinate(listing.latitude, listing.longitude);

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
            hooks.log('Loading MapKit JS', 'libraries: look-around, services');

            return loadLibrary(cfg);
        },

        mount: function (el) {
            element = el;

            var label = document.createElement('label');
            var select = document.createElement('select');

            label.className = 'vd-control';
            label.appendChild(document.createTextNode('Open Look Around from '));

            [['coordinate', 'the MLS coordinate (no service call)'], ['place', 'an Apple Place (1 reverse-lookup call)']]
                .forEach(function (choice) {
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

            destroyCurrent();

            return locationFor(listing).then(function (location) {
                if (mine !== generation) {
                    return { superseded: true, coverage: false, note: '' };
                }

                return new Promise(function (resolve) {
                    var settled = false;

                    function settle(result) {
                        if (!settled) {
                            settled = true;
                            resolve(mine === generation ? result : { superseded: true, coverage: false, note: '' });
                        }
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
                    hooks.count('LookAround objects constructed');

                    lookAround.addEventListener('readystatechange', function () {
                        if (mine === generation && lookAround) {
                            hooks.log('Look Around readyState', String(lookAround.readyState));
                        }
                    });

                    lookAround.addEventListener('load', function () {
                        if (mine === generation) {
                            watchScene();
                        }

                        settle({
                            coverage: true,
                            note: 'Opened from ' + location.label + '. The starting heading is Apple\'s choice and may not face the home.'
                        });
                    });

                    lookAround.addEventListener('error', function (event) {
                        settle({
                            coverage: false,
                            note: 'Look Around could not open from ' + location.label
                                + (event && event.type ? ' (' + event.type + ' event)' : '') + '.'
                        });
                    });
                });
            });
        }
    };

    if (window.VirtualDrive) {
        window.VirtualDrive.register(provider);
    }
})();
