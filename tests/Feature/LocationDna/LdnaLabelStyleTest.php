<?php

namespace Tests\Feature\LocationDna;

use Tests\TestCase;

/**
 * The label style, checked against the two things it depends on and cannot see:
 * the fonts on disk and the archive's own schema.
 *
 * WHY A PHP TEST FOR A JAVASCRIPT STYLE
 * -------------------------------------
 * Because the failures this catches are not JavaScript failures. A `text-font`
 * naming a stack nobody shipped, a `source-layer` the archive does not contain,
 * or a glyph URL pointing at somebody else's server all produce a map that
 * loads, runs, throws nothing and simply has no labels — which is
 * indistinguishable, from inside the browser, from a map that was never asked
 * for any. The browser suite (`tests/browser/label-style.spec.js`) proves the
 * renderer survives; this proves the style asks for things that exist.
 *
 * It reads the source module as text rather than executing it. That is the
 * point: no build step, no bundler, no MapLibre — this suite runs everywhere,
 * including the container where `npm install` cannot complete.
 */
class LdnaLabelStyleTest extends TestCase
{
    /** Source layers the Florida archive publishes, from its own PMTiles metadata. */
    private const ARCHIVE_SOURCE_LAYERS = [
        'boundaries', 'buildings', 'earth', 'landcover', 'landuse', 'places', 'pois', 'roads', 'water',
    ];

    private function basemapSource(): string
    {
        $path = base_path('resources/js/spatial/ldna-basemap.js');
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    // ── glyphs ──────────────────────────────────────────────────────────────

    public function test_the_style_declares_a_glyph_source(): void
    {
        // Without this key MapLibre renders no `text-field` at all, and does so
        // silently: every symbol layer below would be inert.
        $this->assertStringContainsString("export const GLYPH_URL = '/fonts/{fontstack}/{range}.pbf'", $this->basemapSource());
        $this->assertStringContainsString('glyphs,', $this->basemapSource());
    }

    public function test_the_glyph_url_is_same_origin_and_names_no_third_party_host(): void
    {
        $source = $this->basemapSource();

        // Root-relative, so it follows whatever host served the page. The whole
        // reason labels were absent before was a refusal to depend on somebody
        // else's server, and that constraint has not been relaxed — it has been
        // satisfied.
        foreach ([
            'protomaps.github.io',
            'api.mapbox.com',
            'demotiles.maplibre.org',
            'fonts.googleapis.com',
            'cdn.jsdelivr.net',
            'unpkg.com',
            'https://',
        ] as $host) {
            $this->assertStringNotContainsString(
                $host,
                $this->glyphAndFontRegion($source),
                "the glyph configuration reaches out to {$host}"
            );
        }
    }

    public function test_every_font_the_style_names_is_shipped_with_the_application(): void
    {
        $source = $this->basemapSource();

        preg_match_all("/'text-font':\s*(FONT_[A-Z]+|\[[^\]]*\])/", $source, $matches);
        $this->assertNotEmpty($matches[1], 'no text-font declarations found');

        $stacks = ['FONT_REGULAR' => 'Noto Sans Regular', 'FONT_MEDIUM' => 'Noto Sans Medium'];

        foreach ($matches[1] as $declared) {
            $this->assertArrayHasKey(
                $declared,
                $stacks,
                "a label layer names a font stack outside the two shipped constants: {$declared}"
            );
        }

        foreach ($stacks as $constant => $stack) {
            $dir = public_path('fonts/' . $stack);

            $this->assertDirectoryExists($dir, "font stack {$stack} is named by the style but not shipped");

            // The Latin ranges US labels actually request. A missing range is not
            // a crash — the glyphs in it simply do not draw — which is exactly
            // why it needs asserting rather than noticing.
            foreach (['0-255', '256-511', '8192-8447'] as $range) {
                $this->assertFileExists($dir . '/' . $range . '.pbf', "{$stack} is missing range {$range}");
            }
        }
    }

    public function test_the_font_licence_ships_beside_the_fonts(): void
    {
        $licence = public_path('fonts/OFL.txt');

        $this->assertFileExists($licence, 'the SIL Open Font License must ship with the glyphs');
        $this->assertStringContainsString('SIL OPEN FONT LICENSE', (string) file_get_contents($licence));
    }

    // ── layers ──────────────────────────────────────────────────────────────

    public function test_the_expected_label_layers_exist(): void
    {
        $source = $this->basemapSource();

        foreach ([
            'place-label-locality',              // cities and towns
            'place-label-locality-minor',        // small localities
            'place-label-neighbourhood',         // neighbourhoods
            'road-label-major',                  // highways and major roads
            'road-label-minor',                  // ordinary streets
            'road-label-shield',                 // route numbers
            'water-label',                       // water bodies
            'poi-label',                         // parks, schools, landmarks
        ] as $layerId) {
            $this->assertStringContainsString("id: '{$layerId}'", $source, "missing label layer: {$layerId}");
        }
    }

    public function test_every_label_layer_is_a_symbol_layer_with_a_text_field(): void
    {
        $source = $this->basemapSource();

        $symbolCount = substr_count($source, "type: 'symbol'");
        $textFields  = substr_count($source, "'text-field':");

        $this->assertGreaterThanOrEqual(8, $symbolCount);
        $this->assertSame($symbolCount, $textFields, 'a symbol layer carries no text-field');
    }

    public function test_the_style_reads_only_source_layers_the_archive_contains(): void
    {
        $source = $this->basemapSource();

        preg_match_all("/'source-layer':\s*'([a-z_]+)'/", $source, $matches);
        $this->assertNotEmpty($matches[1]);

        foreach (array_unique($matches[1]) as $layer) {
            $this->assertContains(
                $layer,
                self::ARCHIVE_SOURCE_LAYERS,
                "the style reads a source layer the archive does not publish: {$layer}"
            );
        }
    }

    public function test_the_label_source_layers_are_the_four_that_carry_names(): void
    {
        // `boundaries`, `landuse`, `landcover` and `buildings` carry no `name`
        // in this archive; a text layer over any of them would render nothing.
        $this->assertStringContainsString(
            "export const LABEL_SOURCE_LAYERS = ['water', 'roads', 'pois', 'places'];",
            $this->basemapSource()
        );
    }

    public function test_house_numbers_are_not_labelled(): void
    {
        // Sparse in OSM and visually noisy at exactly the zoom a listing is
        // examined at. Deliberately omitted; the audit recommended against it.
        //
        // Asserted as "nothing reads the buildings layer" rather than "the file
        // never says addr_housenumber", because the file SHOULD say it — the
        // decision not to label house numbers is worth writing down, and a test
        // that forbade the words would forbid the explanation along with them.
        $source = $this->basemapSource();

        $this->assertStringNotContainsString("'source-layer': 'buildings'", $source);
        $this->assertStringNotContainsString("['get', 'addr_housenumber']", $source);
        $this->assertNotContains('buildings', $this->labelSourceLayers($source));
    }

    /** The source layers named by the label layers, read out of the file. */
    private function labelSourceLayers(string $source): array
    {
        $labels = substr($source, (int) strpos($source, 'function labelLayers'));

        preg_match_all("/'source-layer':\s*'([a-z_]+)'/", $labels, $matches);

        return array_values(array_unique($matches[1]));
    }

    public function test_no_sprite_is_declared(): void
    {
        // Text-only labels. A sprite would put a second asset family on the
        // critical path for icons nothing asks for.
        $this->assertStringNotContainsString("sprite:", $this->basemapSource());
    }

    public function test_the_geometry_style_can_still_be_built_without_labels(): void
    {
        // The escape hatch a caller needs when it must be certain no glyph
        // request is issued — and the thing that keeps "labels" separable from
        // "the map works" rather than fused to it.
        $this->assertStringContainsString('labels = true', $this->basemapSource());
        $this->assertStringContainsString('...(labels ? labelLayers() : [])', $this->basemapSource());
    }

    // ── independence ────────────────────────────────────────────────────────

    public function test_labels_require_nothing_from_google(): void
    {
        $source = $this->basemapSource();

        foreach (['google', 'googleapis', 'gm_authFailure', 'GOOGLE_PLACES'] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $source);
        }
    }

    public function test_the_blank_fallback_style_carries_no_labels_and_no_glyph_request(): void
    {
        $source = $this->basemapSource();

        // The no-archive path must stay minimal: it exists so geometry survives
        // when tiles cannot be read, and asking for glyphs there would add a
        // request that can only fail on a map that has already failed.
        $blank = substr($source, (int) strpos($source, 'export function buildBlankStyle'));

        $this->assertStringNotContainsString('glyphs', $blank);
        $this->assertStringNotContainsString('symbol', $blank);
    }

    /** The region of the file where glyph and font constants are declared. */
    private function glyphAndFontRegion(string $source): string
    {
        $start = strpos($source, 'export const GLYPH_URL');
        $end   = strpos($source, 'const HALO');

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return substr($source, $start, $end - $start);
    }
}
