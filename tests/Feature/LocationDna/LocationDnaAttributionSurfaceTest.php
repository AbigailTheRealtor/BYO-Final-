<?php

namespace Tests\Feature\LocationDna;

use App\Support\LocationDna\LocationDataAttribution;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The user-visible half of the attribution obligation: the reusable component, the
 * public licenses page, and the separation from the MLS attribution block.
 *
 * WHY THE SEPARATION IS TESTED RATHER THAN TRUSTED. A listing page can owe two
 * unrelated attributions at once — Stellar/Bridge for the LISTING under IDX terms,
 * Overture/Google for the PLACES beside it under open-data licenses. They look
 * alike and sit on the same page, so the standing temptation is to merge them into
 * one "data sources" line. That would let a reader take the Stellar copyright as
 * covering the nearby-restaurants list, or the Overture credit as covering the
 * listing — a false provenance claim in either direction.
 */
class LocationDnaAttributionSurfaceTest extends TestCase
{
    // The base TestCase migrates the shared in-memory schema only for classes that
    // declare this trait, and these tests render real pages — `layouts.main` reads
    // `settings` through get_setting(). Without it the page 500s on a missing table
    // and the attribution assertions never get to run.
    use DatabaseTransactions;

    private const COMPONENT = 'resources/views/partials/location-dna/_data-attribution.blade.php';
    private const PANEL     = 'resources/views/partials/location-dna-agent-panel.blade.php';
    private const MLS_BLOCK = 'resources/views/offer-listing/partials/_mls_attribution.blade.php';

    // ── The NOTICE artifacts ────────────────────────────────────────────────

    /** @test */
    public function the_repository_notice_file_exists_and_states_the_apache_obligation(): void
    {
        $path = base_path((string) config('location_attribution.notice_path'));

        $this->assertFileExists($path, 'The repository NOTICE file is missing.');

        $notice = (string) file_get_contents($path);

        $this->assertStringContainsString('Overture Maps Foundation', $notice);
        $this->assertStringContainsString('Foursquare', $notice);
        $this->assertStringContainsString('Apache-2.0', $notice);
        $this->assertStringContainsString('CDLA-Permissive-2.0', $notice);

        // The specific misconception the NOTICE has to head off.
        $this->assertMatchesRegularExpression(
            '/not ODbL|NOT ODbL|is not one of them/i',
            $notice,
            'The NOTICE does not state that the Places theme is not ODbL.'
        );
    }

    /** @test */
    public function the_foursquare_notice_file_holds_the_upstream_text_and_not_a_placeholder(): void
    {
        // It was a placeholder while the upstream bytes were unavailable, because an
        // invented NOTICE discharges nothing and puts words in Foursquare's mouth.
        // Now that the real text is committed, the placeholder must be gone — a file
        // that still announced itself as one would mean the obligation is unmet while
        // the config claims otherwise.
        $path = base_path((string) LocationDataAttribution::source('overture_places')['notice_path']);

        $this->assertFileExists($path);

        $notice = (string) file_get_contents($path);

        $this->assertStringNotContainsString('PLACEHOLDER', $notice);
        $this->assertStringContainsString('Foursquare OS Places Notice', $notice);
        $this->assertStringContainsString('Foursquare Labs, Inc. All rights reserved.', $notice);
    }

    // ── The public licenses page ────────────────────────────────────────────

    /** @test */
    public function the_data_sources_page_is_publicly_reachable(): void
    {
        // Unauthenticated on purpose: the pages that publish this data
        // (/offer-listing/{seller,landlord}/view/{id}) are themselves outside the
        // auth group, so an attribution page behind a login would discharge nothing
        // for the visitors actually being shown the data.
        $response = $this->get(route('data-sources'));

        $response->assertOk();
    }

    /** @test */
    public function the_data_sources_page_names_every_configured_source_and_license(): void
    {
        $response = $this->get(route('data-sources'));

        // Escaped comparison (assertSee's default): Blade escapes these on output, so
        // "TIGER/Line & Geocoder" reaches the page as "TIGER/Line &amp; Geocoder".
        // Asserting on the raw string would fail for a correctly rendered page.
        foreach (LocationDataAttribution::allSources() as $id => $source) {
            $response->assertSee($source['name']);

            foreach ($source['licenses'] as $license) {
                $response->assertSee($license['name']);
            }
        }
    }

    /** @test */
    public function the_data_sources_page_reproduces_the_notice_file(): void
    {
        // Apache-2.0 §4(d) is discharged by the notice being READABLE where the data
        // is published, not by the file existing in the repository.
        $notice = (string) file_get_contents(base_path((string) config('location_attribution.notice_path')));

        $this->get(route('data-sources'))
            ->assertSee('Foursquare Open Source Places', false)
            ->assertSee(trim(explode("\n", $notice)[0]), false);
    }

    // ── The reusable component ──────────────────────────────────────────────

    /** @test */
    public function the_component_renders_nothing_when_no_attribution_is_owed(): void
    {
        // No POIs, and POIs whose rows record no provider, must both produce no
        // markup — not a default credit.
        $this->assertSame('', trim($this->renderComponent([])));

        $this->assertSame('', trim($this->renderComponent([
            (object) ['poi_name' => 'Somewhere', 'provenance_json' => null],
        ])));
    }

    /** @test */
    public function the_component_renders_overture_attribution_for_corpus_rows(): void
    {
        $html = $this->renderComponent([
            (object) ['poi_name' => 'Corner Store', 'provenance_json' => ['provider' => 'overture_corpus']],
        ]);

        $this->assertStringContainsString('Overture Maps Foundation', $html);
        $this->assertStringContainsString(route('data-sources'), $html);
    }

    /** @test */
    public function the_component_does_not_credit_google_for_corpus_rows(): void
    {
        $html = $this->renderComponent([
            (object) ['poi_name' => 'Corner Store', 'provenance_json' => ['provider' => 'overture_corpus']],
        ]);

        $this->assertStringNotContainsString('Google', $html);
    }

    // ── Separation from the MLS attribution ─────────────────────────────────

    /** @test */
    public function the_mls_attribution_block_was_not_turned_into_a_generic_component(): void
    {
        $mls = (string) file_get_contents(base_path(self::MLS_BLOCK));

        // It must still be about the listing's own MLS provenance, and must not have
        // acquired the POI attribution's vocabulary.
        $this->assertStringContainsString('Stellar MLS', $mls);
        $this->assertStringNotContainsString('Overture', $mls);
        $this->assertStringNotContainsString('LocationDataAttribution', $mls);
    }

    /** @test */
    public function the_location_dna_component_makes_no_mls_provenance_claim(): void
    {
        // Asserted on the RENDERED output rather than the file, because the partial's
        // own comment names the MLS block in order to explain why the two are kept
        // apart — and a Blade comment reaches no reader. What must never appear is an
        // MLS provenance claim in the markup the POI attribution actually emits.
        $html = $this->renderComponent([
            (object) ['poi_name' => 'Corner Store', 'provenance_json' => ['provider' => 'overture_corpus']],
        ]);

        $this->assertNotSame('', trim($html), 'Fixture rendered nothing, so the assertions below are vacuous.');
        $this->assertStringNotContainsString('Stellar', $html);
        $this->assertStringNotContainsString('Bridge Data Output', $html);
        $this->assertStringNotContainsString('deemed reliable', $html);
    }

    /** @test */
    public function the_shared_panel_includes_the_attribution_component(): void
    {
        // The panel is the one file both the seller and landlord public listing
        // pages include, so wiring it here is what gives every role the notice —
        // and is why neither role's view file needed to change.
        $panel = (string) file_get_contents(base_path(self::PANEL));

        $this->assertStringContainsString('partials.location-dna._data-attribution', $panel);
    }

    /** @test */
    public function the_attribution_config_has_exactly_the_declared_readers(): void
    {
        // The same discipline config/hire_agent_compatibility_keys.php and
        // config/landlord_screening_options.php are held to: a licensing SSOT with
        // an unlisted reader is one that can be bypassed.
        // The page itself is NOT a reader: routes/web.php resolves the sources and the
        // notice path and hands them to the view, so data-sources.blade.php renders
        // what it is given. That keeps the "which sources exist" decision in one place
        // instead of letting a template query the licensing config directly.
        $expected = [
            'app/Support/LocationDna/LocationDataAttribution.php',
            'routes/web.php',
        ];

        $hits = [];

        foreach ([base_path('app'), base_path('resources/views'), base_path('routes')] as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($iterator as $file) {
                if (! $file->isFile() || ! in_array($file->getExtension(), ['php'], true)) {
                    continue;
                }

                $contents = (string) file_get_contents($file->getPathname());

                if (str_contains($contents, "config('location_attribution")
                    || str_contains($contents, 'config("location_attribution')) {
                    $hits[] = str_replace(base_path() . '/', '', $file->getPathname());
                }
            }
        }

        sort($hits);
        sort($expected);

        $this->assertSame($expected, $hits, 'config/location_attribution.php gained or lost a reader.');
    }

    /**
     * Render the attribution partial in isolation.
     *
     * @param  array<int, object> $pois
     */
    private function renderComponent(array $pois): string
    {
        return view('partials.location-dna._data-attribution', ['pois' => $pois])->render();
    }
}
