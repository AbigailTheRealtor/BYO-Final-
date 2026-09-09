<?php

namespace Tests\Unit\LocationDna;

use App\Support\LocationDna\LocationDataAttribution;
use Tests\TestCase;

/**
 * The attribution resolver, and the licensing claims it is responsible for.
 *
 * Two of the licenses behind our POI data COMPEL attribution wherever the data is
 * published (CDLA-Permissive-2.0, Apache-2.0), and one of those additionally
 * compels reproducing the upstream NOTICE. Getting this wrong is not a rendering
 * bug — it is either a license breach or a false statement about a third party's
 * data. Both directions are pinned here.
 */
class LocationDataAttributionTest extends TestCase
{
    /** Build a POI row shaped like the persisted model, with a given provenance. */
    private function poi(?string $provider): object
    {
        return (object) [
            'poi_name'        => 'Test Place',
            'data_source'     => 'google_places', // deliberately the misleading literal
            'provenance_json' => $provider === null ? null : ['provider' => $provider],
        ];
    }

    /** @test */
    public function corpus_rows_attribute_overture_and_not_google(): void
    {
        $sources = LocationDataAttribution::forPois([$this->poi('overture_corpus')]);

        $ids = array_column($sources, 'id');

        $this->assertSame(['overture_places'], $ids);
    }

    /** @test */
    public function attribution_follows_recorded_provenance_not_the_data_source_column(): void
    {
        // `property_location_pois.data_source` is written as the literal string
        // 'google_places' regardless of which adapter answered — it predates the
        // provider registry. A resolver reading it would credit Google for every
        // corpus row: a false statement about a third party, in the one place where
        // being wrong is a license matter.
        $row = $this->poi('overture_corpus');

        $this->assertSame('google_places', $row->data_source, 'Fixture no longer reproduces the misleading column.');

        $ids = array_column(LocationDataAttribution::forPois([$row]), 'id');

        $this->assertNotContains('google_places', $ids, 'Attribution was resolved from data_source, not provenance.');
        $this->assertContains('overture_places', $ids);
    }

    /** @test */
    public function a_mixed_page_attributes_every_source_present(): void
    {
        // The state an activation actually passes through: corpus rows written
        // today sitting beside Google rows written before the switch.
        $sources = LocationDataAttribution::forPois([
            $this->poi('google_places'),
            $this->poi('overture_corpus'),
            $this->poi('overture_corpus'),
        ]);

        $ids = array_column($sources, 'id');

        $this->assertContains('overture_places', $ids);
        $this->assertContains('google_places', $ids);
        $this->assertSame(count($ids), count(array_unique($ids)), 'Sources were repeated once per row.');
    }

    /** @test */
    public function ordering_is_configuration_order_not_row_order(): void
    {
        // User-visible text must not reshuffle because one listing happened to have
        // its grocery store fetched before its pharmacy.
        $a = array_column(LocationDataAttribution::forPois([
            $this->poi('google_places'), $this->poi('overture_corpus'),
        ]), 'id');

        $b = array_column(LocationDataAttribution::forPois([
            $this->poi('overture_corpus'), $this->poi('google_places'),
        ]), 'id');

        $this->assertSame($a, $b);
    }

    /** @test */
    public function a_row_with_no_recorded_provider_attributes_nothing(): void
    {
        // A guessed attribution is a false claim about someone else's data; an
        // absent one is a gap that noticeObligationsOutstanding() can surface.
        $this->assertSame([], LocationDataAttribution::forPois([$this->poi(null)]));
        $this->assertSame([], LocationDataAttribution::forPois([]));
    }

    /** @test */
    public function an_unmapped_provider_attributes_nothing(): void
    {
        // `stub` is fixture data with no upstream to credit, and an adapter that is
        // declared but not implemented has produced no rows to attribute.
        $this->assertSame([], LocationDataAttribution::forProvider('stub'));
        $this->assertSame([], LocationDataAttribution::forProvider('osm_overpass'));
        $this->assertSame([], LocationDataAttribution::forProvider('not-a-provider'));
    }

    /** @test */
    public function provenance_is_read_when_it_arrives_as_a_json_string(): void
    {
        // The model casts provenance_json to an array; a raw query row does not.
        $raw = (object) ['provenance_json' => json_encode(['provider' => 'overture_corpus'])];

        $this->assertSame(['overture_places'], array_column(LocationDataAttribution::forPois([$raw]), 'id'));
    }

    /** @test */
    public function overture_places_is_not_declared_as_odbl(): void
    {
        // The single most likely wrong answer. Four of Overture's six themes ARE
        // ODbL and Places is not one of them; declaring share-alike here would
        // assert an obligation on our own data that the license does not create,
        // and would still miss the Apache NOTICE that it does.
        $source = LocationDataAttribution::source('overture_places');

        $this->assertNotNull($source);

        $licenseIds = array_column($source['licenses'], 'id');

        $this->assertNotContains('odbl', $licenseIds, 'Overture Places is declared ODbL. It is not.');
        $this->assertContains('cdla-permissive-2.0', $licenseIds);
        $this->assertContains('apache-2.0', $licenseIds);
        $this->assertContains('cc0-1.0', $licenseIds);
    }

    /** @test */
    public function the_foursquare_notice_obligation_is_declared_and_satisfied(): void
    {
        $source = LocationDataAttribution::source('overture_places');

        $this->assertTrue($source['attribution_required'], 'Overture Places attribution is not marked required.');
        $this->assertTrue($source['notice_required'], 'The Apache-2.0 NOTICE obligation is not declared.');

        // Satisfied — but only because the artifacts are actually present. The flag is
        // not trusted on its own here: asserting it alone would let someone discharge a
        // licence obligation by editing a boolean.
        $this->assertTrue($source['notice_verified'], 'notice_verified was cleared.');

        $this->assertFileExists(base_path($source['notice_path']));
        $this->assertFileExists(base_path($source['license_path']));
        $this->assertFileExists(base_path($source['modifications_path']));

        $this->assertSame(
            [],
            LocationDataAttribution::noticeObligationsOutstanding(),
            'A NOTICE obligation is outstanding.'
        );
    }

    /** @test */
    public function the_corpus_provider_is_no_longer_blocked_by_the_notice_obligation(): void
    {
        $this->assertFalse(
            LocationDataAttribution::providerBlockedByNotice('overture_corpus'),
            'The corpus provider is still reported as blocked despite the NOTICE being committed.'
        );

        // Google's terms are terms, not an open-data license with a NOTICE file.
        // Inventing an obligation there would create a blocker that can never clear.
        $this->assertFalse(LocationDataAttribution::providerBlockedByNotice('google_places'));
        $this->assertFalse(LocationDataAttribution::providerBlockedByNotice('census_tiger'));
    }

    /**
     * @test
     *
     * A satisfied NOTICE says one prerequisite is met. It is not permission to serve
     * the corpus, and the two must not be wired together — that is exactly how a
     * licensing chore would come to flip a provider gate as a side effect.
     */
    public function a_satisfied_notice_does_not_enable_the_provider(): void
    {
        $this->assertFalse((require base_path('config/overture_corpus_poi.php'))['enabled']);
        $this->assertFalse((require base_path('config/location_providers.php'))['providers']['overture_corpus']['enabled']);
    }

    /** @test */
    public function every_source_that_requires_a_notice_names_where_it_lives(): void
    {
        foreach (LocationDataAttribution::allSources() as $id => $source) {
            if (($source['notice_required'] ?? false) !== true) {
                continue;
            }

            $this->assertNotEmpty(
                $source['notice_path'] ?? null,
                "Source '{$id}' requires a NOTICE but names no path to hold it."
            );

            $this->assertFileExists(
                base_path($source['notice_path']),
                "Source '{$id}' names a NOTICE path that does not exist."
            );
        }
    }

    /** @test */
    public function every_provider_mapping_points_at_a_declared_source(): void
    {
        $declared = array_keys(LocationDataAttribution::allSources());

        foreach ((array) config('location_attribution.provider_sources') as $providerId => $sourceIds) {
            foreach ((array) $sourceIds as $sourceId) {
                $this->assertContains(
                    $sourceId,
                    $declared,
                    "Provider '{$providerId}' maps to undeclared source '{$sourceId}'."
                );
            }
        }
    }

    /** @test */
    public function every_enabled_poi_provider_in_the_registry_has_an_attribution_mapping(): void
    {
        // The gap this closes: enabling a provider is a one-line config edit, and
        // nothing else would notice that its rows now reach users uncredited. A
        // provider that genuinely owes nothing is listed here explicitly.
        $owesNothing = ['stub'];

        $mapped = array_keys((array) config('location_attribution.provider_sources'));

        foreach ((array) config('location_providers.providers') as $providerId => $descriptor) {
            if (($descriptor['enabled'] ?? false) !== true) {
                continue;
            }
            if (! in_array('existence', (array) ($descriptor['serves'] ?? []), true)) {
                continue; // not a POI provider
            }
            if (in_array($providerId, $owesNothing, true)) {
                continue;
            }

            $this->assertContains(
                $providerId,
                $mapped,
                "Enabled POI provider '{$providerId}' has no attribution mapping; its rows would publish uncredited."
            );
        }
    }

    /**
     * @test
     *
     * The resolver is called from Blade and is unit-tested without an application.
     * If it ever reads config() unguarded it will raise wherever no app is booted —
     * the same failure that once emptied the entire Ask AI listing context, with the
     * real error swallowed several frames away.
     */
    public function the_resolver_answers_without_a_booted_container(): void
    {
        $source = file_get_contents(base_path('app/Support/LocationDna/LocationDataAttribution.php'));

        $this->assertStringNotContainsString(
            "config('location_attribution.",
            $source,
            'The resolver reads config() directly again; it will raise wherever no application is booted.'
        );
        $this->assertStringContainsString('private static function conf(): array', $source);

        $script = <<<'PHPSCRIPT'
            require %s;
            $a = App\Support\LocationDna\LocationDataAttribution::class;
            echo json_encode([
                'corpus'      => array_column($a::forProvider('overture_corpus'), 'id'),
                'outstanding' => array_column($a::noticeObligationsOutstanding(), 'id'),
                'blocked'     => $a::providerBlockedByNotice('overture_corpus'),
                'statement'   => $a::source('overture_places')['statement'] ?? null,
            ]);
            PHPSCRIPT;

        $file = tempnam(sys_get_temp_dir(), 'ldnaattr') . '.php';
        file_put_contents($file, "<?php\n" . sprintf($script, var_export(base_path('vendor/autoload.php'), true)));

        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1');
        @unlink($file);

        $decoded = json_decode((string) $output, true);

        $this->assertIsArray($decoded, "Resolver failed outside a booted application. Output was: {$output}");
        $this->assertSame(['overture_places'], $decoded['corpus']);
        $this->assertSame([], $decoded['outstanding']);
        $this->assertFalse($decoded['blocked']);
        // The Foursquare credit must survive the no-container path too: it is the
        // attribution the upstream NOTICE requires be preserved.
        $this->assertStringContainsString('Foursquare', (string) $decoded['statement']);
    }
}
