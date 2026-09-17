/*
 |-----------------------------------------------------------------------------
 | Virtual Drive — AR property markers a driver can read, anchored on their house
 |-----------------------------------------------------------------------------
 |
 | Three rounds shaped these specs.
 |
 | 2026-09-11 (live): FOR SALE / FOR RENT was the smallest type on the sign and
 | the selected home differed from its neighbours only by an outline colour.
 |
 | 2026-09-15 (live): the selected sign appeared to TRAVEL with the camera. It
 | did not move — its coordinate is fixed — but it barely shrank from 13 m to
 | 205 m, and a board with nothing under it read as an overlay. The house number
 | was too small to tell 6580 from 6590 at a glance.
 |
 | 2026-09-15 (static design review): Option A, the floating callout card, was
 | chosen, with the house number as the LARGEST element and three detail levels
 | instead of one yard sign shrunk to a smear. The physical-sign treatment (white
 | board, post, tip) is gone on purpose.
 |
 | So these specs pin:
 |   • hierarchy — the house number is the largest line at every level, the
 |     status is a coloured chip, the price sits below, SELECTED is a small tab;
 |   • detail levels — neighbour Full ≤ 50 m < Compact ≤ 110 m < Pin ≤ 160 m;
 |     selected Full ≤ 110 m < Compact ≤ 160 m < Pin ≤ 180 m; hidden beyond;
 |   • anchoring — the dot under a card, or the tip of a pin, is the icon anchor
 |     and sits on the MLS coordinate at every level;
 | and what must not change: the coordinate never moves, the width keeps
 | shrinking with distance, a click selects without moving or turning the
 | camera, Face the selected home is the one explicit turn, and the page builds
 | exactly one panorama.
 |
 | Fake Maps API; every request leaving the fixture origin is aborted.
 |
 | Fixture walk order: 0 Stones sale · 1 Stones rent · 2 Manasota A (6590) ·
 | 3 Manasota B (6580, 33 m away: ~29 m south and ~16 m east of A) · 4-6 Siesta
 | Bayside units.
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');

const PROVIDER_HOSTS = ['googleapis.com', 'gstatic.com', 'google.com', 'apple-mapkit.com', 'apple.com'];

const MANASOTA_A = { lat: 26.960258, lng: -82.38292 };
const MANASOTA_B = { lat: 26.959994, lng: -82.382761 };
const METERS_PER_DEGREE_LAT = 111320;

const fakeGoogle = (page) => page.evaluate(() => window.__fakeGoogle.counters());
const markers = (page) => page.evaluate(() => window.__fakeGoogle.markers());
const signOf = (list, fragment) => list.find((m) => (m.title || '').includes(fragment));
const fitted = (page, id) => page.evaluate((i) => window.VirtualDriveDiagnostics.google.signs.find((s) => s.id === i), id);
const panoramaState = (page) => page.evaluate(() => {
    const p = window.__fakeGoogle.lastPanorama();

    return { lat: p.getPosition().lat(), lng: p.getPosition().lng(), heading: p.getPov().heading, pano: p._pano };
});

function expectNoProviderTraffic(record) {
    expect(record.forbidden).toEqual([]);
    expect(record.external.filter((url) => PROVIDER_HOSTS.some((host) => url.includes(host)))).toEqual([]);
}

async function openOnManasotaA(page, { beforeLaunch } = {}) {
    const record = await installNetworkGuard(page);

    await page.goto('/virtual-drive/google.html?offset=35');
    await expect(page.locator('#vd-launch')).toBeEnabled();

    for (let i = 0; i < 2; i += 1) {
        await page.click('#vd-next');
    }

    if (beforeLaunch) {
        await beforeLaunch();
    }

    expect((await fakeGoogle(page)).panoramaConstructorCalls).toBe(0);
    await page.click('#vd-launch');
    await expect.poll(async () => (await fakeGoogle(page)).panoramas).toBe(1);
    await page.waitForTimeout(150);

    return record;
}

// Put the camera `meters` due north (positive) or south (negative) of a point, as a person walking would.
async function standNorthOf(page, point, meters) {
    await page.evaluate(({ lat, lng }) => {
        const p = window.__fakeGoogle.lastPanorama();
        const here = p.getPosition();

        p.__userWalk(lat - here.lat(), lng - here.lng());
    }, { lat: point.lat + meters / METERS_PER_DEGREE_LAT, lng: point.lng });
    await page.waitForTimeout(100);
}

// The <text> elements of a drawing, in document order: part, size, words.
function readText(svg) {
    return Array.from(svg.matchAll(/<text data-part="([\w-]+)"[^>]*font-size="([\d.]+)"[^>]*>([^<]*)<\/text>/g))
        .map((m) => ({ part: m[1], size: Number(m[2]), text: m[3].replace(/&amp;/g, '&') }));
}

// Where a drawing says its anchor is, read from the SVG itself.
function drawnAnchor(svg) {
    const viewBox = /viewBox="0 0 ([\d.]+) ([\d.]+)"/.exec(svg).slice(1).map(Number);
    const ring = /data-part="anchor-ring" cx="([\d.]+)" cy="([\d.]+)"/.exec(svg);
    const tip = /data-part="pin" d="M([\d.]+) ([\d.]+) /.exec(svg);
    const point = ring ? [Number(ring[1]), Number(ring[2])] : [Number(tip[1]), Number(tip[2])];

    return { viewBox, point };
}

const DISTANCES = [0, 3, 6, 10, 12, 15, 20, 35, 50, 80, 125, 160, 170, 180];

test.describe('Virtual Drive · AR marker hierarchy, detail levels and anchoring (fake Maps API, no network)', () => {
    test('hierarchy: the house number is the largest line at every level, on every kind of marker — and nothing imitates a yard sign', async ({ page }) => {
        const record = await installNetworkGuard(page);

        await page.goto('/virtual-drive/google.html');

        const drawings = await page.evaluate(() => {
            const S = window.VirtualDriveSigns;
            const listing = (id, type, label, price, address, lat = 1) => ({ id, latitude: lat, longitude: lat, transaction_type: type, sign_label: label, display_price: price, address });
            const kinds = {
                rent: S.places([listing('r', 'rent', 'FOR RENT', '$14,000/mo', '6590 MANASOTA KEY RD')])[0],
                sale: S.places([listing('s', 'sale', 'FOR SALE', '$12,500,000', '1226 BAYSIDE DR #C')])[0],
                longUnit: S.places([listing('l', 'rent', 'FOR RENT', '$3,100/mo', '12345 GULF OF MEXICO DR #PH-1204')])[0],
                withheld: S.places([listing('w', 'rent', 'FOR RENT', '$2,400/mo', '')])[0],
                sharedBuilding: S.places([
                    listing('u1', 'sale', 'FOR SALE', '$1', '100 MAIN ST #1', 2),
                    listing('u2', 'rent', 'FOR RENT', '$2', '100 MAIN ST #2', 2),
                ])[0],
                // Two rentals whose house numbers differ: no shared number to show.
                mixedBuilding: S.places([
                    listing('m1', 'rent', 'FOR RENT', '$1', '100 MAIN ST #1', 3),
                    listing('m2', 'rent', 'FOR RENT', '$2', '200 MAIN ST #2', 3),
                ])[0],
            };
            const out = [];

            Object.keys(kinds).forEach((kind) => ['full', 'compact', 'pin'].forEach((level) => [false, true].forEach((selected) => {
                const d = S.signDrawing(kinds[kind], selected, level);

                out.push({ kind, level, selected, svg: d.svg, drawnLevel: d.level, width: d.width });
            })));

            return out;
        });

        const at = (kind, level, selected) => drawings.find((d) => d.kind === kind && d.level === level && d.selected === selected);

        for (const d of drawings) {
            const label = `${d.kind} ${d.level} ${d.selected ? 'selected' : 'neighbour'}`;
            const text = readText(d.svg);
            const head = text.find((t) => t.part === 'number' || t.part === 'headline');

            expect(d.drawnLevel, label).toBe(d.level);
            expect(d.width, label).toBe(200);
            expect(new RegExp(`data-level="${d.level}"`).test(d.svg), label).toBe(true);

            // The largest line is the number (or, with no number, the line standing in for it).
            expect(head, label).toBeTruthy();
            for (const t of text.filter((x) => x !== head)) {
                expect(head.size, `${label}: "${head.text}" is larger than "${t.text}"`).toBeGreaterThan(t.size);
            }

            // No physical-sign treatment: no post, no tip, no white board, no number strip.
            expect(d.svg, label).not.toMatch(/data-part="(post|post-edge|tip|number-strip)"/);
            expect(d.svg, label).not.toMatch(/<rect[^>]*fill="#ffffff"(?![^>]*fill-opacity)/);

            // A dark translucent surface with an edge; yellow only on the selected home.
            expect(d.svg, label).toMatch(/data-part="(card|pill)"[^>]*fill="#0b1220" fill-opacity="0\.8\d?"/);
            expect(/facc15/.test(d.svg), label).toBe(d.selected);
        }

        const parts = (kind, level, selected) => readText(at(kind, level, selected).svg).map((t) => t.part);
        const words = (kind, level, selected) => readText(at(kind, level, selected).svg).map((t) => t.text);

        // FULL: chip, number, price — SELECTED tab on top for the selected home.
        expect(words('rent', 'full', false)).toEqual(['FOR RENT', '6590', '$14,000/mo']);
        expect(words('rent', 'full', true)).toEqual(['SELECTED', 'FOR RENT', '6590', '$14,000/mo']);
        expect(words('sale', 'full', false)).toEqual(['FOR SALE', '1226 #C', '$12,500,000']);
        expect(at('rent', 'full', false).svg).toMatch(/data-part="chip"[^>]*fill="#2563eb"/);
        expect(at('sale', 'full', false).svg).toMatch(/data-part="chip"[^>]*fill="#dc2626"/);
        expect(at('sharedBuilding', 'full', false).svg).toMatch(/data-part="chip"[^>]*fill="#7c3aed"/); // sale + rent

        // COMPACT: number and chip; the price stays for the selected home only.
        expect(words('rent', 'compact', false)).toEqual(['FOR RENT', '6590']);
        expect(words('rent', 'compact', true)).toEqual(['SELECTED', 'FOR RENT', '6590', '$14,000/mo']);

        // PIN: the number alone, on a map pin — no card, no status, no price, no tab.
        for (const selected of [false, true]) {
            expect(words('rent', 'pin', selected)).toEqual(['6590']);
            expect(at('rent', 'pin', selected).svg).toMatch(/data-part="pin"/);
            expect(at('rent', 'pin', selected).svg).not.toMatch(/data-part="(card|chip|tab|price|leader|anchor-ring)"/);
        }
        expect(at('rent', 'pin', false).svg).toMatch(/data-part="pin"[^>]*fill="#2563eb"/);
        expect(at('rent', 'pin', true).svg).toMatch(/data-part="pin"[^>]*fill="#facc15"/);

        // No number is invented: a withheld address leads with the price instead, and a
        // building whose units differ leads with its unit count.
        expect(parts('withheld', 'full', false)).not.toContain('number');
        expect(words('withheld', 'full', false)).toEqual(['FOR RENT', '$2,400/mo']);
        expect(words('withheld', 'pin', false)).toEqual(['$2,400/mo']);
        expect(words('sharedBuilding', 'full', false)).toEqual(['FOR SALE & RENT', '100', '2 UNITS']);
        expect(words('mixedBuilding', 'full', false)).toEqual(['FOR RENT', '2 UNITS']);

        // A long unit number shrinks to fit, and still outranks everything else.
        const longUnit = readText(at('longUnit', 'full', false).svg);

        expect(longUnit.map((t) => t.text)).toEqual(['FOR RENT', '12345 #PH-1204', '$3,100/mo']);
        expect(longUnit[1].size).toBeLessThan(readText(at('rent', 'full', false).svg)[1].size);

        // The SELECTED tab is the smallest line on a selected card.
        for (const level of ['full', 'compact']) {
            const t = readText(at('rent', level, true).svg);

            expect(t[0].part).toBe('tab-text');
            expect(Math.min(...t.slice(1).map((x) => x.size))).toBeGreaterThan(t[0].size);
        }

        expectNoProviderTraffic(record);
    });

    test('readable on screen: the house number stays large even at the far edge of each detail level', async ({ page }) => {
        const record = await installNetworkGuard(page);

        await page.goto('/virtual-drive/google.html');

        const px = await page.evaluate(() => {
            const S = window.VirtualDriveSigns;
            const place = S.places([{ id: 'x', latitude: 1, longitude: 1, transaction_type: 'rent', sign_label: 'FOR RENT', display_price: '$14,000/mo', address: '6590 MANASOTA KEY RD' }])[0];
            const numberPx = (d, selected) => {
                const level = S.detailLevel(d, undefined, selected);
                const svg = S.signDrawing(place, selected, level).svg;
                const size = Number(/data-part="number"[^>]*font-size="([\d.]+)"/.exec(svg)[1]);

                return { d, level, px: size * S.screenWidth(d, undefined, selected) / S.MARKER.width };
            };

            // The far edge of every level, where each is smallest.
            return [numberPx(50, false), numberPx(110, false), numberPx(160, false), numberPx(110, true), numberPx(160, true), numberPx(180, true)];
        });

        const floor = { full: 30, compact: 28, pin: 24 };

        for (const s of px) {
            expect(s.px, `${s.level} at ${s.d} m: the number is ${s.px.toFixed(1)} px`).toBeGreaterThanOrEqual(floor[s.level]);
        }

        expectNoProviderTraffic(record);
    });

    test('detail levels: neighbour 6–50 full, –110 compact, –160 pin; selected 0–110 full, –160 compact, –180 pin; hidden beyond', async ({ page }) => {
        const record = await installNetworkGuard(page);

        await page.goto('/virtual-drive/google.html');

        const r = await page.evaluate(() => {
            const S = window.VirtualDriveSigns;
            const sample = (selected) => [0, 5.9, 6, 20, 50, 50.5, 80, 110, 110.5, 130, 160, 160.5, 170, 180, 180.5, 250]
                .map((d) => ({ d, level: S.detailLevel(d, undefined, selected), width: S.screenWidth(d, undefined, selected) }));
            const everyMetre = (selected) => {
                const out = [];

                for (let d = 0; d <= 200; d += 0.5) out.push({ d, level: S.detailLevel(d, undefined, selected), width: S.screenWidth(d, undefined, selected) });

                return out;
            };

            return {
                neighbour: sample(false),
                selected: sample(true),
                neighbourGrid: everyMetre(false),
                selectedGrid: everyMetre(true),
                defaults: S.DEFAULTS,
                overridden: [S.detailLevel(40, { fullDistance: 30 }, false), S.detailLevel(40, { selectedFullDistance: 30 }, true)],
            };
        });

        const levels = (list) => Object.fromEntries(list.map((s) => [s.d, s.level]));

        expect(levels(r.neighbour)).toEqual({
            0: null, 5.9: null, 6: 'full', 20: 'full', 50: 'full', 50.5: 'compact', 80: 'compact', 110: 'compact',
            110.5: 'pin', 130: 'pin', 160: 'pin', 160.5: null, 170: null, 180: null, 180.5: null, 250: null,
        });
        expect(levels(r.selected)).toEqual({
            0: 'full', 5.9: 'full', 6: 'full', 20: 'full', 50: 'full', 50.5: 'full', 80: 'full', 110: 'full',
            110.5: 'compact', 130: 'compact', 160: 'compact', 160.5: 'pin', 170: 'pin', 180: 'pin', 180.5: null, 250: null,
        });

        expect(r.defaults.selectedMaxDistance).toBe(180);
        expect(r.defaults.maxDistance).toBe(160);
        expect(r.defaults.minDistance).toBe(6);

        // A level and a width never disagree about whether a marker is shown, and
        // detail only ever drops going outwards.
        const order = { full: 0, compact: 1, pin: 2 };

        for (const grid of [r.neighbourGrid, r.selectedGrid]) {
            let lastRank = -1;
            let hiddenAfterShown = false;

            for (const s of grid) {
                expect(s.level === null, `${s.d} m`).toBe(s.width === 0);

                if (s.level === null) {
                    if (lastRank >= 0) hiddenAfterShown = true;
                    continue;
                }

                expect(hiddenAfterShown, `${s.d} m reappears after hiding`).toBe(false);
                expect(order[s.level], `${s.d} m`).toBeGreaterThanOrEqual(lastRank);
                lastRank = order[s.level];
            }
        }

        // The thresholds are configuration, not magic numbers in the drawing.
        expect(r.overridden).toEqual(['compact', 'compact']);
        expectNoProviderTraffic(record);
    });

    test('anchoring: the dot under a card, or the tip of a pin, is the icon anchor on the MLS coordinate — at every level', async ({ page }) => {
        const record = await openOnManasotaA(page);

        // In the drawing: the leader joins the card to the ring, the dot is centred in
        // the ring, and the anchor the drawing reports is that point.
        const geometry = await page.evaluate(() => {
            const S = window.VirtualDriveSigns;
            const place = S.places([{ id: 'x', latitude: 1, longitude: 1, transaction_type: 'rent', sign_label: 'FOR RENT', display_price: '$1', address: '1 A ST' }])[0];
            const out = [];

            ['full', 'compact', 'pin'].forEach((level) => [false, true].forEach((selected) => {
                const d = S.signDrawing(place, selected, level);
                const num = (re) => { const m = re.exec(d.svg); return m ? m.slice(1).map(Number) : null; };

                out.push({
                    level, selected, width: d.width, height: d.height, anchorX: d.anchorX, anchorY: d.anchorY,
                    viewBox: num(/viewBox="0 0 ([\d.]+) ([\d.]+)"/),
                    card: num(/data-part="card" x="[\d.]+" y="([\d.]+)" width="[\d.]+" height="([\d.]+)"/),
                    leader: num(/data-part="leader" x="([\d.]+)" y="([\d.]+)" width="([\d.]+)" height="([\d.]+)"/),
                    ring: num(/data-part="anchor-ring" cx="([\d.]+)" cy="([\d.]+)" r="([\d.]+)"/),
                    dot: num(/data-part="anchor-dot" cx="([\d.]+)" cy="([\d.]+)"/),
                    tip: num(/data-part="pin" d="M([\d.]+) ([\d.]+) /),
                });
            }));

            return out;
        });

        for (const g of geometry) {
            const label = `${g.level} ${g.selected ? 'selected' : 'neighbour'}`;

            expect(g.viewBox, label).toEqual([g.width, g.height]);
            expect(g.anchorX, label).toBe(100);

            if (g.level === 'pin') {
                expect(g.tip, label).toEqual([100, g.anchorY]);
                expect(g.anchorY, label).toBeLessThanOrEqual(g.height);
                expect(g.anchorY, label).toBeGreaterThan(g.height - 4);
            } else {
                const [cardY, cardH] = g.card;
                const [leaderX, leaderY, leaderW, leaderH] = g.leader;

                expect(g.ring.slice(0, 2), label).toEqual([100, g.anchorY]);
                expect(g.dot, label).toEqual([100, g.anchorY]);
                expect(g.anchorY + g.ring[2], label).toBeLessThanOrEqual(g.height); // the whole ring is drawn
                expect(leaderX + leaderW / 2, label).toBe(100);
                expect(leaderW, label).toBeLessThanOrEqual(3);                      // thin
                expect(leaderY, label).toBeCloseTo(cardY + cardH, 0);               // starts at the card
                expect(leaderY + leaderH, label).toBeCloseTo(g.anchorY, 0);         // ends at the ring's centre
                expect(leaderH, label).toBeGreaterThanOrEqual(20);                  // the card floats above the house
            }
        }

        // On the panorama, at every level a marker is drawn at: icon anchor = the drawing's
        // anchor scaled to the icon, and the marker sits on its MLS coordinate.
        const seen = new Set();
        const checkAll = async () => {
            for (const m of (await markers(page)).filter((x) => x.visible && x.iconWidth)) {
                const { viewBox, point } = drawnAnchor(m.svg);
                const scale = m.iconWidth / viewBox[0];

                expect(m.iconHeight, m.title).toBe(Math.round(viewBox[1] * scale));
                expect(m.anchorX, m.title).toBe(Math.round(point[0] * scale));
                expect(m.anchorY, m.title).toBe(Math.round(point[1] * scale));
                seen.add(m.level);
            }

            const list = await markers(page);

            expect({ lat: signOf(list, 'Manasota Key A').lat, lng: signOf(list, 'Manasota Key A').lng }).toEqual(MANASOTA_A);
            expect({ lat: signOf(list, 'Manasota Key B').lat, lng: signOf(list, 'Manasota Key B').lng }).toEqual(MANASOTA_B);
        };

        for (const north of [3, 60, 120, 170]) {
            await standNorthOf(page, MANASOTA_A, north);
            await checkAll();
        }

        expect([...seen].sort()).toEqual(['compact', 'full', 'pin']);
        expectNoProviderTraffic(record);
    });

    test('depth: width shrinks at every step outwards, gentler than a real board, selected ×1.25 with no plateau', async ({ page }) => {
        const record = await installNetworkGuard(page);

        await page.goto('/virtual-drive/google.html');

        const r = await page.evaluate((distances) => {
            const S = window.VirtualDriveSigns;
            const viewport = 960;
            const drawn = (d, selected) => {
                const icon = S.iconWidth(d, viewport, undefined, selected);

                return icon ? Math.round(S.modelledScreenWidth(icon, d, viewport)) : 0;
            };
            const grid = (from, to, selected) => {
                const out = [];

                for (let d = from; d <= to; d += 1) out.push(S.screenWidth(d, undefined, selected));

                return out;
            };

            return {
                selected: distances.map((d) => S.screenWidth(d, undefined, true)),
                neighbour: distances.map((d) => S.screenWidth(d, undefined, false)),
                curve: distances.map((d) => S.curveWidth(d)),
                selectedGrid: grid(0, 180, true),
                neighbourGrid: grid(6, 160, false),
                selectedHidden: [180.5, 200, 220, 400].map((d) => S.screenWidth(d, undefined, true)),
                neighbourHidden: [0.5, 3, 5.9, 160.5, 200, 220].map((d) => S.screenWidth(d, undefined, false)),
                drawnSelected: [4, 10, 35, 80, 160, 180].map((d) => ({ d, drawn: drawn(d, true), want: S.screenWidth(d, undefined, true) })),
                drawnNeighbour: [6, 8, 12, 35, 80, 160].map((d) => ({ d, drawn: drawn(d, false), want: S.screenWidth(d, undefined, false) })),
                exponent: S.perspectiveExponent(),
            };
        }, DISTANCES);

        // Strictly smaller at every sampled step outwards — the curve and the selected marker.
        for (let i = 1; i < DISTANCES.length; i += 1) {
            expect(r.curve[i], `curve ${DISTANCES[i]} m < ${DISTANCES[i - 1]} m`).toBeLessThan(r.curve[i - 1]);
            expect(r.selected[i], `selected ${DISTANCES[i]} m < ${DISTANCES[i - 1]} m`).toBeLessThan(r.selected[i - 1]);
        }

        // Never larger one metre further out, anywhere in either range (rounded px may tie).
        r.selectedGrid.forEach((w, i) => { if (i) expect(w).toBeLessThanOrEqual(r.selectedGrid[i - 1]); });
        r.neighbourGrid.forEach((w, i) => { if (i) expect(w).toBeLessThanOrEqual(r.neighbourGrid[i - 1]); });

        // No plateau at long range: the selected marker is still shrinking to 180 m.
        const at = (list, d) => list[DISTANCES.indexOf(d)];

        expect(at(r.selected, 180)).toBeLessThan(at(r.selected, 170));
        expect(at(r.selected, 170)).toBeLessThan(at(r.selected, 160));
        expect(at(r.selected, 180)).toBeLessThan(at(r.selected, 125) * 0.9);

        // Real depth, but gentler than a physical board (which would shrink 13.3× from 12 m to 160 m).
        const shrink = at(r.neighbour, 12) / at(r.neighbour, 160);

        expect(shrink).toBeGreaterThan(3);
        expect(shrink).toBeLessThan(160 / 12);
        expect(r.exponent).toBeGreaterThan(0.5);
        expect(r.exponent).toBeLessThan(1);

        // Selected ≈ 1.25 × a neighbour at the same distance, wherever both are shown.
        DISTANCES.forEach((d, i) => {
            if (r.neighbour[i]) {
                expect(r.selected[i] / r.neighbour[i], `${d} m`).toBeGreaterThan(1.23);
                expect(r.selected[i] / r.neighbour[i], `${d} m`).toBeLessThan(1.27);
            }
        });

        // Ranges: selected 0–180 m; neighbours 6–160 m.
        r.selectedGrid.forEach((w, d) => expect(w, `selected at ${d} m`).toBeGreaterThan(0));
        r.selectedHidden.forEach((w) => expect(w).toBe(0));
        r.neighbourGrid.forEach((w, i) => expect(w, `neighbour at ${i + 6} m`).toBeGreaterThan(0));
        r.neighbourHidden.forEach((w) => expect(w).toBe(0));

        // Drawn on target through the measured provider model, close in included.
        for (const s of [...r.drawnSelected, ...r.drawnNeighbour]) {
            expect(Math.abs(s.drawn - s.want), `${s.d} m drawn ${s.drawn} px, target ${s.want} px`).toBeLessThanOrEqual(3);
        }

        expectNoProviderTraffic(record);
    });

    test('driving past the house: the coordinate never moves, the marker is never rebuilt — it shrinks and sheds detail, full → compact → pin → hidden', async ({ page }) => {
        let setPositionInstalled = false;

        const record = await openOnManasotaA(page, {
            // A witness independent of the provider: any position write after creation is counted.
            beforeLaunch: async () => {
                await page.evaluate(async () => {
                    const lib = await window.google.maps.importLibrary('marker');

                    window.__positionWrites = 0;
                    lib.Marker.prototype.setPosition = function (p) { window.__positionWrites += 1; this._options.position = p; };
                });
                setPositionInstalled = true;
            },
        });

        expect(setPositionInstalled).toBe(true);

        const read = () => page.evaluate((mls) => {
            const pano = window.__fakeGoogle.lastPanorama().getPosition();
            const all = window.__fakeGoogle.markers().filter((m) => (m.title || '').includes('Manasota Key A'));
            const sign = window.VirtualDriveDiagnostics.google.signs.find((s) => s.id === 'FX-RENT-MANASOTA-A');

            return {
                pano: [pano.lat(), pano.lng()],
                markersFor6590: all.length,
                latLng: all.map((m) => [m.lat, m.lng]),
                distance: window.VirtualDriveSigns.meters({ lat: pano.lat(), lng: pano.lng() }, mls),
                screenWidth: sign.screenWidth,
                level: sign.level,
                drawnLevel: all[0].level,
                visible: all[0].visible,
                constructed: window.__fakeGoogle.counters().markers,
                removed: window.__fakeGoogle.counters().markersRemoved,
                positionWrites: window.__positionWrites,
            };
        }, MANASOTA_A);

        const start = await read();
        const samples = [start];

        // Onto the road 12 m east, then drive 260 m south past the house and beyond.
        await page.evaluate(() => window.__fakeGoogle.lastPanorama().__userWalk(0, 12 / (111320 * Math.cos(26.96 * Math.PI / 180))));

        for (let i = 1; i <= 26; i += 1) {
            await page.evaluate(() => window.__fakeGoogle.lastPanorama().__userWalk(-10 / 111320, 0));
            await page.waitForTimeout(20);
            samples.push(await read());
        }

        // The camera really moved.
        expect(samples[samples.length - 1].pano).not.toEqual(start.pano);

        for (const s of samples) {
            expect(s.markersFor6590).toBe(1);
            expect(s.latLng).toEqual([[MANASOTA_A.lat, MANASOTA_A.lng]]); // numerically identical, every step
            expect(s.positionWrites).toBe(0);
            expect(s.constructed).toBe(start.constructed);                // not rebuilt by driving
            expect(s.removed).toBe(0);

            // What is drawn is the level the rules give for where the camera is.
            if (s.visible) {
                expect(s.drawnLevel, `${Math.round(s.distance)} m`).toBe(s.level);
            }
        }

        // Once past the house, every step farther away is a smaller marker with no more
        // detail than the step before, until it is hidden past 180 m.
        const receding = samples.filter((s, i) => i > 0 && s.distance > samples[i - 1].distance && s.screenWidth > 0);
        const rank = { full: 0, compact: 1, pin: 2 };

        expect(receding.length).toBeGreaterThan(10);

        for (let i = 1; i < receding.length; i += 1) {
            expect(receding[i].screenWidth, `${Math.round(receding[i].distance)} m`).toBeLessThan(receding[i - 1].screenWidth);
            expect(rank[receding[i].level], `${Math.round(receding[i].distance)} m`).toBeGreaterThanOrEqual(rank[receding[i - 1].level]);
        }

        expect(new Set(receding.map((s) => s.level))).toEqual(new Set(['full', 'compact', 'pin']));
        expect(samples[samples.length - 1].distance).toBeGreaterThan(180);
        expect(samples[samples.length - 1].visible).toBe(false);
        expect((await fakeGoogle(page)).panoramaConstructorCalls).toBe(1);
        expectNoProviderTraffic(record);
    });

    test('on the panorama: the selected marker is larger, tabbed, yellow and on top; a marker click moves only the selection', async ({ page }) => {
        const record = await openOnManasotaA(page);

        let list = await markers(page);
        let a = signOf(list, 'Manasota Key A');
        let b = signOf(list, 'Manasota Key B');

        expect({ lat: a.lat, lng: a.lng }).toEqual(MANASOTA_A);
        expect({ lat: b.lat, lng: b.lng }).toEqual(MANASOTA_B);
        expect(a.text[0]).toBe('SELECTED');
        expect(b.text).not.toContain('SELECTED');
        expect(a.selectedOutline).toBe(true);
        expect(b.selectedOutline).toBe(false);
        expect(a.zIndex).toBeGreaterThan(b.zIndex);

        // Each marker is sized for its own distance and role, exactly as the rules say.
        const fitA = await fitted(page, 'FX-RENT-MANASOTA-A');
        const fitB = await fitted(page, 'FX-RENT-MANASOTA-B');
        const rules = await page.evaluate(([dA, dB]) => ({
            a: window.VirtualDriveSigns.screenWidth(dA, undefined, true),
            b: window.VirtualDriveSigns.screenWidth(dB, undefined, false),
            aAsNeighbour: window.VirtualDriveSigns.screenWidth(dA, undefined, false),
        }), [fitA.distance, fitB.distance]);

        expect(fitA.selected).toBe(true);
        expect(fitB.selected).toBe(false);
        expect(fitA.screenWidth).toBe(rules.a);
        expect(fitB.screenWidth).toBe(rules.b);
        expect(fitA.screenWidth / rules.aAsNeighbour).toBeCloseTo(1.25, 1);

        // Click the neighbour's marker: the emphasis moves, the camera does not.
        const before = await panoramaState(page);
        const countersBefore = await fakeGoogle(page);

        await page.evaluate((i) => window.__fakeGoogle.clickMarker(i), list.findIndex((m) => (m.title || '').includes('Manasota Key B')));
        await expect(page.locator('#vd-shopper')).toHaveAttribute('data-listing', 'FX-RENT-MANASOTA-B');

        list = await markers(page);
        a = signOf(list, 'Manasota Key A');
        b = signOf(list, 'Manasota Key B');

        expect(b.text[0]).toBe('SELECTED');
        expect(a.text).not.toContain('SELECTED');
        expect(b.zIndex).toBeGreaterThan(a.zIndex);
        expect(await panoramaState(page)).toEqual(before);

        const countersAfterClick = await fakeGoogle(page);

        expect(countersAfterClick.setPano).toBe(countersBefore.setPano);
        expect(countersAfterClick.programmaticPov).toBe(countersBefore.programmaticPov);
        expect(countersAfterClick.markers).toBe(countersBefore.markers); // selection redraws icons, never rebuilds markers

        // Face the selected home: the one explicit turn — toward 6580 now — and still no move.
        await page.getByRole('button', { name: 'Face the selected home' }).click();

        const faced = await panoramaState(page);
        const bearing = await page.evaluate((target) => {
            const p = window.__fakeGoogle.lastPanorama();

            return window.google.maps.importLibrary('geometry').then((g) => g.spherical.computeHeading(p.getPosition(), target));
        }, MANASOTA_B);

        expect(faced.lat).toBe(before.lat);
        expect(faced.lng).toBe(before.lng);
        expect(faced.pano).toBe(before.pano);
        expect(faced.heading).toBeCloseTo(bearing, 6);
        expect(faced.heading).not.toBeCloseTo(before.heading, 1);

        const countersAfterFace = await fakeGoogle(page);

        expect(countersAfterFace.programmaticPov).toBe(countersAfterClick.programmaticPov + 1);
        expect(countersAfterFace.setPano).toBe(countersBefore.setPano);
        expect(countersAfterFace.panoramaConstructorCalls).toBe(1);
        expectNoProviderTraffic(record);
    });

    test('on the panorama: levels follow distance and role, and clicking a far pin selects it without moving the camera', async ({ page }) => {
        const record = await openOnManasotaA(page);
        const constructed = (await fakeGoogle(page)).markers;
        const levelOf = async (fragment) => {
            const m = signOf(await markers(page), fragment);

            return m.visible ? m.level : null;
        };

        // Standing north of 6590 (selected): it goes full → compact → pin → hidden.
        // 6580 is ~29 m farther south and ~16 m east, so it steps down sooner.
        const walk = [
            { north: 3,   a: 'full',    b: 'full' },    // b ≈ 36 m
            { north: 40,  a: 'full',    b: 'compact' }, // b ≈ 71 m
            { north: 100, a: 'full',    b: 'pin' },     // b ≈ 130 m
            { north: 125, a: 'compact', b: 'pin' },     // b ≈ 155 m
            { north: 150, a: 'compact', b: null },      // b ≈ 180 m
            { north: 170, a: 'pin',     b: null },
            { north: 190, a: null,      b: null },
        ];

        for (const step of walk) {
            await standNorthOf(page, MANASOTA_A, step.north);
            expect(await levelOf('Manasota Key A'), `6590 from ${step.north} m north`).toBe(step.a);
            expect(await levelOf('Manasota Key B'), `6580 from ${step.north} m north of 6590`).toBe(step.b);
        }

        // Driving through every level never rebuilt a marker.
        expect((await fakeGoogle(page)).markers).toBe(constructed);

        // 100 m north: 6580 is a pin. Click the pin — it becomes the selection, gains
        // detail (a selected home at ~130 m is compact), and the camera stays put.
        await standNorthOf(page, MANASOTA_A, 100);

        const before = await panoramaState(page);
        const countersBefore = await fakeGoogle(page);
        const list = await markers(page);

        expect(signOf(list, 'Manasota Key B').level).toBe('pin');
        await page.evaluate((i) => window.__fakeGoogle.clickMarker(i), list.findIndex((m) => (m.title || '').includes('Manasota Key B')));
        await expect(page.locator('#vd-shopper')).toHaveAttribute('data-listing', 'FX-RENT-MANASOTA-B');
        await page.waitForTimeout(100);

        const after = await markers(page);
        const b = signOf(after, 'Manasota Key B');
        const a = signOf(after, 'Manasota Key A');

        expect(b.level).toBe('compact');
        expect(b.selectedOutline).toBe(true);
        expect(b.text[0]).toBe('SELECTED');
        expect(a.level).toBe('compact'); // 6590 is now a neighbour ~100 m away
        expect(a.selectedOutline).toBe(false);
        expect(await panoramaState(page)).toEqual(before);

        const countersAfter = await fakeGoogle(page);

        expect(countersAfter.setPano).toBe(countersBefore.setPano);
        expect(countersAfter.programmaticPov).toBe(countersBefore.programmaticPov);
        expect(countersAfter.markers).toBe(constructed);
        expect(countersAfter.panoramaConstructorCalls).toBe(1);
        expectNoProviderTraffic(record);
    });

    test('on the panorama: the selected home stays visible to ~180 m and right up close; neighbours keep 6–160 m', async ({ page }) => {
        const record = await openOnManasotaA(page);
        const visible = async (fragment) => signOf(await markers(page), fragment).visible;

        // ~170 m from the selected home: it stays up; its neighbour (~200 m) is hidden.
        await standNorthOf(page, MANASOTA_A, 170);
        expect(await visible('Manasota Key A')).toBe(true);
        expect(await visible('Manasota Key B')).toBe(false);

        // Past ~180 m even the selected marker is hidden.
        await standNorthOf(page, MANASOTA_A, 190);
        expect(await visible('Manasota Key A')).toBe(false);

        // 3 m from the selected home: it does not vanish; the neighbour is in range.
        await standNorthOf(page, MANASOTA_A, 3);
        expect(await visible('Manasota Key A')).toBe(true);
        expect(await visible('Manasota Key B')).toBe(true);

        // Select the neighbour by its marker: 6590 is now a neighbour 3 m away, so it
        // follows the neighbour rule and hides — and 6580 carries the selection.
        const list = await markers(page);

        await page.evaluate((i) => window.__fakeGoogle.clickMarker(i), list.findIndex((m) => (m.title || '').includes('Manasota Key B')));
        await expect(page.locator('#vd-shopper')).toHaveAttribute('data-listing', 'FX-RENT-MANASOTA-B');
        await page.waitForTimeout(100);

        expect(await visible('Manasota Key A')).toBe(false);
        expect(await visible('Manasota Key B')).toBe(true);

        // 140 m south of 6580 (selected, visible) puts 6590 ~170 m away: a neighbour
        // past 160 m is hidden even though the selected marker in front of it is not.
        await standNorthOf(page, MANASOTA_B, -140);
        expect(await visible('Manasota Key B')).toBe(true);
        expect(await visible('Manasota Key A')).toBe(false);

        const counters = await fakeGoogle(page);

        expect(counters.panoramaConstructorCalls).toBe(1);
        expect(counters.panoramas).toBe(1);
        expect(counters.setPano).toBe(0);
        expectNoProviderTraffic(record);
    });
});
