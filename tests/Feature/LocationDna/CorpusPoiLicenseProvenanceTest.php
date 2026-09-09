<?php

namespace Tests\Feature\LocationDna;

use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use App\Services\LocationDna\OvertureCorpusPoiAdapter;
use App\Services\LocationDna\Providers\CanonicalPoiAssembler;
use App\Services\LocationDna\Providers\LocationProviderRegistry;
use App\Services\Spatial\OverturePlaceNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

/**
 * The licensing claim stamped into every corpus-backed row — pinned so it cannot regress
 * to the plausible-but-wrong answer.
 *
 * WHY THIS FILE EXISTS
 * --------------------
 * The first draft of this feature stamped `odbl`. That is wrong, and it is wrong in the
 * direction people reach for: four of Overture's six themes ARE ODbL, so "Overture" and
 * "ODbL" travel together in most sentences anyone has read. Places is the exception.
 *
 *   https://docs.overturemaps.org/attribution/
 *     Buildings / Transportation / Divisions / Base → ODbL
 *     Places                                        → CDLA-Permissive-2.0 (bulk),
 *                                                     Apache-2.0 (Foursquare),
 *                                                     CC0-1.0 (AllThePlaces)
 *
 * The project's own architecture SSOT states it outright — "ODbL share-alike does not
 * govern the Places theme" — so the wrong value contradicted a document already in this
 * repository. A wrong licence in an audit field is not cosmetic: it is the record we would
 * produce if anyone asked what terms we hold this data under.
 *
 * WHY A COMPOUND TOKEN RATHER THAN A SINGLE LICENCE
 * -------------------------------------------------
 * Places is a mixed-licence aggregate and OUR CORPUS CANNOT TELL THE MEMBERS APART. The
 * raw Overture record carries `sources[].dataset`; `OverturePlaceNormalizer::sourceCount()`
 * counts those datasets and discards their names, so the corpus keeps `source_count` (an
 * integer) and nothing else. Verified against the loaded corpus: 29,434 rows, `attrs`
 * containing only `geometry_type`, zero per-source attribution.
 *
 * So no row can be attributed to CDLA vs Apache vs CC0. Naming CDLA alone would be false
 * for some rows and would drop the Apache-2.0 NOTICE obligation — the only requirement in
 * the set that can actually be breached. The compound token claims none exclusively and
 * names every licence that can apply.
 *
 * `license` is carried into `provenance_json` as an audit string; nothing parses,
 * compares or validates it, which is what makes a compound value safe here.
 */
class CorpusPoiLicenseProvenanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The verified representation. Every member of the aggregate, none claimed
     * exclusively, splittable on `+`.
     */
    private const CORPUS_LICENSE = 'cdla-permissive-2.0+apache-2.0+cc0-1.0';

    private const LISTING_TYPE = 'seller_agent';
    private const LISTING_ID   = 7777;

    // ── the descriptor ──────────────────────────────────────────────────────

    public function test_the_corpus_provider_declares_the_verified_license(): void
    {
        $this->assertSame(
            self::CORPUS_LICENSE,
            config('location_providers.providers.overture_corpus.license')
        );
    }

    /**
     * The specific regression. Named separately from the assertion above so a failure
     * says WHICH mistake was made, not merely that a string changed.
     */
    public function test_the_corpus_provider_is_never_declared_odbl(): void
    {
        $license = (string) config('location_providers.providers.overture_corpus.license');

        $this->assertStringNotContainsStringIgnoringCase(
            'odbl',
            $license,
            'Overture PLACES is not ODbL. ODbL governs the Buildings, Transportation, '
            . 'Divisions and Base themes; Places is CDLA-Permissive-2.0 / Apache-2.0 / CC0-1.0. '
            . 'See https://docs.overturemaps.org/attribution/ and the architecture SSOT.'
        );
    }

    /** Each member is named, so none is silently dropped from the record. */
    public function test_every_member_license_of_the_aggregate_is_named(): void
    {
        $license = (string) config('location_providers.providers.overture_corpus.license');

        foreach (['cdla-permissive-2.0', 'apache-2.0', 'cc0-1.0'] as $member) {
            $this->assertStringContainsString(
                $member,
                $license,
                "The Places aggregate includes {$member}; omitting it understates what we hold."
            );
        }
    }

    /**
     * The Apache-2.0 slice is the one with a NOTICE obligation, so it is the one whose
     * omission would matter most. Asserted on its own for that reason.
     */
    public function test_the_apache_slice_is_not_dropped(): void
    {
        $this->assertStringContainsString(
            'apache-2.0',
            (string) config('location_providers.providers.overture_corpus.license'),
            'The Foursquare slice is Apache-2.0 and carries a NOTICE requirement. It is the '
            . 'only obligation in this set that can be breached by omission.'
        );
    }

    /** ODbL must remain declared for the providers it genuinely governs. */
    public function test_odbl_is_still_declared_for_the_osm_provider(): void
    {
        $this->assertSame(
            'odbl',
            config('location_providers.providers.osm_overpass.license'),
            'OpenStreetMap really is ODbL — this correction is about Overture Places only.'
        );
    }

    // ── the claim about our corpus, verified against the importer ───────────

    /**
     * The premise of the compound token: per-row source licensing is NOT recoverable,
     * because the normalizer counts contributing datasets and throws their names away.
     *
     * If a future importer ever retained `sources[].dataset`, this test fails and the
     * compound token can be revisited — which is exactly when it should be.
     */
    public function test_the_importer_discards_the_per_source_attribution_a_row_license_would_need(): void
    {
        $source = file_get_contents(
            (new ReflectionClass(OverturePlaceNormalizer::class))->getFileName()
        );

        $this->assertStringContainsString(
            'source_count',
            $source,
            'The normalizer reduces sources[] to a count.'
        );

        $record = (new ReflectionClass(\App\Services\Spatial\NormalizedPlaceRecord::class));
        $fields = array_map(
            static fn ($p) => $p->getName(),
            $record->getConstructor()->getParameters()
        );

        $this->assertContains('source_count', $fields);
        $this->assertNotContains(
            'sources',
            $fields,
            'A normalized record that carried sources[] would make a per-row licence derivable, '
            . 'and the compound provider-level token would no longer be the best available answer.'
        );
        $this->assertNotContains('license', $fields);
        $this->assertNotContains('source_datasets', $fields);
    }

    // ── what actually reaches a persisted row ───────────────────────────────

    /** The registry hands the corpus licence to whatever resolves it. */
    public function test_the_registry_resolves_the_corpus_license(): void
    {
        config(['location_providers.providers.overture_corpus.enabled' => true]);

        $base = (new LocationProviderRegistry((array) config('location_providers', [])))
            ->effectiveBase('poi.default');

        $this->assertSame('overture_corpus', $base['provider']);
        $this->assertSame(self::CORPUS_LICENSE, $base['descriptor']['license']);
    }

    /** And the canonical assembler stamps the same value onto each contribution. */
    public function test_the_canonical_assembler_stamps_the_corpus_license(): void
    {
        config(['location_providers.providers.overture_corpus.enabled' => true]);

        $merger = new class extends \App\Services\LocationDna\Providers\CanonicalLocationMerger {
            public array $seen = [];

            public function merge(array $contributions, array $options = []): \App\Services\LocationDna\Providers\CanonicalField
            {
                $this->seen[] = $contributions;

                return parent::merge($contributions, $options);
            }
        };

        (new CanonicalPoiAssembler((array) config('location_providers', []), $merger))
            ->assemble([['name' => 'Publix', 'place_id' => 'overture:gers:x']]);

        $this->assertSame(self::CORPUS_LICENSE, $merger->seen[0][0]['license']);
        $this->assertSame('overture_corpus', $merger->seen[0][0]['source']);
    }

    /**
     * End of the chain: a real pipeline run writes the verified licence into
     * `provenance_json`. This is the value that would be produced if anyone asked what
     * terms we hold a stored place under.
     */
    public function test_a_persisted_row_carries_the_verified_license(): void
    {
        config([
            'location_providers.providers.overture_corpus.enabled' => true,
            'google_places.enabled'                                => false,
            'overture_corpus_poi.enabled'                          => true,
            'overture_corpus_poi.corpus_version'                   => 'overture-2026-06-17.0-fl',
            'overture_corpus_poi.regions'                          => ['US-FL'],
            'overture_corpus_poi.region_bounds'                    => [
                'US-FL' => ['west' => -87.63, 'south' => 24.40, 'east' => -79.97, 'north' => 31.00],
            ],
        ]);

        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '6817 STONES THROW CIRCLE N UNIT 17208',
            'source_city'    => 'ST PETERSBURG',
            'source_state'   => 'FL',
            'source_zip'     => '33710',
            'geocoded_lat'   => 27.788945,
            'geocoded_lng'   => -82.735144,
            'geocode_status' => 'geocoded',
            'geocode_source' => 'saved_meta',
            'geocoded_at'    => now(),
        ]);

        (new LocationDnaPoiDistanceService(nearbyFetcher: $this->corpusFetcher()))
            ->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $found = PropertyLocationPoi::where('poi_category', 'grocery_store')->where('rank', 1)->firstOrFail();

        $this->assertSame('found', $found->status);
        $this->assertSame(self::CORPUS_LICENSE, $found->provenance_json['license']);
        $this->assertSame('overture_corpus', $found->provenance_json['provider']);

        // SIA-D18: provenance is a property of the LOOKUP, so a not_found row carries the
        // licence too. A licence error would otherwise hide on the majority of rows.
        $notFound = PropertyLocationPoi::where('poi_category', 'beach')->where('rank', 1)->firstOrFail();

        $this->assertSame('not_found', $notFound->status);
        $this->assertSame(self::CORPUS_LICENSE, $notFound->provenance_json['license']);
    }

    /** Nothing anywhere in the corpus path still says odbl. */
    public function test_no_corpus_row_is_ever_stamped_odbl(): void
    {
        config([
            'location_providers.providers.overture_corpus.enabled' => true,
            'google_places.enabled'                                => false,
            'overture_corpus_poi.enabled'                          => true,
            'overture_corpus_poi.corpus_version'                   => 'overture-2026-06-17.0-fl',
            'overture_corpus_poi.regions'                          => ['US-FL'],
            'overture_corpus_poi.region_bounds'                    => [
                'US-FL' => ['west' => -87.63, 'south' => 24.40, 'east' => -79.97, 'north' => 31.00],
            ],
        ]);

        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '6817 STONES THROW CIRCLE N',
            'source_city'    => 'ST PETERSBURG',
            'source_state'   => 'FL',
            'source_zip'     => '33710',
            'geocoded_lat'   => 27.788945,
            'geocoded_lng'   => -82.735144,
            'geocode_status' => 'geocoded',
            'geocode_source' => 'saved_meta',
            'geocoded_at'    => now(),
        ]);

        (new LocationDnaPoiDistanceService(nearbyFetcher: $this->corpusFetcher()))
            ->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $rows = PropertyLocationPoi::all();

        $this->assertGreaterThan(0, $rows->count());

        foreach ($rows as $row) {
            $this->assertStringNotContainsStringIgnoringCase(
                'odbl',
                (string) ($row->provenance_json['license'] ?? ''),
                "Row for '{$row->poi_category}' was stamped ODbL."
            );
        }
    }

    /** A real adapter with only its database read fixtured. */
    private function corpusFetcher(): OvertureCorpusPoiAdapter
    {
        return new class extends OvertureCorpusPoiAdapter {
            public function isAvailable(): bool
            {
                return true;
            }

            protected function selectRows(string $sql, array $bindings): array
            {
                if (($bindings[2] ?? null) !== 'grocery_store') {
                    return [];
                }

                return [(object) [
                    'name'       => 'Publix',
                    'brand'      => 'Publix',
                    'confidence' => 1.0,
                    'source_ref' => 'overture:gers:aaa111',
                    'last_seen'  => '2026-06-17 00:00:00',
                    'attrs'      => null,
                    'poi_lat'    => 27.79300,
                    'poi_lng'    => -82.74010,
                    'meters'     => 627.9,
                ]];
            }
        };
    }
}
