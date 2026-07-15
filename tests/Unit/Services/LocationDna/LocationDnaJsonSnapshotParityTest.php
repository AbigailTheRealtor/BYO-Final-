<?php

namespace Tests\Unit\Services\LocationDna;

use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaLifestyleScoreService;
use App\Services\LocationDna\LocationDnaSummaryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * LocationDnaJsonSnapshotParityTest — Phase 1, Definition-of-Done DoD8 ("zero behaviour change
 * demonstrated by snapshot parity"), the summary_json / lifestyle_json half.
 *
 * The golden-master test freezes the ranking engine. This freezes the two JSON documents the
 * ranking feeds: `summary_json` (LocationDnaSummaryService) and `lifestyle_json`
 * (LocationDnaLifestyleScoreService). A deterministic seed (below, from the fixture) produces
 * both documents; their byte-image and SHA-256 are frozen in
 * `tests/Fixtures/LocationDna/phase1-json-snapshots.json`. Any refactor that moves a byte fails.
 *
 * DETERMINISM: the only timestamp inside either document is `summary.geocode.geocoded_at`, which
 * the seed fixes; `generated_at` is a separate column, not part of the JSON. Everything else is a
 * pure function of the seeded POI rows.
 *
 * REGENERATION: run with `UPDATE_LDNA_SNAPSHOTS=1` to re-capture — only when the JSON contract is
 * intentionally changed, and say so in the commit message. A regenerated snapshot is a silenced
 * alarm, not a passing test.
 */
class LocationDnaJsonSnapshotParityTest extends TestCase
{
    use DatabaseTransactions;

    private const FIXTURE = 'tests/Fixtures/LocationDna/phase1-json-snapshots.json';

    /** Stable json_encode flags — the byte image and the frozen SHA are computed with exactly these. */
    private const CANON = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    private function fixturePath(): string
    {
        return base_path(self::FIXTURE);
    }

    /** @return array The whole fixture (seed + frozen documents + meta). */
    private function loadFixture(): array
    {
        $decoded = json_decode(file_get_contents($this->fixturePath()), true);
        $this->assertIsArray($decoded, 'The snapshot fixture is not valid JSON.');

        return $decoded;
    }

    private function canon(mixed $value): string
    {
        return json_encode($value, self::CANON);
    }

    /**
     * Apply the deterministic seed for one listing, run both services, and read back the two
     * persisted JSON documents.
     *
     * @return array{0: array, 1: array} [summary_json, lifestyle_json]
     */
    private function seedRunAndReadJson(array $seed, string $listingType, int $listingId): array
    {
        $dna = $seed['dna'];
        PropertyLocationDna::create([
            'listing_type'   => $listingType,
            'listing_id'     => $listingId,
            'source_address' => $dna['source_address'],
            'source_city'    => $dna['source_city'],
            'source_state'   => $dna['source_state'],
            'geocoded_lat'   => $dna['geocoded_lat'],
            'geocoded_lng'   => $dna['geocoded_lng'],
            'geocode_source' => $dna['geocode_source'],
            'geocode_status' => $dna['geocode_status'],
            'geocoded_at'    => Carbon::parse($dna['geocoded_at']),
        ]);

        foreach ($seed['pois'] as $poi) {
            PropertyLocationPoi::create([
                'listing_type'   => $listingType,
                'listing_id'     => $listingId,
                'poi_category'   => $poi['poi_category'],
                'poi_subtype'    => $poi['poi_subtype'],
                'poi_name'       => $poi['poi_name'],
                'source_lat'     => $dna['geocoded_lat'],
                'source_lng'     => $dna['geocoded_lng'],
                'distance_miles' => $poi['distance_miles'],
                'rank'           => $poi['rank'],
                'ranking_score'  => $poi['ranking_score'],
                'data_source'    => $poi['data_source'],
                'status'         => $poi['status'],
                'calculated_at'  => Carbon::parse($dna['geocoded_at']),
            ]);
        }

        (new LocationDnaSummaryService())->summarizeForListing($listingType, $listingId);
        (new LocationDnaLifestyleScoreService())->generateForListing($listingType, $listingId);

        $record = PropertyLocationDna::where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->first();

        return [$record->summary_json, $record->lifestyle_json];
    }

    /** Write the captured documents + SHAs back into the fixture, preserving the seed. */
    private function captureSnapshot(array $summary, array $lifestyle): void
    {
        $fixture = $this->loadFixture();
        $fixture['summary_json']              = $summary;
        $fixture['lifestyle_json']            = $lifestyle;
        $fixture['_meta']['summary_sha256']   = hash('sha256', $this->canon($summary));
        $fixture['_meta']['lifestyle_sha256'] = hash('sha256', $this->canon($lifestyle));

        file_put_contents(
            $this->fixturePath(),
            json_encode($fixture, JSON_PRETTY_PRINT | self::CANON) . "\n",
        );
    }

    // =========================================================================

    /** @test */
    public function summary_and_lifestyle_are_deterministic(): void
    {
        $seed = $this->loadFixture()['seed'];

        [$s1, $l1] = $this->seedRunAndReadJson($seed, 'seller_agent_auction', 900002);
        [$s2, $l2] = $this->seedRunAndReadJson($seed, 'seller_agent_auction', 900003);

        $this->assertSame($this->canon($s1), $this->canon($s2), 'summary_json is not deterministic across runs.');
        $this->assertSame($this->canon($l1), $this->canon($l2), 'lifestyle_json is not deterministic across runs.');
    }

    /** @test */
    public function summary_and_lifestyle_match_the_frozen_snapshot(): void
    {
        $fixture = $this->loadFixture();
        [$summary, $lifestyle] = $this->seedRunAndReadJson($fixture['seed'], 'seller_agent_auction', 900001);

        if ($fixture['summary_json'] === null || $fixture['lifestyle_json'] === null || getenv('UPDATE_LDNA_SNAPSHOTS')) {
            $this->captureSnapshot($summary, $lifestyle);
            $fixture = $this->loadFixture();
        }

        $this->assertSame(
            $this->canon($fixture['summary_json']),
            $this->canon($summary),
            'summary_json drifted from the frozen Phase-1 snapshot.',
        );
        $this->assertSame(
            $this->canon($fixture['lifestyle_json']),
            $this->canon($lifestyle),
            'lifestyle_json drifted from the frozen Phase-1 snapshot.',
        );
    }

    /** @test */
    public function the_frozen_snapshot_digests_match_the_documents(): void
    {
        $fixture = $this->loadFixture();

        // If capture has not run yet, the sibling test performs it; require it here.
        $this->assertNotNull($fixture['summary_json'], 'Snapshot not captured — run the parity test first.');
        $this->assertNotNull($fixture['lifestyle_json']);

        $this->assertSame(
            hash('sha256', $this->canon($fixture['summary_json'])),
            $fixture['_meta']['summary_sha256'],
            'summary_json SHA-256 does not match its frozen digest.',
        );
        $this->assertSame(
            hash('sha256', $this->canon($fixture['lifestyle_json'])),
            $fixture['_meta']['lifestyle_sha256'],
            'lifestyle_json SHA-256 does not match its frozen digest.',
        );
    }
}
