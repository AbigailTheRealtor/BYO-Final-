const mix = require('laravel-mix');
const path = require('path');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel applications. By default, we are compiling the CSS
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.js('resources/js/app.js', 'public/js').postCss('resources/css/app.css', 'public/css', [
    require('tailwindcss'),
    require('autoprefixer'),
]).version();

/*
 | Location DNA MapLibre renderer — a SEPARATE entry point, deliberately.
 |
 | MapLibre is a large dependency and this renderer ships behind a flag that
 | defaults to off, so none of it may reach app.js — the bundle every consumer
 | page already loads. Only a page that opts in loads this one.
 |
 | Salvaged from the Phase 2A proof (b12d06233) rather than rediscovered.
 */
mix.js('resources/js/spatial/ldna-maplibre.js', 'public/js/spatial');

/*
 | MapLibre's worker, published beside the bundle.
 |
 | MapLibre resolves its worker URL from `import.meta.url`, which webpack bakes
 | into a build-time file:// path. That fails MapLibre's own `^https?:` guard and
 | the worker silently never starts — the map then paints its background layer and
 | never requests a tile, which looks like an empty map rather than an error.
 | ldna-basemap.js points setWorkerUrl() at this copied asset instead.
 |
 | maplibre-gl-worker.mjs imports maplibre-gl-shared.mjs by RELATIVE path, so both
 | must sit side by side. Copied verbatim, never bundled: webpack does not parse
 | them, so the minified distribution is safe here even though the main thread
 | needs the alias below.
 */
mix.copy(
    [
        'node_modules/maplibre-gl/dist/maplibre-gl-worker.mjs',
        'node_modules/maplibre-gl/dist/maplibre-gl-shared.mjs',
    ],
    'public/js/spatial'
);

/*
 | MapLibre resolution workaround. TEMPORARY — and UNVERIFIED on this branch.
 |
 | webpack 5.74.0 mishandles the named class-expression shadowing used in
 | MapLibre's minified ESM distribution: where a class expression binds its own
 | name and refers to that binding internally, the bundler rewrites the
 | self-reference to an outer scope, and the map throws at runtime. Aliasing bare
 | `maplibre-gl` to the UNMINIFIED distribution avoids the trigger — same library,
 | same version, different distribution file.
 |
 | CARRIED FORWARD FROM b12d06233 AGAINST maplibre-gl 6.0.0. This branch pins
 | 6.7.0, and whether the defect still reproduces has NOT been re-verified,
 | because `npm install` cannot complete in this environment (the package firewall
 | blocks websocket-driver@0.7.4, a pre-existing laravel-mix dependency), so no
 | Mix build has been run here. Keeping the alias is the conservative choice: it
 | costs bundle size, while dropping it unverified risks a map that throws.
 |
 | REMOVE ONLY AFTER a real build plus a browser verification of the bundled
 | renderer. A green test run alone is not sufficient — the browser suite loads
 | the UMD distribution directly and never exercises this code path.
 */
mix.webpackConfig({
    resolve: {
        alias: {
            'maplibre-gl$': path.resolve(
                __dirname,
                'node_modules/maplibre-gl/dist/maplibre-gl-dev.mjs'
            ),
        },
    },
});
