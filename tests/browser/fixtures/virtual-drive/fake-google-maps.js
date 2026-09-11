/*
 |-----------------------------------------------------------------------------
 | Fake Maps JavaScript API — Virtual Drive launch-guard specs only
 |-----------------------------------------------------------------------------
 |
 | Stands in for `google.maps` so the REAL provider code runs with no network
 | and no key. The provider adopts an API that is already on the page instead of
 | loading one, which is what makes this possible without a test-only switch in
 | the code under test.
 |
 | It counts every constructor call ITSELF, independently of the provider's own
 | counters, so a spec compares two witnesses instead of trusting the code under
 | test to report on itself.
 |
 | Query parameters:
 |   ?fake=ok (default) | nocoverage | authfail | constructorThrows
 |   ?delay=<ms>        how long StreetViewService.getPanorama takes (default 60)
 */
(function () {
    'use strict';

    var params = new URLSearchParams(window.location.search);
    var mode = params.get('fake') || 'ok';
    var delay = Number(params.get('delay') || 60);
    var rad = Math.PI / 180;

    var counters = {
        importLibrary: 0,
        panoramaConstructorCalls: 0,
        panoramas: 0,
        markers: 0,
        getPanorama: 0,
        setPano: 0,
        programmaticPov: 0,
        userMoves: 0
    };
    var markers = [];
    var panoramas = [];
    var authFailureSent = false;

    function LatLng(lat, lng) { this._lat = lat; this._lng = lng; }
    LatLng.prototype.lat = function () { return this._lat; };
    LatLng.prototype.lng = function () { return this._lng; };

    function toLatLng(v) {
        if (v instanceof LatLng) {
            return v;
        }

        return new LatLng(typeof v.lat === 'function' ? v.lat() : v.lat, typeof v.lng === 'function' ? v.lng() : v.lng);
    }

    function panoId(ll) { return 'fake-pano:' + ll.lat().toFixed(6) + ',' + ll.lng().toFixed(6); }

    function panoLocation(id) {
        var parts = String(id).replace('fake-pano:', '').split(',');

        return new LatLng(Number(parts[0]), Number(parts[1]));
    }

    function listenable(target) {
        target._listeners = {};
        target.addListener = function (event, fn) {
            (target._listeners[event] = target._listeners[event] || []).push(fn);

            return { remove: function () {} };
        };
        target._emit = function (event) {
            (target._listeners[event] || []).slice().forEach(function (fn) { fn(); });
        };
    }

    function StreetViewPanorama(element, options) {
        counters.panoramaConstructorCalls++;

        if (mode === 'constructorThrows' && counters.panoramaConstructorCalls === 1) {
            throw new Error('fake StreetViewPanorama failure');
        }

        counters.panoramas++;
        listenable(this);
        this._pano = options.pano;
        this._position = panoLocation(options.pano);
        this._pov = options.pov || { heading: 0, pitch: 0 };
        element.setAttribute('data-fake-panorama', String(counters.panoramas));
        panoramas.push(this);

        var self = this;

        setTimeout(function () {
            self._emit('status_changed');
            self._emit('pano_changed');
            self._emit('position_changed');
        }, 0);
    }

    StreetViewPanorama.prototype.getPosition = function () { return this._position; };
    StreetViewPanorama.prototype.getPov = function () { return this._pov; };
    StreetViewPanorama.prototype.getStatus = function () { return 'OK'; };
    StreetViewPanorama.prototype.setPov = function (pov) {
        counters.programmaticPov++;
        this._pov = pov;
        this._emit('pov_changed');
    };
    StreetViewPanorama.prototype.setPano = function (id) {
        counters.setPano++;
        this._pano = id;
        this._position = panoLocation(id);
        this._emit('pano_changed');
        this._emit('position_changed');
    };

    // What a person does with the mouse or a finger. The provider must treat all of
    // it as navigation inside the one panorama — never as a reason to build another.
    StreetViewPanorama.prototype.__userRotate = function (degrees) {
        counters.userMoves++;
        this._pov = { heading: (this._pov.heading + degrees) % 360, pitch: 0 };
        this._emit('pov_changed');
    };
    StreetViewPanorama.prototype.__userWalk = function (dLat, dLng) {
        counters.userMoves++;

        var p = this._position;

        this._position = new LatLng(p.lat() + dLat, p.lng() + dLng);
        this._pano = panoId(this._position);
        this._emit('pano_changed');
        this._emit('position_changed');
    };

    function StreetViewService() {}

    StreetViewService.prototype.getPanorama = function (request, callback) {
        counters.getPanorama++;

        setTimeout(function () {
            if (mode === 'nocoverage') {
                callback(null, 'ZERO_RESULTS');

                return;
            }

            var target = toLatLng(request.location);
            var at = new LatLng(target.lat() + 0.00012, target.lng()); // ~13 m away, "on the street"

            callback({ location: { pano: panoId(at), latLng: at }, imageDate: '2024-03', copyright: '© Fixture' }, 'OK');
        }, delay);
    };

    function Marker(options) {
        counters.markers++;
        listenable(this);
        this._options = options;
        markers.push(this);
    }

    Marker.prototype.setIcon = function (icon) { this._options.icon = icon; };

    function Point(x, y) { this.x = x; this.y = y; }

    var spherical = {
        computeDistanceBetween: function (a, b) {
            a = toLatLng(a);
            b = toLatLng(b);

            var dLat = (b.lat() - a.lat()) * rad;
            var dLng = (b.lng() - a.lng()) * rad;
            var h = Math.pow(Math.sin(dLat / 2), 2)
                + Math.cos(a.lat() * rad) * Math.cos(b.lat() * rad) * Math.pow(Math.sin(dLng / 2), 2);

            return 2 * 6371008.8 * Math.asin(Math.min(1, Math.sqrt(h)));
        },
        computeHeading: function (a, b) {
            a = toLatLng(a);
            b = toLatLng(b);

            var dLng = (b.lng() - a.lng()) * rad;
            var y = Math.sin(dLng) * Math.cos(b.lat() * rad);
            var x = Math.cos(a.lat() * rad) * Math.sin(b.lat() * rad)
                - Math.sin(a.lat() * rad) * Math.cos(b.lat() * rad) * Math.cos(dLng);

            return Math.atan2(y, x) / rad;
        }
    };

    var libraries = {
        streetView: {
            StreetViewPanorama: StreetViewPanorama,
            StreetViewService: StreetViewService,
            StreetViewSource: { DEFAULT: 'default', OUTDOOR: 'outdoor' },
            StreetViewPreference: { NEAREST: 'nearest', BEST: 'best' },
            StreetViewStatus: { OK: 'OK', ZERO_RESULTS: 'ZERO_RESULTS', UNKNOWN_ERROR: 'UNKNOWN_ERROR' }
        },
        marker: { Marker: Marker },
        geometry: { spherical: spherical },
        core: { Point: Point, LatLng: LatLng }
    };

    window.google = {
        maps: {
            importLibrary: function (name) {
                counters.importLibrary++;

                // Google calls gm_authFailure asynchronously after the API loads.
                if (mode === 'authfail' && !authFailureSent) {
                    authFailureSent = true;
                    setTimeout(function () {
                        if (typeof window.gm_authFailure === 'function') {
                            window.gm_authFailure();
                        }
                    }, 0);
                }

                return libraries[name] ? Promise.resolve(libraries[name]) : Promise.reject(new Error('fake: unknown library ' + name));
            }
        }
    };

    window.__fakeGoogle = {
        counters: function () { return Object.assign({}, counters); },
        markers: function () {
            return markers.map(function (m) {
                var p = toLatLng(m._options.position);

                return { title: m._options.title, lat: p.lat(), lng: p.lng(), onPanorama: panoramas.indexOf(m._options.map) >= 0 };
            });
        },
        clickMarker: function (index) { markers[index]._emit('click'); },
        lastPanorama: function () { return panoramas[panoramas.length - 1] || null; }
    };
})();
