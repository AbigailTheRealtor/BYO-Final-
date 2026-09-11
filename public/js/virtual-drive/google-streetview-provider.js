/*
 * Google Street View provider — Maps JavaScript API, documented API only.
 *
 * INTERNAL, DEVELOPMENT ONLY. Loaded only by the Google proof page, and only
 * when VIRTUAL_DRIVE_GOOGLE_MAPS_BROWSER_KEY is configured.
 *
 * WHY THIS PROVIDER CAN PIN A SIGN TO A HOUSE
 * -------------------------------------------
 * The Street View guide documents overlays on a StreetViewPanorama — "the
 * types of overlays which are supported on Street View panoramas are limited
 * to Markers, InfoWindows and custom OverlayViews" — and it documents the
 * camera: getPosition(), getPov(), and the position_changed / pov_changed /
 * pano_changed events. So each sign is a Marker placed at the listing's MLS
 * coordinate, and it is Google, not this file, that keeps it there as the
 * camera turns and moves. This file never repositions a sign.
 *
 * The legacy google.maps.Marker is used because it is the class the Street
 * View guide names. It is deprecated (February 2024) but Google states it "is
 * not scheduled to be discontinued" and promises at least 12 months' notice;
 * AdvancedMarkerElement is not listed as a Street View overlay. A production
 * build must re-check both before depending on them.
 *
 * COST SHAPE
 * ----------
 * Dynamic Street View bills per panorama OBJECT instantiated
 * (StreetViewPanorama() or Map.getStreetView()). This file creates exactly one
 * per page — lazily, on the first home that has coverage — and moves it with
 * setPano() afterwards; walking the street inside it creates no new object.
 * No google.maps.Map is created, so there is no Dynamic Maps load either.
 * Libraries requested: streetView, marker, geometry, core. No Places.
 */
(function () {
    'use strict';

    var loadPromise = null;
    var hooks = null;
    var element = null;
    var lib = null;
    var panorama = null;
    var service = null;
    var markers = {};    // listing id -> Marker
    var listings = {};   // listing id -> listing
    var selectedId = null;
    var generation = 0;

    // Latched: one script tag per page, and a failed load stays failed — no retry
    // against a billed API.
    function loadLibrary(cfg) {
        if (loadPromise) {
            return loadPromise;
        }

        loadPromise = new Promise(function (resolve, reject) {
            window.__virtualDriveGoogleReady = function () { resolve(); };

            // Documented hook for a rejected key (referrer or API restriction).
            window.gm_authFailure = function () {
                hooks.log('gm_authFailure', 'Google rejected the browser key (referrer or API restriction)');
            };

            var script = document.createElement('script');

            script.src = 'https://maps.googleapis.com/maps/api/js'
                + '?key=' + encodeURIComponent(cfg.credential)
                + '&v=' + encodeURIComponent(cfg.apiVersion || 'weekly')
                + '&loading=async&callback=__virtualDriveGoogleReady';
            script.async = true;
            script.onerror = function () { reject(new Error('the Maps JavaScript API script could not be loaded')); };
            document.head.appendChild(script);
        }).then(function () {
            return Promise.all([
                google.maps.importLibrary('streetView'),
                google.maps.importLibrary('marker'),
                google.maps.importLibrary('geometry'),
                google.maps.importLibrary('core')
            ]);
        }).then(function (libraries) {
            lib = { sv: libraries[0], marker: libraries[1], geometry: libraries[2], core: libraries[3] };
            service = new lib.sv.StreetViewService();
        });

        return loadPromise;
    }

    function latLng(listing) {
        return { lat: listing.latitude, lng: listing.longitude };
    }

    function escapeXml(value) {
        return String(value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;' }[c];
        });
    }

    function signIcon(listing, isSelected) {
        var fill = listing.transaction_type === 'rent' ? '#1d4ed8' : '#b91c1c';
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="168" height="74" viewBox="0 0 168 74">'
            + '<rect x="3" y="3" width="162" height="50" rx="9" fill="' + fill + '" stroke="'
            + (isSelected ? '#facc15' : '#ffffff') + '" stroke-width="' + (isSelected ? 5 : 2) + '"/>'
            + '<text x="84" y="24" font-family="Arial,Helvetica,sans-serif" font-size="16" font-weight="700"'
            + ' fill="#ffffff" text-anchor="middle">' + escapeXml(listing.sign_label) + '</text>'
            + '<text x="84" y="44" font-family="Arial,Helvetica,sans-serif" font-size="14"'
            + ' fill="#ffffff" text-anchor="middle">' + escapeXml(listing.display_price || '') + '</text>'
            + '<path d="M74 53 L84 72 L94 53 Z" fill="' + fill + '"/>'
            + '</svg>';

        return {
            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
            anchor: new lib.core.Point(84, 72)
        };
    }

    function syncMarkers() {
        if (!panorama) {
            return;
        }

        Object.keys(listings).forEach(function (id) {
            if (markers[id]) {
                return;
            }

            var listing = listings[id];
            var marker = new lib.marker.Marker({
                position: latLng(listing),
                map: panorama,
                title: listing.sign_label + ' ' + (listing.display_price || '') + ' — '
                    + (listing.address || listing.city || 'address withheld'),
                icon: signIcon(listing, id === selectedId)
            });

            // The click carries THIS marker's listing id and nothing else.
            marker.addListener('click', function () {
                hooks.count('Marker clicks');
                hooks.onSelect(id, 'marker click');
            });

            markers[id] = marker;
        });

        hooks.setCounter('Markers placed in the panorama', Object.keys(markers).length);
    }

    function highlight(id) {
        Object.keys(markers).forEach(function (markerId) {
            markers[markerId].setIcon(signIcon(listings[markerId], markerId === id));
        });
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
        });

        panorama.addListener('position_changed', function () {
            var p = panorama.getPosition();

            if (!p) {
                return;
            }

            hooks.onMoved({ lat: p.lat(), lng: p.lng() });
            relative();
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

    // Outdoor Google imagery only — not user-contributed or indoor panoramas.
    function nearestPanorama(target, radius) {
        return new Promise(function (resolve) {
            hooks.count('StreetViewService.getPanorama requests');

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

        load: function (cfg, shellHooks) {
            hooks = shellHooks;
            hooks.log('Loading Maps JavaScript API', 'libraries: streetView, marker, geometry, core');

            return loadLibrary(cfg);
        },

        mount: function (el) {
            element = el;

            var aim = document.createElement('button');

            aim.type = 'button';
            aim.className = 'vd-control';
            aim.textContent = 'Face the selected home';
            aim.addEventListener('click', aimAtSelected);
            hooks.addControl(aim);
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
            highlight(listing.id);
            relative();
        },

        show: function (listing) {
            var target = latLng(listing);
            var mine = ++generation;

            provider.select(listing);

            return nearestPanorama(target, 50).then(function (data) {
                return data || nearestPanorama(target, 150);
            }).then(function (data) {
                // A newer selection overtook this lookup; it must not move the camera.
                if (mine !== generation) {
                    return { superseded: true, coverage: false, note: '' };
                }

                if (!data) {
                    return { coverage: false, note: 'No outdoor Google panorama within 150 m of the MLS coordinate.' };
                }

                var from = data.location.latLng;
                var heading = lib.geometry.spherical.computeHeading(from, target);
                var gap = lib.geometry.spherical.computeDistanceBetween(from, target);

                if (!panorama) {
                    panorama = new lib.sv.StreetViewPanorama(element, {
                        pano: data.location.pano,
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
                    hooks.count('StreetViewPanorama instantiated (billable: Dynamic Street View)');
                    bind();
                    syncMarkers();
                } else {
                    panorama.setPano(data.location.pano);
                    panorama.setPov({ heading: heading, pitch: 0 });
                }

                highlight(listing.id);

                return {
                    coverage: true,
                    note: 'Nearest outdoor panorama is ' + Math.round(gap) + ' m from the MLS coordinate; camera turned to heading '
                        + Math.round((heading + 360) % 360) + '° to face the home.'
                };
            });
        }
    };

    if (window.VirtualDrive) {
        window.VirtualDrive.register(provider);
    }
})();
