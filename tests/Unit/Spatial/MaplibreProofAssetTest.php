<?php

namespace Tests\Unit\Spatial;

use Tests\TestCase;

/**
 * Phase 2A — static guarantees about the proof bundle.
 *
 * These assert on source text rather than on a compiled bundle. That is
 * deliberate: `npm run production` is not runnable in every checkout, and the
 * properties that matter here — the protocol is registered, no Google or
 * Nominatim host is reachable, the archive URL is not hardcoded, there is no
 * fallback provider — are properties of the source, so they should be enforced
 * without a build step standing between the change and the failure.
 */
class MaplibreProofAssetTest extends TestCase
{
    private function proofJs(): string
    {
        return file_get_contents(base_path('resources/js/spatial/maplibre-proof.js'));
    }

    /**
     * The proof source with comments removed.
     *
     * The file documents the very mistakes these tests guard against — including
     * the broken default import, written out verbatim — so a naive match on the
     * raw text asserts on prose rather than on code. Strips block comments and
     * whole-line `//` comments; trailing `//` is left alone so the `pmtiles://`
     * URL is not truncated.
     */
    private function proofCode(): string
    {
        $withoutBlocks = preg_replace('#/\*.*?\*/#s', '', $this->proofJs());

        return implode("\n", array_filter(
            explode("\n", $withoutBlocks),
            fn ($line) => ! str_starts_with(ltrim($line), '//')
        ));
    }

    /**
     * The Mix config with comments removed.
     *
     * Same reasoning as proofCode(): the build config documents the webpack
     * defect it works around and names `maplibre-gl` in that prose, so matching
     * the raw text would let a deleted alias keep passing on the strength of the
     * comment that describes it.
     */
    private function mixConfigCode(): string
    {
        $withoutBlocks = preg_replace(
            '#/\*.*?\*/#s',
            '',
            file_get_contents(base_path('webpack.mix.js'))
        );

        return implode("\n", array_filter(
            explode("\n", $withoutBlocks),
            fn ($line) => ! str_starts_with(ltrim($line), '//')
        ));
    }

    /** @test */
    public function the_frontend_manifest_declares_maplibre_and_pmtiles(): void
    {
        $package = json_decode(file_get_contents(base_path('package.json')), true);
        $declared = array_merge(
            $package['dependencies'] ?? [],
            $package['devDependencies'] ?? []
        );

        $this->assertArrayHasKey('maplibre-gl', $declared);
        $this->assertArrayHasKey('pmtiles', $declared);
    }

    /** @test */
    public function the_lockfile_pins_maplibre_and_pmtiles(): void
    {
        $lock = json_decode(file_get_contents(base_path('package-lock.json')), true);

        $this->assertArrayHasKey('node_modules/maplibre-gl', $lock['packages']);
        $this->assertArrayHasKey('node_modules/pmtiles', $lock['packages']);
    }

    /** @test */
    public function the_build_registers_the_isolated_proof_entry_point(): void
    {
        $mixConfig = file_get_contents(base_path('webpack.mix.js'));

        $this->assertStringContainsString(
            "mix.js('resources/js/spatial/maplibre-proof.js', 'public/js/spatial')",
            $mixConfig
        );
    }

    /** @test */
    public function the_build_aliases_maplibre_to_its_unminified_distribution(): void
    {
        $code = $this->mixConfigCode();

        // webpack 5.74.0 miscompiles the named class-expression shadowing used in
        // MapLibre 6.0.0's minified ESM distribution, so `maplibre-gl` must
        // resolve to the unminified build instead. Asserted on the alias
        // declaration itself — never on a generated identifier, which changes
        // every time the bundler renumbers its scopes.
        $this->assertMatchesRegularExpression(
            '/resolve\s*:\s*\{.*?alias\s*:\s*\{.*?[\'"]maplibre-gl\$?[\'"]\s*:/s',
            $code,
            'webpack.mix.js must alias bare `maplibre-gl` inside resolve.alias.'
        );

        $this->assertMatchesRegularExpression(
            '#[\'"]node_modules/maplibre-gl/dist/maplibre-gl-dev\.mjs[\'"]#',
            $code,
            'The maplibre-gl alias must target the unminified dev distribution.'
        );

        // A path that no longer exists would fail deep inside the build with an
        // opaque resolver error, so check it here — but only where dependencies
        // are installed, so an install-less checkout still runs the rest.
        if (is_dir(base_path('node_modules/maplibre-gl'))) {
            $this->assertFileExists(
                base_path('node_modules/maplibre-gl/dist/maplibre-gl-dev.mjs')
            );
        }
    }

    /** @test */
    public function the_build_publishes_and_references_the_maplibre_worker(): void
    {
        $mix = $this->mixConfigCode();
        $js = $this->proofCode();

        // MapLibre derives its worker URL from import.meta.url, which webpack
        // resolves at build time to a file:// path. That fails MapLibre's own
        // ^https?: guard, so `new Worker('')` loads the HTML page instead of a
        // script and dies on an error event nothing listens for — the map paints
        // its background layer and never requests a single tile, silently. The
        // build must publish the worker and the entry must point setWorkerUrl at
        // it. Asserted on the published path, not on any bundled identifier.
        $this->assertMatchesRegularExpression(
            '#mix\.copy\(.*maplibre-gl-worker\.mjs.*public/js/spatial#s',
            $mix,
            'webpack.mix.js must publish maplibre-gl-worker.mjs beside the bundle.'
        );

        // The worker imports the shared chunk by relative path, so it is only
        // usable if that sits next to it.
        $this->assertStringContainsString(
            'maplibre-gl-shared.mjs',
            $mix,
            'The worker imports maplibre-gl-shared.mjs — it must be published too.'
        );

        $this->assertStringContainsString(
            'setWorkerUrl(',
            $js,
            'The entry point must configure MapLibre\'s worker URL explicitly.'
        );
        $this->assertMatchesRegularExpression(
            '#[\'"]/js/spatial/maplibre-gl-worker\.mjs[\'"]#',
            $js,
            'setWorkerUrl must point at the published worker asset.'
        );

        // Where a build exists, the referenced assets must really be published —
        // a path the build never emits is a 404 and a dead worker.
        if (file_exists(public_path('js/spatial/maplibre-proof.js'))) {
            $this->assertFileExists(public_path('js/spatial/maplibre-gl-worker.mjs'));
            $this->assertFileExists(public_path('js/spatial/maplibre-gl-shared.mjs'));
        }
    }

    /** @test */
    public function maplibre_is_not_imported_into_the_application_bundle(): void
    {
        $appJs = file_get_contents(base_path('resources/js/app.js'));

        $this->assertStringNotContainsString('maplibre', $appJs);
        $this->assertStringNotContainsString('pmtiles', $appJs);
    }

    /** @test */
    public function the_proof_bundle_imports_maplibre_and_pmtiles(): void
    {
        $js = $this->proofJs();

        $this->assertStringContainsString("from 'maplibre-gl'", $js);
        $this->assertStringContainsString("from 'pmtiles'", $js);
    }

    /** @test */
    public function the_proof_bundle_uses_named_maplibre_imports_only(): void
    {
        $code = $this->proofCode();

        // maplibre-gl v6 publishes no default export. A default import yields
        // undefined and every call on it throws — and webpack reports this only
        // as a warning, so the build still exits 0 with a broken bundle. This
        // test is the thing that actually fails.
        $this->assertDoesNotMatchRegularExpression(
            '/import\s+\w+\s*(,|from)\s*[^\n]*[\'"]maplibre-gl[\'"]/',
            $code,
            'maplibre-gl must be imported by name, never as a default export.'
        );
        $this->assertMatchesRegularExpression(
            '/import\s*\{[^}]*\}\s*from\s*[\'"]maplibre-gl[\'"]/',
            $code
        );
        $this->assertStringNotContainsString('maplibregl.', $code);
    }

    /** @test */
    public function every_blade_mix_reference_exists_in_the_build_manifest(): void
    {
        $manifestPath = public_path('mix-manifest.json');

        // Build output is not committed for this feature, so on a checkout where
        // `npm run production` has not run there is nothing to compare against.
        // Once the bundle exists — in CI, or locally after a build — the check is
        // live, and it is what catches a Blade mix() path the build never emits.
        if (! file_exists($manifestPath) || ! file_exists(public_path('js/spatial/maplibre-proof.js'))) {
            $this->markTestSkipped('Proof bundle not built — run `npm run production` to enable this check.');
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $blade = file_get_contents(base_path('resources/views/spatial/maplibre-proof.blade.php'));

        preg_match_all("/mix\('([^']+)'\)/", $blade, $matches);
        $this->assertNotEmpty($matches[1], 'The proof page should reference at least one built asset.');

        foreach ($matches[1] as $asset) {
            $this->assertArrayHasKey(
                '/' . ltrim($asset, '/'),
                $manifest,
                "The Blade view references {$asset}, which the build does not emit."
            );
        }
    }

    /** @test */
    public function the_proof_bundle_registers_the_pmtiles_protocol(): void
    {
        $js = $this->proofJs();

        $this->assertStringContainsString('new Protocol()', $js);
        $this->assertMatchesRegularExpression(
            '/addProtocol\(\s*[\'"]pmtiles[\'"]/',
            $js,
            'The pmtiles:// protocol must be registered with MapLibre.'
        );
        $this->assertStringContainsString('pmtiles://', $js);
    }

    /** @test */
    public function the_proof_bundle_reads_the_archive_url_from_a_data_attribute(): void
    {
        $js = $this->proofJs();

        $this->assertStringContainsString('container.dataset.pmtilesUrl', $js);

        // No literal archive host may be embedded — the URL is configuration.
        $this->assertDoesNotMatchRegularExpression(
            '/https?:\/\/[^\s\'"]*\.pmtiles/',
            $js,
            'The archive URL must come from configuration, not be hardcoded.'
        );
        $this->assertStringNotContainsString('r2.dev', $js);
    }

    /** @test */
    public function the_proof_bundle_contains_no_credential(): void
    {
        $js = $this->proofJs();

        foreach (['ACCESS_KEY', 'SECRET', 'accessKeyId', 'secretAccessKey', 'Authorization'] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $js,
                "The browser bundle must carry no credential material ({$token})."
            );
        }
    }

    /** @test */
    public function the_proof_bundle_reaches_no_google_or_nominatim_endpoint(): void
    {
        $sources = [
            'resources/js/spatial/maplibre-proof.js',
            'resources/js/spatial/maplibre-proof.css',
            'resources/views/spatial/maplibre-proof.blade.php',
            'config/spatial_basemap.php',
        ];

        foreach ($sources as $source) {
            $contents = file_get_contents(base_path($source));

            foreach ([
                'maps.googleapis.com',
                'places.googleapis.com',
                'google.maps',
                'nominatim.openstreetmap.org',
            ] as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $contents,
                    "{$source} must not reference {$forbidden}."
                );
            }
        }
    }

    /** @test */
    public function the_proof_bundle_declares_an_explicit_error_state(): void
    {
        $js = $this->proofJs();

        $this->assertStringContainsString('showError', $js);
        $this->assertStringContainsString("map.on('error'", $js);

        // The error panel must exist in the server-rendered markup.
        $blade = file_get_contents(base_path('resources/views/spatial/maplibre-proof.blade.php'));
        $this->assertStringContainsString('data-maplibre-proof-error', $blade);
    }

    /** @test */
    public function the_proof_bundle_configures_no_fallback_tile_source(): void
    {
        $js = $this->proofJs();

        // Exactly one source, and it is the configured PMTiles archive. A raster
        // source or a second vector source would mean a silent fallback, which
        // would disguise a broken archive as a working basemap.
        //
        // Counts the URL *construction* rather than every `pmtiles://` in the
        // file — the prose above explains the scheme and would otherwise inflate
        // the count, making this assert on comment text instead of on behaviour.
        $this->assertSame(
            1,
            preg_match_all('/pmtiles:\/\/\$\{archiveUrl\}/', $js),
            'The style must build exactly one PMTiles source URL.'
        );

        // And exactly one entry in the style's `sources` map.
        $this->assertSame(
            1,
            preg_match_all('/^\s+type: \'vector\',$/m', $js),
            'The style must declare exactly one vector source.'
        );
        $this->assertStringNotContainsString("type: 'raster'", $js);
        $this->assertStringNotContainsString('tile.openstreetmap.org', $js);
    }

    /** @test */
    public function the_style_requests_no_third_party_font_or_sprite_host(): void
    {
        $js = $this->proofJs();

        // A `glyphs` or `sprite` URL would add egress to a font/sprite host, so
        // the style carries neither and therefore no symbol layer.
        $this->assertStringNotContainsString('glyphs:', $js);
        $this->assertStringNotContainsString('sprite:', $js);
        $this->assertStringNotContainsString("type: 'symbol'", $js);
    }

    /** @test */
    public function the_configuration_reads_no_secret_environment_value(): void
    {
        $config = file_get_contents(base_path('config/spatial_basemap.php'));

        // The bucket's S3 credentials are server-side only and must never be
        // resolvable from the config that feeds the browser.
        $this->assertStringNotContainsString("env('BASEMAP_R2_ACCESS_KEY_ID", $config);
        $this->assertStringNotContainsString("env('BASEMAP_R2_SECRET_ACCESS_KEY", $config);
    }
}
