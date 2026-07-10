<?php

namespace Tests\Unit\Services\LocationDna;

use App\Services\LocationDna\LocationDnaRankingEngine;
use App\Services\LocationDna\PoiCandidate;
use Tests\TestCase;

/**
 * Phase 1 — proof that `PoiCandidate` is a lossless input contract for the ranking engine.
 *
 * WHAT THIS PROVES
 * ----------------
 * `LocationDnaRankingEngineGoldenMasterTest` freezes what the engine produces from raw
 * Google arrays. This test asks the strictly harder question that has to be answered before
 * the engine is normalised onto `PoiCandidate`:
 *
 *   Do the five accessors — name, latitude, longitude, types, rating, reviewCount — carry
 *   *everything* the engine scores on, for every one of the 995 frozen rows?
 *
 * It answers it by routing the frozen candidates through `PoiCandidate` and back out via
 * `toRankingArray()`, which emits ONLY those five signals: no `vicinity`, no `place_id`, no
 * `_row_id`. If the engine were reading anything else — anything at all — the corpus digest
 * would move. It does not.
 *
 * WHAT IT DOES NOT PROVE
 * ----------------------
 * That the rewiring is safe. The production path reads `_row_id` and `vicinity` back off the
 * engine's `array_merge` output, and `toRankingArray()` drops both by design. Sufficiency for
 * SCORING is not sufficiency for PERSISTENCE. That is a separate batch with its own harness.
 *
 * Nothing in production routes through `PoiCandidate` yet. This test is what earns it the
 * right to, later.
 */
class PoiCandidateGoldenMasterParityTest extends TestCase
{
    private const FIXTURE = 'tests/Fixtures/LocationDna/ranking-golden-master.json';

    /** Must stay identical to the golden master's flags, or the digests are incomparable. */
    private const CANONICAL_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * Non-scoring keys the engine must be proven indifferent to. `_row_id` and `vicinity` are
     * the two the production path actually threads through `array_merge` today.
     */
    private const NON_SCORING_NOISE = [
        'vicinity'        => '1 Nonexistent Way',
        'place_id'        => 'ChIJ_this_must_not_affect_scoring',
        '_row_id'         => 999999,
        'business_status' => 'OPERATIONAL',
        'price_level'     => 4,
    ];

    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $path = base_path(self::FIXTURE);
        $this->assertFileExists($path, 'The golden-master fixture is missing.');

        $decoded = json_decode(file_get_contents($path), true);
        $this->assertIsArray($decoded, 'The golden-master fixture is not valid JSON.');

        $this->fixture = $decoded;

        // Fail closed (erratum E-41). An empty fixture would make every assertion below pass
        // over nothing and report a safety it never verified.
        $this->assertSame(103, count($this->fixture['groups']), 'Expected 103 groups.');
    }

    /** Re-shape one engine result exactly as the fixture stores it. Key order is load-bearing. */
    private function shape(array $ranked): array
    {
        $rows = [];

        foreach ($ranked as $index => $candidate) {
            $rows[] = ['rank' => $index + 1, 'name' => $candidate['name']] + $candidate['_ranking'];
        }

        return $rows;
    }

    /**
     * Rank every fixture group after round-tripping its candidates through `PoiCandidate`,
     * and return the digest of the whole corpus.
     *
     * @param  callable(array): array  $decorate  Applied to each raw candidate before adaptation.
     */
    private function corpusDigestThroughCandidate(callable $decorate): string
    {
        $engine = new LocationDnaRankingEngine();
        $all    = [];

        foreach ($this->fixture['groups'] as $group) {
            $candidates = PoiCandidate::fromGooglePlaces(array_map($decorate, $group['candidates']));

            $input = array_map(
                static fn (PoiCandidate $candidate): array => $candidate->toRankingArray(),
                $candidates,
            );

            $all[] = $this->shape($engine->rankCandidates(
                $group['category'],
                $input,
                $group['source_lat'],
                $group['source_lng'],
            ));
        }

        return hash('sha256', json_encode($all, self::CANONICAL_FLAGS));
    }

    /** @test */
    public function adapting_through_poi_candidate_reproduces_the_frozen_corpus_digest(): void
    {
        // The headline assertion: 995 rows, byte-identical, with the engine fed exclusively
        // from the value object's accessors.
        $this->assertSame(
            $this->fixture['_meta']['golden_hash_sha256'],
            $this->corpusDigestThroughCandidate(static fn (array $place): array => $place),
            'Adapting candidates through PoiCandidate changed the ranking corpus. The value '
            . 'object is dropping or distorting a signal the engine scores on.',
        );
    }

    /** @test */
    public function every_group_reproduces_its_frozen_output_byte_for_byte_through_poi_candidate(): void
    {
        // Same claim as above, but reported per group so a failure names the category that broke
        // rather than handing back a mismatched hash.
        $engine    = new LocationDnaRankingEngine();
        $divergent = [];

        foreach ($this->fixture['groups'] as $group) {
            $input = array_map(
                static fn (PoiCandidate $candidate): array => $candidate->toRankingArray(),
                PoiCandidate::fromGooglePlaces($group['candidates']),
            );

            $actual = $this->shape($engine->rankCandidates(
                $group['category'],
                $input,
                $group['source_lat'],
                $group['source_lng'],
            ));

            if (json_encode($actual, self::CANONICAL_FLAGS) !== json_encode($group['expected'], self::CANONICAL_FLAGS)) {
                $divergent[] = $group['key'];
            }
        }

        $this->assertSame([], $divergent, "PoiCandidate changed ranking output for:\n  " . implode("\n  ", $divergent));
    }

    /** @test */
    public function non_scoring_provider_fields_cannot_influence_ranking(): void
    {
        // Sufficiency, stated adversarially. Decorate every candidate with the non-scoring keys
        // the production path threads through `array_merge` — plus a few Google really sends —
        // and confirm the digest does not move. If it did, some field outside the five accessors
        // is load-bearing, and normalising the engine onto PoiCandidate would silently reprice
        // the corpus.
        $this->assertSame(
            $this->fixture['_meta']['golden_hash_sha256'],
            $this->corpusDigestThroughCandidate(static fn (array $place): array => $place + self::NON_SCORING_NOISE),
            'A non-scoring provider field changed the ranking. PoiCandidate is not a sufficient '
            . 'input contract for the engine.',
        );
    }

    /** @test */
    public function the_raw_payload_survives_adaptation_for_the_persistence_layer(): void
    {
        // `toRankingArray()` drops `_row_id` and `vicinity` by design; `raw()` is what keeps the
        // production path able to re-associate a scored candidate with its database row. Pin it:
        // losing this is how a rewiring writes NULL addresses and orphans every scored row.
        $source = $this->fixture['groups'][0]['candidates'][0] + self::NON_SCORING_NOISE;

        $candidate = PoiCandidate::fromGooglePlace($source);

        $this->assertSame($source, $candidate->raw(), 'raw() must return the provider payload untouched.');
        $this->assertSame(999999, $candidate->raw()['_row_id']);
        $this->assertSame('1 Nonexistent Way', $candidate->address());
        $this->assertArrayNotHasKey('_row_id', $candidate->toRankingArray());
        $this->assertArrayNotHasKey('vicinity', $candidate->toRankingArray());
    }

    /** @test */
    public function adaptation_preserves_candidate_order(): void
    {
        // The engine normalises distance by the maximum across the set it is handed (erratum
        // E-48), so a reordered or resized set scores differently even for identical POIs.
        // fromGooglePlaces() must not sort, filter, or re-key.
        $raw        = $this->fixture['groups'][0]['candidates'];
        $candidates = PoiCandidate::fromGooglePlaces($raw);

        $this->assertCount(count($raw), $candidates);
        $this->assertSame(
            array_column($raw, 'name'),
            array_map(static fn (PoiCandidate $c): string => $c->name(), $candidates),
        );
    }
}
