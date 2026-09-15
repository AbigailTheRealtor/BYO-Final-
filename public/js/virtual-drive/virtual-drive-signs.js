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
 * icon.scaledSize is re-chosen as the camera moves. The 19.4 m constant is
 * measured, not documented: if Google's scaling changes, signs drift in size,
 * but they can never leave their house. Beyond MAX_DISTANCE a sign is hidden.
 *
 * WHY IT IS ONLY PARTLY COMPENSATED
 * ---------------------------------
 * The first version cancelled almost all of that shrinkage: from 13 m to 205 m
 * a real board shrinks ~16×, and the sign shrank 1.5× (and the selected one
 * held a flat 165 px from 160 m out). In the 2026-09-15 live session a sign
 * that barely changed size while its house receded read as a HUD riding along
 * with the camera, although its coordinate never moved. Depth is the cue that
 * says "attached to that house", so the target width now follows a softened
 * perspective curve (curveWidth): it shrinks all the way out, more gently than
 * a physical board so a sign at shopping distance stays readable.
 *
 * WHAT IT LOOKS LIKE: A PROPERTY-INFORMATION OVERLAY, NOT A YARD SIGN
 * -------------------------------------------------------------------
 * The white board on a post imitated a physical sign. The marker is now openly
 * an AR overlay: a dark translucent callout floating above the house, a thin
 * leader line down to a ring on the MLS coordinate. The house number is the
 * largest thing on it, because telling 6580 from 6590 while driving is the job.
 * As the marker shrinks it sheds detail rather than shrinking every line — see
 * detailLevel() and signDrawing().
 */
(function (root) {
    'use strict';

    var DEFAULTS = {
        maxDistance: 160,          // m — farther than this, the sign is hidden rather than a dot
        minDistance: 6,            // m — closer than this it would sit on top of the camera
        // The perspective curve passes through (nearDistance, nearWidth) and
        // (maxDistance, farWidth), and keeps shrinking on both sides of them.
        nearDistance: 20,          // m
        nearWidth: 200,            // px on screen at nearDistance
        farWidth: 68,              // px on screen at maxDistance, where a neighbour is hidden
        perspectiveSoftening: 15,  // m — larger is gentler up close; 0 would be a pure power law
        calibrationMeters: 19.4,   // distance at which the provider draws an icon at its own size…
        calibrationViewport: 960,  // …in a panorama this many px wide (measured live, 2026-09-11)
        groupRadius: 8,            // m — listings closer than this are one building
        closeCoverage: 60,         // m — farther than this, imagery is "nearby", not "at the home"
        // The SELECTED home. The shopper chose it, so it is larger than its
        // neighbours, stays up to a longer range, and never vanishes because the
        // camera is standing close to it. Its size still follows the same
        // near→far curve, so it shrinks with distance like a sign on that house.
        selectedScale: 1.25,       // × a neighbour's on-screen width at the same distance
        selectedMaxDistance: 180,  // m — the selected sign is hidden only beyond this (was 220)
        // DETAIL LEVELS. Each level spends the same width budget on less, so the
        // house number stays large as the marker shrinks. Upper bounds, inclusive:
        //   neighbour  Full ≤ fullDistance < Compact ≤ compactDistance < Pin ≤ maxDistance
        //   selected   Full ≤ selectedFullDistance < Compact ≤ selectedCompactDistance
        //              < Pin ≤ selectedMaxDistance
        fullDistance: 50,
        compactDistance: 110,
        selectedFullDistance: 110,
        selectedCompactDistance: 160,
        // The smallest icon handed to the provider. Only reached very close in,
        // where the provider's own scaling enlarges it. It used to be the far
        // width (132), which drew a sign 6–13 m away up to ~2× its target; 48 px
        // lets a close sign land on target down to ~4 m while keeping enough
        // pixels that an enlarged icon is not a smear.
        minIconWidth: 48
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

    /*
     * The softened perspective curve, before visibility and selection:
     *
     *   width(d) = nearWidth × ((nearDistance + s) / (d + s)) ^ p
     *
     * with s = perspectiveSoftening and p chosen so that width(maxDistance) =
     * farWidth. Strictly decreasing for every d ≥ 0, with no plateau at either
     * end. A physical board is p = 1, s = 0; the defaults give p ≈ 0.67, s = 15 m.
     */
    function perspectiveExponent(cfg) {
        var span = Math.log((cfg.maxDistance + cfg.perspectiveSoftening) / (cfg.nearDistance + cfg.perspectiveSoftening));
        var shrink = Math.log(cfg.nearWidth / cfg.farWidth);

        return span > 0 && shrink > 0 ? shrink / span : 1;
    }

    function curveWidth(distance, overrides) {
        var cfg = config(overrides);
        var d = Math.max(0, distance);

        return cfg.nearWidth * Math.pow((cfg.nearDistance + cfg.perspectiveSoftening) / (d + cfg.perspectiveSoftening), perspectiveExponent(cfg));
    }

    // The width a shopper should SEE at this distance, or 0 for "hide it".
    //
    // A neighbour: the curve, shown from minDistance to maxDistance.
    // The selected home: the curve × selectedScale, shown from the camera's feet
    // out to selectedMaxDistance — and still shrinking all the way there.
    function screenWidth(distance, overrides, selected) {
        var cfg = config(overrides);
        var limit = selected ? cfg.selectedMaxDistance : cfg.maxDistance;

        if (!(distance >= 0) || distance > limit || (!selected && distance < cfg.minDistance)) {
            return 0;
        }

        var width = curveWidth(distance, cfg);

        return Math.round(selected ? width * cfg.selectedScale : width);
    }

    // The icon size to hand the provider so that, after its own distance
    // scaling, the sign appears at screenWidth(). 0 means hidden.
    function iconWidth(distance, viewportWidth, overrides, selected) {
        var cfg = config(overrides);
        var want = screenWidth(distance, cfg, selected);

        if (!want) {
            return 0;
        }

        var k = cfg.calibrationMeters * ((viewportWidth > 0 ? viewportWidth : cfg.calibrationViewport) / cfg.calibrationViewport);

        return Math.min(4000, Math.round(Math.max(cfg.minIconWidth, want * distance / k)));
    }

    // What Google would draw for an icon of `width` at `distance` — the measured
    // model, used by the specs to check the compensation end to end.
    function modelledScreenWidth(width, distance, viewportWidth, overrides) {
        var cfg = config(overrides);
        var k = cfg.calibrationMeters * ((viewportWidth > 0 ? viewportWidth : cfg.calibrationViewport) / cfg.calibrationViewport);

        return width * k / distance;
    }

    // What the FIRST panorama lookup for a home found, worded for two audiences.
    //
    // `gapMeters` is the distance from the panorama Google matched to the MLS
    // coordinate at the moment the home was opened. It is a snapshot: it is not
    // where the camera is now, and walking does not change it. So:
    //   • `message` is the developer diagnostic, labelled as the initial match so
    //     it can never be read as the live distance;
    //   • `customerMessage` carries no number at all. A near match says nothing
    //     (the live "Selected home: X m away" readout already answers it), and a
    //     far match keeps the honest warning without a figure that goes stale.
    function coverage(gapMeters, overrides) {
        var cfg = config(overrides);
        var gap = Math.round(gapMeters);
        var initial = 'Initial Street View match: ' + gap + ' m from the selected listing\'s MLS coordinate.';

        if (gapMeters > cfg.closeCoverage) {
            return {
                near: false,
                message: initial + ' Nearby only — not directly at this property.',
                customerMessage: 'Street View is nearby, but this view is not directly in front of the property.'
            };
        }

        return { near: true, message: initial, customerMessage: '' };
    }

    // Which drawing a sign uses at this distance: 'full', 'compact', 'pin', or
    // null when it is hidden. Hidden is decided by screenWidth(), so a level and a
    // width can never disagree about whether a sign is shown.
    function detailLevel(distance, overrides, selected) {
        var cfg = config(overrides);

        if (!screenWidth(distance, cfg, selected)) {
            return null;
        }

        if (selected) {
            return distance <= cfg.selectedFullDistance ? 'full' : (distance <= cfg.selectedCompactDistance ? 'compact' : 'pin');
        }

        return distance <= cfg.fullDistance ? 'full' : (distance <= cfg.compactDistance ? 'compact' : 'pin');
    }

    /*
     * THE MARKER ITSELF — an AR property-information overlay.
     *
     *   FULL (near)                COMPACT (mid)             PIN (far)
     *
     *     [ SELECTED ]               [ SELECTED ]
     *   ╭──────────────╮           ╭──────────────╮          ╭──────────╮
     *   │  ( FOR RENT )│ chip      │  ( FOR RENT )│          │   6590   │ number only
     *   │     6590     │ LARGEST   │     6590     │          ╰────┬─────╯
     *   │  $14,000/mo  │ price     │  $14,000/mo  │ selected      ●  map pin; its tip
     *   ╰──────┬───────╯           ╰──────┬───────╯ only          ▼  is the anchor
     *          ┆ leader                   ┆
     *          ◉ ring + dot = anchor      ◉
     *
     * A dark translucent card with a glowing edge in the listing's colour reads
     * over bright sky and dark trees alike; the selected home's edge, leader,
     * ring and pin turn yellow, and full/compact add a small SELECTED tab. The
     * house number is the largest type at every level — enforced, not hoped for:
     * a long unit number shrinks to fit and every other line stays below it.
     * Nothing here imitates a physical board: no post, no white face, no tip.
     *
     * Every drawing is MARKER.width units wide. The provider scales it to
     * iconWidth() and anchors it at (anchorX, anchorY): the dot on the MLS
     * coordinate for a card, the pin's tip for a pin.
     */
    var MARKER = {
        width: 200,
        selectedColour: '#facc15',
        full:    { cardTop: 24, tabHeight: 22, tabText: 12, chipHeight: 28, chipText: 17, numberMax: 62, priceMax: 23, leader: 46, ring: 10 },
        compact: { cardTop: 26, tabHeight: 24, tabText: 14, chipHeight: 30, chipText: 18, numberMax: 72, priceMax: 26, leader: 30, ring: 10 },
        pin:     { pillTop: 6, pillHeight: 96, numberMax: 78, pinWidth: 52 }
    };

    var TONES = { sale: '#dc2626', rent: '#2563eb', mixed: '#7c3aed' };

    function escapeXml(value) {
        return String(value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;' }[c];
        });
    }

    // Approximate advance width of bold Arial, in em.
    function textEm(text) {
        return String(text).split('').reduce(function (em, ch) {
            if (/[0-9$#]/.test(ch)) { return em + 0.556; }
            if (ch === ' ') { return em + 0.278; }
            if (/[.,:;'!|\/()\-]/.test(ch)) { return em + 0.333; }
            if (/[A-Z&%@]/.test(ch)) { return em + 0.722; }

            return em + 0.611;
        }, 0);
    }

    // The largest size up to `max` at which `text` fits `room` units, never below `min`.
    function fitSize(text, max, room, min) {
        var em = Math.max(0.5, textEm(text));

        return Math.max(min, Math.min(max, Math.floor(room / em)));
    }

    function svgText(part, y, size, weight, fill, value, extra) {
        return '<text data-part="' + part + '" x="100" y="' + round1(y) + '" font-family="Arial,Helvetica,sans-serif" font-size="' + size
            + '" font-weight="' + weight + '" fill="' + fill + '" text-anchor="middle"' + (extra || '') + '>' + escapeXml(value) + '</text>';
    }

    function round1(n) {
        return Math.round(n * 10) / 10;
    }

    // Soft glow without filters: two wide translucent strokes under the edge.
    function glowRect(part, x, y, w, h, rx, fill, fillOpacity, edge, edgeWidth) {
        return '<rect x="' + x + '" y="' + round1(y) + '" width="' + w + '" height="' + round1(h) + '" rx="' + rx + '" fill="none" stroke="' + edge + '" stroke-opacity="0.2" stroke-width="12"/>'
            + '<rect x="' + x + '" y="' + round1(y) + '" width="' + w + '" height="' + round1(h) + '" rx="' + rx + '" fill="none" stroke="' + edge + '" stroke-opacity="0.4" stroke-width="6"/>'
            + '<rect data-part="' + part + '" x="' + x + '" y="' + round1(y) + '" width="' + w + '" height="' + round1(h) + '" rx="' + rx + '" fill="' + fill
            + '" fill-opacity="' + fillOpacity + '" stroke="' + edge + '" stroke-width="' + edgeWidth + '"/>';
    }

    // The big line is the house number; with none (a withheld address, or a
    // building whose units differ) the price or unit count takes its place —
    // smaller than a number would be, and never an invented number.
    function headline(place) {
        var l = place.lines;

        return l.title
            ? { text: l.title, isNumber: true, sub: l.detail || '' }
            : { text: l.detail || '', isNumber: false, sub: '' };
    }

    function cardDrawing(place, selected, level) {
        var m = MARKER[level];
        var tone = TONES[place.tone] || TONES.mixed;
        var edge = selected ? MARKER.selectedColour : tone;
        var head = headline(place);
        var cardX = 8;
        var cardW = MARKER.width - cardX * 2;
        var room = cardW - 24;
        var headSize = fitSize(head.text, head.isNumber ? m.numberMax : Math.round(m.numberMax * 0.62), room, 20);
        var chipSize = Math.min(fitSize(place.lines.label, m.chipText, room - 24, 11), headSize - 4);
        // Compact keeps the price only for the selected home: a neighbour at that
        // range needs its number, not a second line too small to read.
        var sub = head.sub && (level === 'full' || selected) ? head.sub : '';
        var subSize = sub ? Math.min(fitSize(sub, m.priceMax, room, 12), headSize - 6) : 0;

        var chipY = m.cardTop + 12;
        var chipW = Math.min(room, Math.round(textEm(place.lines.label) * chipSize * 1.08 + 26));
        var headBaseline = chipY + m.chipHeight + 10 + headSize * 0.716;
        var subBaseline = sub ? headBaseline + 12 + subSize * 0.716 : headBaseline;
        var cardBottom = subBaseline + 14;
        var ringY = cardBottom + m.leader + m.ring;
        var height = Math.ceil(ringY + m.ring + 3);
        var leaderColour = selected ? MARKER.selectedColour : '#ffffff';
        var parts = [];

        parts.push('<svg xmlns="http://www.w3.org/2000/svg" data-level="' + level + '" data-selected="' + (selected ? '1' : '0')
            + '" width="' + MARKER.width + '" height="' + height + '" viewBox="0 0 ' + MARKER.width + ' ' + height + '">');
        parts.push('<defs><linearGradient id="leader" x1="0" y1="0" x2="0" y2="1">'
            + '<stop offset="0" stop-color="' + leaderColour + '" stop-opacity="0.95"/>'
            + '<stop offset="1" stop-color="' + leaderColour + '" stop-opacity="0.3"/></linearGradient></defs>');

        // Leader: a thin fading line from the card to the ring, on a faint dark
        // hairline so it survives a bright sky.
        parts.push('<rect x="97.5" y="' + round1(cardBottom) + '" width="5" height="' + round1(ringY - cardBottom) + '" fill="#000000" fill-opacity="0.22"/>');
        parts.push('<rect data-part="leader" x="98.5" y="' + round1(cardBottom) + '" width="3" height="' + round1(ringY - cardBottom) + '" fill="url(#leader)"/>');

        // Anchor: a ring in the listing's colour (yellow when selected) around a white dot.
        parts.push('<circle data-part="anchor-ring" cx="100" cy="' + round1(ringY) + '" r="' + m.ring + '" fill="#000000" fill-opacity="0.3" stroke="' + edge + '" stroke-width="3"/>');
        parts.push('<circle data-part="anchor-dot" cx="100" cy="' + round1(ringY) + '" r="4" fill="#ffffff" stroke="#111827" stroke-width="1.5"/>');

        parts.push(glowRect('card', cardX, m.cardTop, cardW, cardBottom - m.cardTop, 14, '#0b1220', 0.82, edge, selected ? 4 : 2.5));

        if (selected) {
            parts.push('<rect data-part="tab" x="' + (100 - 44) + '" y="1" width="88" height="' + (m.cardTop - 1 + 4) + '" rx="6" fill="' + MARKER.selectedColour + '"/>');
            parts.push(svgText('tab-text', 1 + m.tabHeight / 2 + m.tabText * 0.36, m.tabText, 900, '#111827', 'SELECTED', ' letter-spacing="1"'));
        }

        parts.push('<rect data-part="chip" x="' + round1(100 - chipW / 2) + '" y="' + chipY + '" width="' + chipW + '" height="' + m.chipHeight
            + '" rx="' + m.chipHeight / 2 + '" fill="' + tone + '"/>');
        parts.push(svgText('status', chipY + m.chipHeight / 2 + chipSize * 0.36, chipSize, 800, '#ffffff', place.lines.label, ' letter-spacing="1"'));
        parts.push(svgText(head.isNumber ? 'number' : 'headline', headBaseline, headSize, 800, '#ffffff', head.text));

        if (sub) {
            parts.push(svgText('price', subBaseline, subSize, 700, '#e5e7eb', sub));
        }

        parts.push('</svg>');

        return { svg: parts.join(''), level: level, width: MARKER.width, height: height, anchorX: 100, anchorY: round1(ringY) };
    }

    function pinDrawing(place, selected) {
        var m = MARKER.pin;
        var tone = TONES[place.tone] || TONES.mixed;
        var edge = selected ? MARKER.selectedColour : tone;
        var head = headline(place);
        var size = fitSize(head.text, head.isNumber ? m.numberMax : Math.round(m.numberMax * 0.62), 170, 20);
        var pillBottom = m.pillTop + m.pillHeight;

        // A map pin under the number: the head sits just below the pill, the tip
        // is the MLS coordinate.
        var s = m.pinWidth / 24;
        var top = pillBottom + 6;
        var r = 10.4 * s;
        var cy = top + 11.5 * s;
        var tipY = round1(top + 30 * s);
        var height = Math.ceil(tipY + 3);
        var parts = [];

        parts.push('<svg xmlns="http://www.w3.org/2000/svg" data-level="pin" data-selected="' + (selected ? '1' : '0')
            + '" width="' + MARKER.width + '" height="' + height + '" viewBox="0 0 ' + MARKER.width + ' ' + height + '">');
        parts.push('<path data-part="pin" d="M100 ' + tipY + ' C100 ' + tipY + ' ' + round1(100 - r) + ' ' + round1(cy + 6.9 * s) + ' ' + round1(100 - r) + ' ' + round1(cy)
            + ' A' + round1(r) + ' ' + round1(r) + ' 0 0 1 ' + round1(100 + r) + ' ' + round1(cy)
            + ' C' + round1(100 + r) + ' ' + round1(cy + 6.9 * s) + ' 100 ' + tipY + ' 100 ' + tipY + ' Z" fill="' + edge
            + '" stroke="' + (selected ? '#111827' : '#ffffff') + '" stroke-width="3" stroke-linejoin="round"/>');
        parts.push('<circle data-part="pin-eye" cx="100" cy="' + round1(cy) + '" r="' + round1(4.1 * s) + '" fill="' + (selected ? '#111827' : '#ffffff') + '"/>');
        parts.push(glowRect('pill', 7, m.pillTop, 186, m.pillHeight, 22, '#0b1220', 0.84, edge, selected ? 6 : 4));
        parts.push(svgText(head.isNumber ? 'number' : 'headline', m.pillTop + m.pillHeight / 2 + size * 0.358, size, 800, '#ffffff', head.text));
        parts.push('</svg>');

        return { svg: parts.join(''), level: 'pin', width: MARKER.width, height: height, anchorX: 100, anchorY: tipY };
    }

    // The drawing for one place at one level. An unknown level draws the full card.
    function signDrawing(place, selected, level) {
        if (level === 'pin') {
            return pinDrawing(place, !!selected);
        }

        return cardDrawing(place, !!selected, level === 'compact' ? 'compact' : 'full');
    }

    function signSvg(place, selected, level) {
        return signDrawing(place, selected, level).svg;
    }

    root.VirtualDriveSigns = {
        DEFAULTS: DEFAULTS,
        MARKER: MARKER,
        signSvg: signSvg,
        signDrawing: signDrawing,
        detailLevel: detailLevel,
        curveWidth: curveWidth,
        perspectiveExponent: function (overrides) { return perspectiveExponent(config(overrides)); },
        fitSize: fitSize,
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
