/*
 |-----------------------------------------------------------------------------
 | The BUILT bundle — the only spec that looks at Mix's real output
 |-----------------------------------------------------------------------------
 |
 | Every other spec in this directory imports `resources/js/spatial` as ES modules.
 | That is deliberate and it is what lets the suite run with no build step — but it
 | also means none of them can see a BUNDLING defect, because none of them ever load
 | what `npm run production` actually publishes.
 |
 | Two hazards live exclusively in the built artefact, and both are recorded as
 | UNVERIFIED in webpack.mix.js and resources/js/spatial/ldna-maplibre.js:
 |
 |   1. THE ALIAS. webpack mishandles the named class-expression shadowing in
 |      maplibre-gl's MINIFIED ESM distribution, rewriting a class's self-reference
 |      to an outer scope so the map throws when it is used. webpack.mix.js works
 |      around it by aliasing bare `maplibre-gl` to the UNMINIFIED distribution. That
 |      workaround was carried forward from maplibre-gl 6.0.0; this branch pins 6.7.0
 |      and nothing had re-tested it.
 |
 |   2. THE NAMED IMPORTS. maplibre-gl v6 publishes no default export, so a default
 |      import yields `undefined` and every use throws. The production build reports
 |      that as a WARNING and exits 0 — a green build with a dead bundle.
 |
 | Neither is visible to PHP, and neither is visible to a spec that loads the source
 | modules. This file is the coverage that closes that gap, and it is why
 | tests/browser/support/static-server.js mounts public/js/spatial at `/dist/`
 | under a prefix distinct from the `/js/spatial/` source mount: a spec must be able
 | to say which of the two artefacts it is exercising.
 |
 | WHAT THIS FILE CAN PROVE WITHOUT A GPU, AND WHAT IT CANNOT
 | ----------------------------------------------------------
 | Everything above is a MODULE-GRAPH property, and a module graph resolves with no
 | WebGL whatever. The bundle evaluates, the named bindings resolve, `createLdnaRenderer`
 | is callable, the panel's stored geometry is adopted — all of that is assertable here
 | and all of it runs in this container.
 |
 | What needs a GPU is only the last step: MapLibre constructing a map. That one test
 | skips loudly where WebGL2 is absent, exactly as renderer-gl.spec.js does. A run where
 | it skipped has NOT verified basemap rendering from the bundle — see the note there.
 */

const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');
const { hasWebgl2 } = require('./support/capabilities');

const ROOT = path.resolve(__dirname, '../..');
const PANEL = path.join(ROOT, 'resources/views/partials/location-dna/_maplibre-panel.blade.php');
const SUPPORT = path.join(ROOT, 'app/Support/Spatial/LdnaBasemapSurface.php');
const HARNESS = path.join(__dirname, 'fixtures/bundle-harness.html');

/** What the harness declares, and therefore what the bundle is being handed. */
const HARNESS_STATE = {
    polygons: [
        { label: 'Downtown', path: [{ lat: 27.77, lng: -82.64 }, { lat: 27.79, lng: -82.64 }, { lat: 27.79, lng: -82.61 }] },
    ],
    radius_searches: [
        { lat: 27.7676, lng: -82.6403, radius_miles: 5, address: '100 Central Ave' },
    ],
};

/*
 | Message fragments that mean THE BUNDLE IS BROKEN, as distinct from a container that
 | merely has no GPU.
 |
 | This distinction is the entire reason the spec can run here at all. Without WebGL,
 | `new maplibregl.Map()` throws and the renderer catches it and reports `error` — a
 | correct, expected outcome that says nothing about the build. A bundling defect throws
 | at the SAME call site with a categorically different message: a missing binding, or a
 | class touched before its initialiser ran. Asserting on which message arrived is what
 | separates the two without needing a GPU to tell them apart.
 */
const BUNDLE_DEFECT_SIGNATURES = [
    'is not a constructor',      // named import resolved to undefined
    'is not a function',         // ditto, for addProtocol/setWorkerUrl
    'before initialization',     // the webpack class-expression rewrite
    'is not defined',            // ditto
    'Cannot read propert',       // reading through an undefined namespace
];

/** Load the harness with the guard installed, recording anything the page throws. */
async function bootBundle(page) {
    const network = await installNetworkGuard(page);
    const pageErrors = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));

    await page.goto('/bundle-harness.html');

    // The bundle tag is `defer`, and it mounts on DOMContentLoaded or immediately if
    // that has already fired. Waiting on the mount marker rather than a timeout means a
    // bundle that never evaluates fails here with a clear cause instead of later with a
    // confusing one.
    await expect
        .poll(() => page.evaluate(() => document.getElementById('ldna-built').dataset.ldnaMaplibreMounted), { timeout: 15_000 })
        .toBe('1');

    return { network, pageErrors };
}

/** The status the renderer last reported, and the sentence it put on the page. */
async function status(page) {
    return page.evaluate(() => ({
        state: document.getElementById('ldna-built').getAttribute('data-ldna-map-state'),
        message: (document.querySelector('[data-ldna-map-status]') || {}).textContent || '',
    }));
}

test.describe('the built bundle evaluates and mounts', () => {
    test('the module graph resolves — no uncaught error, and the mount hook is installed', async ({ page }) => {
        const { pageErrors } = await bootBundle(page);

        // An alias defect or a bad named import surfaces as an uncaught exception during
        // module evaluation, before anything else in this file gets a chance to run.
        expect(pageErrors, `the bundle threw while evaluating: ${pageErrors.join(' | ')}`).toEqual([]);

        expect(await page.evaluate(() => typeof window.ldnaMaplibreMount)).toBe('function');
        expect(await page.evaluate(() => !!document.getElementById('ldna-built')._ldnaRenderer)).toBe(true);
    });

    test('createLdnaRenderer survives bundling with its whole surface intact', async ({ page }) => {
        await bootBundle(page);

        // The host's Blade wiring calls every one of these. A tree-shaken or partially
        // bundled renderer would still mount and still look alive.
        const methods = await page.evaluate(() => {
            const r = document.getElementById('ldna-built')._ldnaRenderer;
            return ['init', 'hydrate', 'isHydrated', 'getState', 'resize', 'setBoundary',
                'clearBoundary', 'setPropertyPin', 'fitToGeometry', 'fitToBoundary']
                .filter((name) => typeof r[name] !== 'function');
        });

        expect(methods, `missing from the bundled renderer: ${methods.join(', ')}`).toEqual([]);
    });

    test('the maplibre-gl namespace is real — the alias and named-import contract', async ({ page }) => {
        await bootBundle(page);
        const { state, message } = await status(page);

        // Where there is no GPU this is legitimately 'error'. What it must never be is an
        // error CAUSED BY THE BUILD, and the message is what tells them apart.
        const defect = BUNDLE_DEFECT_SIGNATURES.find((sig) => message.includes(sig));

        expect(
            defect,
            `the bundled maplibre-gl namespace is broken — status "${state}" reported: ${message}`
        ).toBeUndefined();

        expect(['ready', 'degraded', 'error']).toContain(state);
    });
});

test.describe('the panel contract, against the real bundle', () => {
    test('stored geometry in data-ldna-state is adopted before any map exists', async ({ page }) => {
        await bootBundle(page);

        // The PR #124 property, re-proved through the built artefact: hydration precedes
        // init, so it holds even where init cannot succeed at all. This is what stops a
        // GPU-less client — or a browser that refuses the tiles — serialising an empty
        // geometry set over the user's saved shapes.
        expect(await page.evaluate(() => document.getElementById('ldna-built')._ldnaRenderer.isHydrated())).toBe(true);

        const read = await page.evaluate(() => document.getElementById('ldna-built')._ldnaRenderer.getState());

        expect(read.polygons).toEqual(HARNESS_STATE.polygons);
        expect(read.radius_searches).toEqual(HARNESS_STATE.radius_searches);
    });

    test('a second mount pass is a no-op rather than a second renderer', async ({ page }) => {
        await bootBundle(page);

        // `window.ldnaMaplibreMount()` exists so a host can re-scan after a Livewire
        // morphdom update. Re-scanning a page whose panels are already mounted is the
        // common case, and it must not build a second map over the first.
        // A re-scan RETURNS the panels it found, already-mounted ones included — so the
        // count is the number of panels on the page, not the number of new maps. Identity
        // is what carries the no-op property, and it is what is asserted here: the same
        // renderer instance comes back, still hydrated, and the container still holds one
        // map. A count alone would pass just as happily against a mount that rebuilt.
        const again = await page.evaluate(() => {
            window.__ldnaFirst = document.getElementById('ldna-built')._ldnaRenderer;
            const returned = window.ldnaMaplibreMount();

            return {
                count: returned.length,
                sameInstance: returned[0] === window.__ldnaFirst,
                stillOnElement: document.getElementById('ldna-built')._ldnaRenderer === window.__ldnaFirst,
                stillHydrated: window.__ldnaFirst.isHydrated(),
                canvases: document.querySelectorAll('#ldna-built canvas').length,
            };
        });

        expect(again.count).toBe(1);
        expect(again.sameInstance).toBe(true);
        expect(again.stillOnElement).toBe(true);
        expect(again.stillHydrated).toBe(true);
        expect(again.canvases, 'a re-scan built a second map over the first').toBeLessThanOrEqual(1);
    });

    test('the harness mirrors the attributes Blade actually emits', async ({ page }) => {
        await bootBundle(page);

        // The fixture stands in for _maplibre-panel.blade.php. If the partial renames an
        // attribute and the fixture does not follow, this file would go on testing a
        // contract the application no longer has — passing while production broke.
        const blade = fs.readFileSync(PANEL, 'utf8');
        const support = fs.readFileSync(SUPPORT, 'utf8');
        const fixture = fs.readFileSync(HARNESS, 'utf8');

        const attributes = [...fixture.matchAll(/\sdata-([a-z0-9-]+)=/g)].map((m) => m[1]);
        expect(attributes.length).toBeGreaterThan(5);

        /*
         | The panel emits its attributes from TWO places, and both have to be searched or
         | this assertion is worthless.
         |
         | The `data-ldna-*` attributes are written literally in the template. The basemap
         | ones — attribution, the initial view, the archive URL — arrive through a
         | `@foreach` over LdnaBasemapSurface::containerAttributes(), so their names appear
         | in the SUPPORT CLASS and never in the Blade at all. That indirection is the
         | single-reader rule doing its job: a template that spelled those names itself
         | would be a second reader of the basemap config.
         */
        const missing = attributes.filter(
            (attr) => !blade.includes(`data-${attr}`) && !support.includes(`'${attr}'`)
        );

        expect(missing, `the fixture declares attributes nothing emits: ${missing.join(', ')}`).toEqual([]);

        // Valueless in the partial, so the regex above cannot see it — and it is the hook
        // the entry point selects on, so its absence would mean nothing mounts at all.
        expect(blade).toContain('data-ldna-maplibre');
    });
});

test.describe('what the bundle is allowed to fetch', () => {
    test('contacts no Google host, and no third party at all', async ({ page }) => {
        const { network } = await bootBundle(page);

        // The whole point of the migration. A bundle that quietly pulled a Google script
        // would still render a map, and every other assertion here would still pass.
        expect(network.forbidden, `forbidden hosts contacted: ${network.forbidden.join(', ')}`).toEqual([]);
        expect(network.external, `unexpected third-party requests: ${network.external.join(', ')}`).toEqual([]);
    });

    test("MapLibre's stylesheet is bundled, not fetched", async ({ page }) => {
        await bootBundle(page);

        // The entry imports 'maplibre-gl/dist/maplibre-gl.css'. Mix can either inline it
        // into the bundle or emit a sibling .css file the panel links conditionally;
        // what it must not do is leave the renderer requesting it from a CDN at runtime.
        const inlined = await page.evaluate(() => [...document.styleSheets].some((sheet) => {
            try {
                return [...sheet.cssRules].some((rule) => (rule.selectorText || '').includes('maplibregl'));
            } catch (e) {
                return false;
            }
        }));

        const sibling = fs.existsSync(path.join(ROOT, 'public/js/spatial/ldna-maplibre.css'));

        expect(inlined || sibling, 'maplibre-gl.css reached neither the bundle nor a sibling stylesheet').toBe(true);
    });

    test('the worker and its shared chunk are published side by side', async ({ page, request }) => {
        await bootBundle(page);

        // maplibre-gl-worker.mjs imports maplibre-gl-shared.mjs by RELATIVE path, so one
        // without the other is a worker that never starts — and a worker that never starts
        // paints the background layer and requests no tile, which reads as an empty map
        // rather than as an error. webpack.mix.js copies both for exactly this reason.
        for (const asset of ['maplibre-gl-worker.mjs', 'maplibre-gl-shared.mjs']) {
            const response = await request.get(`/dist/${asset}`);
            expect(response.status(), `${asset} is not published beside the bundle`).toBe(200);
        }
    });
});

test.describe('the built bundle against real MapLibre', () => {
    test('constructs a live map from the published artefact', async ({ page }) => {
        await bootBundle(page);

        test.skip(
            !(await hasWebgl2(page)),
            'No WebGL2 in this container — the bundled MapLibre cannot construct a map. '
            + 'The module graph above IS verified; basemap rendering from the bundle is NOT.'
        );

        // The assertion the alias workaround exists to protect. With a GPU present, a
        // successful construction against the pinned maplibre-gl is the only evidence
        // that webpack did not rewrite the distribution's class expressions.
        await expect.poll(async () => (await status(page)).state, { timeout: 20_000 })
            .not.toBe('error');

        expect(await page.evaluate(() => !!document.querySelector('#ldna-built canvas'))).toBe(true);
    });
});
