<?php

namespace Tests\Feature\LocationDna;

use App\Support\Spatial\LdnaBasemapSurface;
use Tests\TestCase;

/**
 * The gate itself.
 *
 * Phase 1 shipped `config/spatial_basemap.php` with NO PHP reader at all — the two flags
 * governed nothing, so there was nothing for a test to assert. This suite exists because
 * the gate now decides which renderer writes a listing's geography, and a gate that is
 * wrong in the permissive direction silently swaps the renderer under an editing surface.
 *
 * Everything here asserts BEHAVIOUR through the real method, never the shape of the config.
 */
class LdnaBasemapSurfaceTest extends TestCase
{
    private function configure(bool $enabled, array $surfaces): void
    {
        config([
            'spatial_basemap.maplibre_renderer_enabled'  => $enabled,
            'spatial_basemap.maplibre_renderer_surfaces' => $surfaces,
        ]);
    }

    public function test_shipped_defaults_leave_every_surface_on_the_incumbent_renderer(): void
    {
        // No config() override: whatever the repository ships is what is asserted.
        foreach (LdnaBasemapSurface::SURFACES as $surface) {
            $this->assertFalse(
                LdnaBasemapSurface::enabledFor($surface),
                "MapLibre must ship OFF for '{$surface}' — enabling a renderer that writes "
                . 'listing geography is a reviewed decision, not a default.'
            );
        }
    }

    public function test_both_gates_must_agree(): void
    {
        $this->configure(false, [LdnaBasemapSurface::CREATE_BUYER]);
        $this->assertFalse(
            LdnaBasemapSurface::enabledFor(LdnaBasemapSurface::CREATE_BUYER),
            'the surface allowlist alone must not enable the renderer'
        );

        $this->configure(true, []);
        $this->assertFalse(
            LdnaBasemapSurface::enabledFor(LdnaBasemapSurface::CREATE_BUYER),
            'the master switch alone must not enable the renderer — an operator who turns it '
            . 'on without naming a surface must get no change, not all eight at once.'
        );

        $this->configure(true, [LdnaBasemapSurface::CREATE_BUYER]);
        $this->assertTrue(LdnaBasemapSurface::enabledFor(LdnaBasemapSurface::CREATE_BUYER));
    }

    public function test_naming_one_surface_does_not_enable_its_neighbours(): void
    {
        $this->configure(true, [LdnaBasemapSurface::CREATE_BUYER]);

        $this->assertTrue(LdnaBasemapSurface::enabledFor(LdnaBasemapSurface::CREATE_BUYER));

        foreach (array_diff(LdnaBasemapSurface::SURFACES, [LdnaBasemapSurface::CREATE_BUYER]) as $other) {
            $this->assertFalse(
                LdnaBasemapSurface::enabledFor($other),
                "'{$other}' must stay on the incumbent renderer while only create_buyer is named"
            );
        }
    }

    public function test_an_unrecognised_surface_can_never_be_enabled(): void
    {
        // The typo case, and the "widen it from the environment" case, are the same case.
        $this->configure(true, ['create_buyerr', 'everything', '*', 'display']);

        $this->assertFalse(LdnaBasemapSurface::enabledFor('create_buyerr'));
        $this->assertFalse(LdnaBasemapSurface::enabledFor('everything'));
        $this->assertFalse(LdnaBasemapSurface::enabledFor('*'));
        $this->assertFalse(
            LdnaBasemapSurface::enabledFor(null),
            'a host that passes no surface key must keep the incumbent renderer'
        );

        // The one recognised entry still works — an unknown neighbour is inert, not fatal.
        $this->assertTrue(LdnaBasemapSurface::enabledFor(LdnaBasemapSurface::DISPLAY));
        $this->assertSame([LdnaBasemapSurface::DISPLAY], LdnaBasemapSurface::surfaces());
    }

    public function test_a_config_that_did_not_load_reads_as_off(): void
    {
        // Not "false" — ABSENT. A config file that failed to load is indistinguishable from
        // one that requires nothing, and the safe reading of that ambiguity is "off".
        config(['spatial_basemap' => null]);

        $this->assertFalse(LdnaBasemapSurface::enabled());
        $this->assertSame([], LdnaBasemapSurface::surfaces());

        foreach (LdnaBasemapSurface::SURFACES as $surface) {
            $this->assertFalse(LdnaBasemapSurface::enabledFor($surface));
        }
    }

    public function test_a_non_array_surface_list_reads_as_empty_rather_than_raising(): void
    {
        $this->configure(true, []);
        config(['spatial_basemap.maplibre_renderer_surfaces' => 'create_buyer']);

        $this->assertSame([], LdnaBasemapSurface::surfaces());
        $this->assertFalse(LdnaBasemapSurface::enabledFor(LdnaBasemapSurface::CREATE_BUYER));
    }

    public function test_master_switch_reader_is_not_a_gate(): void
    {
        $this->configure(true, []);

        $this->assertTrue(
            LdnaBasemapSurface::enabled(),
            'enabled() answers the master switch alone, so a diagnostic can report the posture'
        );
        $this->assertFalse(
            LdnaBasemapSurface::enabledFor(LdnaBasemapSurface::DISPLAY),
            'and it must never be what a surface renders from'
        );
    }

    public function test_a_null_archive_is_a_supported_state(): void
    {
        config(['spatial_basemap.pmtiles_url' => null]);

        $this->assertNull(LdnaBasemapSurface::pmtilesUrl());
        $this->assertFalse(LdnaBasemapSurface::hasBasemapArchive());
        $this->assertSame('', LdnaBasemapSurface::containerAttributes()['pmtiles-url']);
    }

    public function test_attribution_is_never_empty(): void
    {
        // OSM under ODbL plus a Protomaps archive is a Produced Work; attribution is the
        // licence condition for displaying it. An unreadable config must not strip it.
        config(['spatial_basemap.attribution' => '']);
        $this->assertStringContainsString('OpenStreetMap', LdnaBasemapSurface::attribution());

        config(['spatial_basemap' => null]);
        $this->assertStringContainsString('OpenStreetMap', LdnaBasemapSurface::attribution());
        $this->assertStringContainsString('OpenStreetMap', LdnaBasemapSurface::containerAttributes()['attribution']);
    }

    public function test_container_attributes_carry_no_credential(): void
    {
        $serialised = json_encode(LdnaBasemapSurface::containerAttributes());

        foreach (['ACCESS_KEY', 'SECRET', 'access_key', 'secret', 'Authorization'] as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                (string) $serialised,
                'the container is public markup; the archive is public and read-only and no '
                . 'credential may pass through these attributes'
            );
        }
    }

    public function test_the_bundle_url_is_cache_busted_but_never_throws(): void
    {
        // Cache-busted when the manifest can say so: the bundle is a versioned build
        // artefact and a browser holding yesterday's copy after a deploy is a real failure.
        $this->assertStringContainsString(LdnaBasemapSurface::BUNDLE_PATH, LdnaBasemapSurface::bundleUrl());

        // And never a 500. `mix()` THROWS on a missing manifest entry, which on a listing
        // page would take the whole page down over one script tag. A missing build artefact
        // must degrade to a 404 on that script — the container still renders and reports
        // its empty state — not to an error page.
        $manifest = public_path('mix-manifest.json');
        $saved = file_exists($manifest) ? file_get_contents($manifest) : null;

        try {
            file_put_contents($manifest, json_encode(['/js/app.js' => '/js/app.js']));
            $url = LdnaBasemapSurface::bundleUrl();
            $this->assertStringContainsString(LdnaBasemapSurface::BUNDLE_PATH, $url);
        } finally {
            if ($saved !== null) {
                file_put_contents($manifest, $saved);
            }
        }
    }

    public function test_the_support_class_is_the_only_php_reader_of_the_config(): void
    {
        // The single-reader rule, asserted rather than left to reviewer memory. A second
        // reader is how the markup and the serialiser come to disagree about the renderer.
        $roots = [base_path('app'), base_path('resources/views'), base_path('routes')];
        $readers = [];

        foreach ($roots as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($iterator as $file) {
                if (! $file->isFile() || ! in_array($file->getExtension(), ['php'], true)) {
                    continue;
                }
                $contents = file_get_contents($file->getPathname());

                // A READ, not a mention. Prose about the config — the panel partial's own
                // docblock says it deliberately does not read it — is not a second reader,
                // and a test that cannot tell those apart makes the comment unwritable.
                if (preg_match('/config\\(\\s*[\'"]spatial_basemap/', $contents)) {
                    $readers[] = str_replace(base_path() . '/', '', $file->getPathname());
                }
            }
        }

        sort($readers);

        $this->assertSame(
            ['app/Support/Spatial/LdnaBasemapSurface.php'],
            $readers,
            'config/spatial_basemap.php must have exactly one reader. Found: ' . implode(', ', $readers)
        );
    }
}
