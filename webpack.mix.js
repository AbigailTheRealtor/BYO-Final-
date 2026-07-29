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
 | Phase 2A — MapLibre + PMTiles proof-of-render bundle.
 |
 | A SEPARATE entry point, deliberately. MapLibre is a large dependency and this
 | is an internal diagnostic behind a disabled-by-default flag, so none of it may
 | reach app.js, which every consumer page loads. The proof page loads this
 | bundle and nothing else.
 |
 | The imported CSS (maplibre-gl's own, plus the page's local styles) is
 | extracted alongside the bundle as public/js/spatial/maplibre-proof.css.
 */
mix.js('resources/js/spatial/maplibre-proof.js', 'public/js/spatial');

/*
 | Phase 2A — MapLibre resolution workaround. TEMPORARY.
 |
 | webpack 5.74.0 mishandles the named class-expression shadowing used in
 | MapLibre GL 6.0.0's minified ESM distribution: where a class expression binds
 | its own name and refers to that binding internally, the bundler rewrites the
 | self-reference to an outer scope. In this build that surfaced on the DOM
 | helper carrying getScale — the reference resolved to the shared intersection
 | helper namespace instead of the class itself, so the map threw at runtime.
 |
 | Aliasing bare `maplibre-gl` to MapLibre's UNMINIFIED distribution avoids the
 | trigger: the dev build does not use the shadowed-name form, so the bundler has
 | nothing to rewrite. It is the same library and the same version — only the
 | distribution file differs.
 |
 | REMOVE THIS ONLY AFTER webpack is upgraded past the defect AND the proof page
 | has been re-verified in a browser. Dropping it on the strength of a green test
 | run alone would silently restore the broken binding.
 */
/*
 | MapLibre's worker, published beside the bundle.
 |
 | MapLibre resolves its worker URL from `import.meta.url`, which webpack bakes
 | into a build-time file:// path — it fails MapLibre's `^https?:` guard, and the
 | worker silently never starts. The entry point points setWorkerUrl() at this
 | copied asset instead. maplibre-gl-worker.mjs imports maplibre-gl-shared.mjs by
 | relative path, so both must sit side by side.
 |
 | Copied verbatim, not bundled: webpack never parses them, so the minified
 | distribution is safe here even though the main thread needs the alias below.
 */
mix.copy(
    [
        'node_modules/maplibre-gl/dist/maplibre-gl-worker.mjs',
        'node_modules/maplibre-gl/dist/maplibre-gl-shared.mjs',
    ],
    'public/js/spatial'
);

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
