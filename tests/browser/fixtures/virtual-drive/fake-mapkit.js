/*
 |-----------------------------------------------------------------------------
 | Fake MapKit JS 6 — Virtual Drive launch-guard specs only
 |-----------------------------------------------------------------------------
 |
 | Stands in for `mapkit` so the real Apple provider runs with no network and no
 | token. Like the Google fake it counts its own constructor calls, and it records
 | the exact `location` each LookAround was given, so a spec can prove the view
 | opened from the stored MLS coordinate as plain CoordinateData — with no
 | PlaceLookup and no Geocoder call.
 |
 | It models only what MapKit JS 6 documents for Look Around: `readyState`
 | ('loading' → 'complete' | 'error', and 'destroyed'), an 'error' event carrying
 | a LookAroundErrorEvent-shaped `detail`, `scene`, and `destroy()`.
 |
 | Query parameters: ?fake=ok (default) | unavailable
 */
(function () {
    'use strict';

    var mode = new URLSearchParams(window.location.search).get('fake') || 'ok';
    var counters = { inits: 0, lookArounds: 0, destroys: 0, reverseLookups: 0, placeLookups: 0 };
    var locations = [];

    function Coordinate(latitude, longitude) {
        this.latitude = latitude;
        this.longitude = longitude;
    }

    function Geocoder() {}

    Geocoder.prototype.reverseLookup = function () {
        counters.reverseLookups++;

        return Promise.resolve({ results: [{ formattedAddress: 'Fixture Place, Sarasota, FL' }] });
    };

    function PlaceLookup() {}

    PlaceLookup.prototype.getPlace = function () {
        counters.placeLookups++;

        return Promise.resolve(null);
    };

    function LookAroundScene() {}

    LookAroundScene.prototype.copy = function () { return new LookAroundScene(); };

    function LookAround(parent, location) {
        counters.lookArounds++;
        locations.push(location);

        var self = this;

        this._listeners = {};
        this.scene = null;
        this.readyState = 'loading';

        setTimeout(function () {
            if (self.readyState === 'destroyed') {
                return;
            }

            if (mode === 'unavailable') {
                self.readyState = 'error';
                self._dispatch('error', { type: 'availability-error', message: 'Fixture: no Look Around imagery here.' });

                return;
            }

            self.scene = new LookAroundScene();
            self.readyState = 'complete';
        }, 50);
    }

    LookAround.prototype.addEventListener = function (type, fn) {
        (this._listeners[type] = this._listeners[type] || []).push(fn);
    };
    LookAround.prototype._dispatch = function (type, detail) {
        var self = this;

        (this._listeners[type] || []).slice().forEach(function (fn) { fn({ type: type, target: self, detail: detail }); });
    };
    LookAround.prototype.destroy = function () {
        counters.destroys++;
        this.readyState = 'destroyed';
        this._listeners = {};
    };

    window.mapkit = {
        version: '6.0.0-fixture',
        init: function () { counters.inits++; },
        addEventListener: function () {},
        Coordinate: Coordinate,
        Geocoder: Geocoder,
        PlaceLookup: PlaceLookup,
        LookAround: LookAround,
        LookAroundScene: LookAroundScene
    };

    window.__fakeMapkit = {
        counters: function () { return Object.assign({}, counters); },
        // The shape of the last `location` handed to new LookAround(...).
        lastLocation: function () {
            var loc = locations[locations.length - 1];

            if (!loc) {
                return null;
            }

            return {
                plainObject: Object.getPrototypeOf(loc) === Object.prototype,
                keys: Object.keys(loc).sort(),
                latitude: loc.latitude,
                longitude: loc.longitude
            };
        }
    };
})();
