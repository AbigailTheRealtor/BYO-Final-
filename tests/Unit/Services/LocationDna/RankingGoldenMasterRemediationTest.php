<?php

namespace Tests\Unit\Services\LocationDna;

use App\Console\Commands\RemediateRankingGoldenMaster;
use App\Services\LocationDna\LocationDnaRankingEngine;
use App\Services\LocationDna\PoiCandidate;
use Tests\TestCase;

/**
 * Phase 2 Batch 2D Part B — proof that the ranking golden master is FULLY DE-GOOGLED.
 *
 * The fixture was `generated_from property_location_pois WHERE ranking_score IS NOT NULL` and
 * carried Google business names, ratings, review counts, and real coordinates committed
 * indefinitely — content SSOT §9.4 orders purged. `ldna:remediate-golden-master` removed all of
 * it and replaced identifying data with deterministic synthetic content. This test is the
 * standing guard that it stays removed, while `LocationDnaRankingEngineGoldenMasterTest` proves
 * the engine reproduces the (re-baselined) digest.
 *
 * Touches no DB and no network.
 */
class RankingGoldenMasterRemediationTest extends TestCase
{
    private const FIXTURE = 'tests/Fixtures/LocationDna/ranking-golden-master.json';
    private const CANONICAL_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = json_decode(file_get_contents(base_path(self::FIXTURE)), true);
        $this->assertIsArray($this->fixture);
    }

    /** @test */
    public function no_candidate_or_expected_row_carries_a_rating_or_review_count(): void
    {
        foreach ($this->fixture['groups'] as $group) {
            foreach ($group['candidates'] as $candidate) {
                $this->assertArrayNotHasKey('rating', $candidate, "Group {$group['key']} still carries a rating.");
                $this->assertArrayNotHasKey('user_ratings_total', $candidate, "Group {$group['key']} still carries a review count.");
            }
            foreach ($group['expected'] as $row) {
                $this->assertArrayNotHasKey('rating', $row);
                $this->assertArrayNotHasKey('user_ratings_total', $row);
            }
        }
    }

    /** @test */
    public function no_place_ids_or_addresses_survive(): void
    {
        foreach ($this->fixture['groups'] as $group) {
            foreach ($group['candidates'] as $candidate) {
                $this->assertArrayNotHasKey('place_id', $candidate);
                $this->assertArrayNotHasKey('vicinity', $candidate);
                $this->assertArrayNotHasKey('address', $candidate);
                // The only keys a candidate may carry post-remediation.
                $this->assertEqualsCanonicalizing(['name', 'geometry', 'types'], array_keys($candidate), "Unexpected key in {$group['key']}.");
            }
        }
    }

    /** @test */
    public function every_name_is_a_deterministic_synthetic_label(): void
    {
        foreach ($this->fixture['groups'] as $group) {
            foreach ($group['candidates'] as $candidate) {
                $this->assertMatchesRegularExpression('/^Synthetic POI \d{4}$/', (string) $candidate['name']);
            }
            foreach ($group['expected'] as $row) {
                $this->assertMatchesRegularExpression('/^Synthetic POI \d{4}$/', (string) $row['name']);
            }
        }
    }

    /** @test */
    public function every_source_is_the_fixed_synthetic_point(): void
    {
        // Compare numerically: JSON serialises a whole-number float (30.0, -90.0) as `30` / `-90`,
        // which json_decode returns as int. Every consumer casts (float); so does the engine.
        foreach ($this->fixture['groups'] as $group) {
            $this->assertSame(RemediateRankingGoldenMaster::SYNTH_SOURCE_LAT, (float) $group['source_lat'], "Group {$group['key']} source_lat is not synthetic.");
            $this->assertSame(RemediateRankingGoldenMaster::SYNTH_SOURCE_LNG, (float) $group['source_lng'], "Group {$group['key']} source_lng is not synthetic.");
        }
    }

    /** @test */
    public function every_candidate_coordinate_follows_the_documented_position_contract(): void
    {
        // Coordinates are a pure function of array position (contract v1). This simultaneously
        // proves NO original coordinate survives and that none is a rounded/shifted/offset copy.
        foreach ($this->fixture['groups'] as $group) {
            foreach (array_values($group['candidates']) as $i => $candidate) {
                $loc = $candidate['geometry']['location'];
                $this->assertSame(RemediateRankingGoldenMaster::syntheticCandidateLat($i), (float) $loc['lat'], "Group {$group['key']} candidate #{$i} lat off-contract.");
                $this->assertSame(RemediateRankingGoldenMaster::SYNTH_SOURCE_LNG, (float) $loc['lng'], "Group {$group['key']} candidate #{$i} lng off-contract.");
            }
        }
    }

    /** @test */
    public function coordinates_depend_only_on_position_not_on_original_values(): void
    {
        // Independence proof: the candidate at a given index has the SAME synthetic coordinate in
        // every group, even though those groups had entirely different real coordinates. A coordinate
        // derived by offsetting/transforming the originals could not be position-identical like this.
        $byIndex = [];
        foreach ($this->fixture['groups'] as $group) {
            foreach (array_values($group['candidates']) as $i => $candidate) {
                $byIndex[$i][] = $candidate['geometry']['location']['lat'];
            }
        }

        foreach ($byIndex as $i => $lats) {
            $this->assertCount(1, array_unique($lats), "Index {$i} coordinates vary across groups — not position-only.");
            $this->assertSame(RemediateRankingGoldenMaster::syntheticCandidateLat($i), $lats[0]);
        }
    }

    /** @test */
    public function listing_ids_are_deterministic_synthetic_ordinals(): void
    {
        $ids = array_column($this->fixture['groups'], 'listing_id');
        $distinct = array_values(array_unique($ids));
        sort($distinct);

        $this->assertSame([1, 2, 3, 4, 5, 6], $distinct, 'Listing ids must be synthetic ordinals 1..6.');

        // The group key must be rebuilt from the synthetic ordinal, not the old real id.
        foreach ($this->fixture['groups'] as $group) {
            $this->assertSame(
                $group['listing_type'] . '|' . $group['listing_id'] . '|' . $group['category'],
                $group['key'],
            );
        }
    }

    /** @test */
    public function the_structural_shape_is_preserved(): void
    {
        $groups = $this->fixture['groups'];

        $this->assertSame(103, count($groups));
        $this->assertSame(995, array_sum(array_map(fn ($g) => count($g['expected']), $groups)));
        $this->assertSame(20, count(array_unique(array_column($groups, 'category'))));
        $this->assertSame(6, count(array_unique(array_map(fn ($g) => $g['listing_type'] . '|' . $g['listing_id'], $groups))));
        $this->assertSame(103, $this->fixture['_meta']['groups']);
        $this->assertSame(995, $this->fixture['_meta']['scored_rows']);

        foreach ($groups as $group) {
            $this->assertSame(count($group['candidates']), count($group['expected']), "Length mismatch in {$group['key']}.");
        }
    }

    /** @test */
    public function the_engine_reproduces_the_rebaselined_digest(): void
    {
        $engine = new LocationDnaRankingEngine();
        $all    = [];

        foreach ($this->fixture['groups'] as $group) {
            $ranked = $engine->rankCandidates(
                $group['category'],
                PoiCandidate::fromGooglePlaces($group['candidates']),
                $group['source_lat'],
                $group['source_lng'],
            );

            $rows = [];
            foreach ($ranked as $i => $candidate) {
                $rows[] = ['rank' => $i + 1, 'name' => $candidate['name']] + $candidate['_ranking'];
            }
            $all[] = $rows;
        }

        $this->assertSame(
            $this->fixture['_meta']['golden_hash_sha256'],
            hash('sha256', json_encode($all, self::CANONICAL_FLAGS)),
            'The remediated fixture is not self-consistent with the engine.',
        );
    }

    /** @test */
    public function the_meta_records_the_full_remediation(): void
    {
        $meta = $this->fixture['_meta'];

        $this->assertArrayHasKey('remediation', $meta);
        $this->assertSame('phase-2-batch-2d-part-b', $meta['remediation']['batch']);
        foreach (['business_names', 'rating', 'user_ratings_total', 'source_coordinates', 'candidate_coordinates', 'listing_id'] as $removed) {
            $this->assertContains($removed, $meta['remediation']['removed']);
        }
        $this->assertArrayHasKey('coordinate_contract', $meta['remediation']);
        $this->assertArrayHasKey('types_provenance', $meta['remediation']);
        $this->assertArrayNotHasKey('note_historical_divergence', $meta);
    }
}
