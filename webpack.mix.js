const mix = require('laravel-mix');

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
