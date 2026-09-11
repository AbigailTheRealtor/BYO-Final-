/*
 * Virtual Drive — what a FOR SALE / FOR RENT sign says, where it stands and
 * how big it is. INTERNAL, DEVELOPMENT ONLY.
 *
 * Pure functions: no DOM, no network, no provider. The Google provider uses
 * them to draw geo-anchored signs; the shell uses them for the shopper card and
 * the building chooser; the browser specs call them directly.
 *
 * WHY THE SIZE IS COMPENSATED
 * ---------------------------
 * A Marker in a StreetViewPanorama is scaled by Google with distance, and in the
 * 2026-09-11 live session the rendered width of a 168 px icon followed
 * width ≈ 168 × 19.4 m / distance almost exactly (165 px at 19 m, 91 at 36 m,
 * 67 at 49 m, 25 at 132 m) in a 960 px wide panorama. At 132 m that is a dot.
 *
 * So each sign keeps its geographic anchor — Google still places it at the MLS
 * coordinate and moves it with the camera — and only its documented
 * icon.scaledSize is re-chosen as the camera moves, so that the size a shopper
 * SEES stays between FAR_WIDTH and NEAR_WIDTH. The 19.4 m constant is measured,
 * not documented: if Google's scaling changes, signs drift in size, but they can
 * never leave their house. Beyond MAX_DISTANCE a sign is hidden, not shrunk.
 */
(function (root) {
    'use strict';

    var DEFAULTS = {
        maxDistance: 160,          // m — farther than this, the sign is hidden rather than a dot
        minDistance: 6,            // m — closer than this it would sit on top of the camera
        nearDistance: 20,          // m — at or inside this, a sign is shown at nearWidth
        nearWidth: 200,            // px on screen, the largest a sign appears
        farWidth: 132,             // px on screen, the smallest (still readable, still a big tap target)
        calibrationMeters: 19.4,   // distance at which the provider draws an icon at its own size…
        calibrationViewport: 960,  // …in a panorama this many px wide (measured live, 2026-09-11)
        groupRadius: 8,            // m — listings closer than this are one building
        closeCoverage: 60,         // m — farther than this, imagery is "nearby", not "at the home"
        aspect: 0.62               // sign height / width, pointer included
    };

    function config(overrides) {
        var out = {};

        Object.keys(DEFAULTS).forEach(function (key) {
            var value = overrides && overrides[key];

            out[key] = typeof value === 'number' && isFinite(value) && value > 0 ? value : DEFAULTS[key];
        });

        return out;
    }

    function meters(a, b) {
        var rad = Math.PI / 180;
        var dLat = (b.lat - a.lat) * rad;
        var dLng = (b.lng - a.lng) * rad;
        var h = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(a.lat * rad) * Math.cos(b.lat * rad) * Math.sin(dLng / 2) * Math.sin(dLng / 2);

        return 2 * 6371008.8 * Math.asin(Math.min(1, Math.sqrt(h)));
    }

    // "6590 MANASOTA KEY ROAD" -> "6590". A withheld address has no number, and
    // none is invented.
    function houseNumber(listing) {
        var match = /^\s*(\d+[A-Za-z]?)\b/.exec((listing && listing.address) || '');

        return match ? match[1] : null;
    }

    function unit(listing) {
        var address = (listing && listing.address) || '';
        var match = /#\s*([A-Za-z0-9-]+)\s*$/.exec(address) || /\bUNIT\s+([A-Za-z0-9-]+)/i.exec(address);

        return match ? match[1] : null;
    }

    function tone(list) {
        var sale = list.some(function (l) { return l.transaction_type === 'sale'; });
        var rent = list.some(function (l) { return l.transaction_type === 'rent'; });

        return sale && rent ? 'mixed' : (sale ? 'sale' : 'rent');
    }

    function toneLabel(t) {
        return t === 'mixed' ? 'FOR SALE & RENT' : (t === 'sale' ? 'FOR SALE' : 'FOR RENT');
    }

    // The words on a sign, top to bottom.
    function lines(place) {
        var list = place.listings;

        if (list.length === 1) {
            var one = list[0];
            var number = houseNumber(one);
            var u = unit(one);

            return {
                title: number ? (u ? number + ' #' + u : number) : null,
                label: one.sign_label || toneLabel(tone(list)),
                detail: one.display_price || ''
            };
        }

        var numbers = list.map(houseNumber);
        var shared = numbers[0] && numbers.every(function (n) { return n === numbers[0]; }) ? numbers[0] : null;

        return { title: shared, label: toneLabel(tone(list)), detail: list.length + ' UNITS' };
    }

    // Listings closer together than groupRadius become ONE place — a building —
    // instead of a stack of signs on one point. Deterministic: sorted input,
    // greedy on the first member's coordinate.
    function places(listings, overrides) {
        var cfg = config(overrides);
        var sorted = listings.slice().sort(function (a, b) {
            return a.latitude - b.latitude || a.longitude - b.longitude || (a.id < b.id ? -1 : a.id > b.id ? 1 : 0);
        });
        var out = [];

        sorted.forEach(function (listing) {
            var here = { lat: listing.latitude, lng: listing.longitude };
            var home = null;

            for (var i = 0; i < out.length; i++) {
                if (meters(out[i].anchor, here) <= cfg.groupRadius) {
                    home = out[i];
                    break;
                }
            }

            if (!home) {
                home = { anchor: here, listings: [] };
                out.push(home);
            }

            home.listings.push(listing);
        });

        return out.map(function (p) {
            var lat = 0;
            var lng = 0;

            p.listings.forEach(function (l) { lat += l.latitude; lng += l.longitude; });
            lat /= p.listings.length;
            lng /= p.listings.length;

            var place = {
                id: p.listings.length === 1 ? p.listings[0].id : 'building:' + lat.toFixed(5) + ',' + lng.toFixed(5) + ':' + p.listings.length,
                kind: p.listings.length === 1 ? 'home' : 'building',
                lat: lat,
                lng: lng,
                listings: p.listings,
                tone: tone(p.listings)
            };

            place.lines = lines(place);

            return place;
        });
    }

    // The width a shopper should SEE at this distance, or 0 for "hide it".
    function screenWidth(distance, overrides) {
        var cfg = config(overrides);

        if (!(distance >= cfg.minDistance) || distance > cfg.maxDistance) {
            return 0;
        }

        var t = Math.max(0, Math.min(1, (distance - cfg.nearDistance) / (cfg.maxDistance - cfg.nearDistance)));

        return Math.round(cfg.nearWidth + (cfg.farWidth - cfg.nearWidth) * t);
    }

    // The icon size to hand Google so that, after Google's own distance scaling,
    // the sign appears at screenWidth(). 0 means hidden.
    function iconWidth(distance, viewportWidth, overrides) {
        var cfg = config(overrides);
        var want = screenWidth(distance, cfg);

        if (!want) {
            return 0;
        }

        var k = cfg.calibrationMeters * ((viewportWidth > 0 ? viewportWidth : cfg.calibrationViewport) / cfg.calibrationViewport);

        return Math.min(4000, Math.round(Math.max(cfg.farWidth, want * distance / k)));
    }

    // What Google would draw for an icon of `width` at `distance` — the measured
    // model, used by the specs to check the compensation end to end.
    function modelledScreenWidth(width, distance, viewportWidth, overrides) {
        var cfg = config(overrides);
        var k = cfg.calibrationMeters * ((viewportWidth > 0 ? viewportWidth : cfg.calibrationViewport) / cfg.calibrationViewport);

        return width * k / distance;
    }

    function coverage(gapMeters, overrides) {
        var cfg = config(overrides);
        var gap = Math.round(gapMeters);

        if (gapMeters > cfg.closeCoverage) {
            return {
                near: false,
                message: 'Street View is available nearby, but not directly at this property — the closest imagery is '
                    + gap + ' m away. You are not in front of the home.'
            };
        }

        return { near: true, message: 'Street View imagery is ' + gap + ' m from the home.' };
    }

    root.VirtualDriveSigns = {
        DEFAULTS: DEFAULTS,
        config: config,
        meters: meters,
        houseNumber: houseNumber,
        unit: unit,
        places: places,
        lines: lines,
        screenWidth: screenWidth,
        iconWidth: iconWidth,
        modelledScreenWidth: modelledScreenWidth,
        coverage: coverage
    };
})(window);
