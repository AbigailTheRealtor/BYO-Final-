/*
 |-----------------------------------------------------------------------------
 | Fake MapKit JS — Virtual Drive launch-guard specs only
 |-----------------------------------------------------------------------------
 |
 | Stands in for `mapkit` so the real Apple provider runs with no network and no
 | token. Like the Google fake it counts its own constructor calls, so a spec
 | compares two witnesses.
 */
(function () {
    'use strict';

    var counters = { inits: 0, lookArounds: 0, destroys: 0, reverseLookups: 0 };

    function Coordinate(latitude, longitude) {
        this.latitude = latitude;
        this.longitude = longitude;
    }

    function Geocoder() {}

    Geocoder.prototype.reverseLookup = function () {
        counters.reverseLookups++;

        return Promise.resolve({ results: [{ formattedAddress: 'Fixture Place, Sarasota, FL' }] });
    };

    function LookAround() {
        counters.lookArounds++;

        var self = this;

        this._listeners = {};
        this.scene = { fixtureScene: counters.lookArounds };
        this.readyState = 'loading';

        setTimeout(function () {
            self.readyState = 'complete';
            self._emit('readystatechange');
            self._emit('load');
        }, 50);
    }

    LookAround.prototype.addEventListener = function (type, fn) {
        (this._listeners[type] = this._listeners[type] || []).push(fn);
    };
    LookAround.prototype._emit = function (type) {
        var self = this;

        (this._listeners[type] || []).slice().forEach(function (fn) { fn({ type: type, target: self }); });
    };
    LookAround.prototype.destroy = function () {
        counters.destroys++;
        this._listeners = {};
    };

    window.mapkit = {
        init: function () { counters.inits++; },
        addEventListener: function () {},
        Coordinate: Coordinate,
        Geocoder: Geocoder,
        LookAround: LookAround
    };

    window.__fakeMapkit = { counters: function () { return Object.assign({}, counters); } };
})();
