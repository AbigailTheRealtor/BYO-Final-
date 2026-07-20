<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\AuthorityLinkAcceptance;
use App\Services\Spatial\AuthorityLinkMatcher;
use App\Services\Spatial\AuthorityRecord;
use App\Services\Spatial\NormalizedPlaceRecord;
use Tests\TestCase;

/**
 * Batch 2D Part C1 — the offline link acceptance gate. Each invariant proven pass and fail.
 * Pure; no DB.
 */
class AuthorityLinkAcceptanceTest extends TestCase
{
    private function authority(string $ref, float $lat = 0.0, float $lon = 0.0): AuthorityRecord
    {
        return new AuthorityRecord('cms', $ref, 'Name', $lon, $lat, 1.0);
    }

    private function place(string $ref, float $lat = 0.0, float $lon = 0.0): NormalizedPlaceRecord
    {
        return new NormalizedPlaceRecord('overture', $ref, null, 'hospital', 'Name', null, 0.95, 1, $lon, $lat, 'Point');
    }

    private function gate(): AuthorityLinkAcceptance
    {
        return new AuthorityLinkAcceptance(new AuthorityLinkMatcher(null, 150.0, 0.60));
    }

    private function verdict(array $result, array $places): array
    {
        return $this->gate()->evaluate($result, $places);
    }

    private static function failed(array $verdict): array
    {
        return $verdict['failures'];
    }

    /** @test */
    public function a_well_formed_result_passes_every_invariant(): void
    {
        $a = $this->authority('A1');
        $p = $this->place('P1');
        $result = ['linked' => [['authority' => $a, 'place' => $p, 'score' => 1.0, 'method' => 'spatial_name']], 'unlinked' => [], 'ambiguous' => []];

        $v = $this->verdict($result, [$p]);
        $this->assertTrue($v['passed']);
        $this->assertSame([], $v['failures']);
    }

    /** @test */
    public function duplicate_primary_key_fails_pk_unique(): void
    {
        $a = $this->authority('A1');
        $p = $this->place('P1');
        $result = ['linked' => [
            ['authority' => $a, 'place' => $p, 'score' => 1.0, 'method' => 'spatial_name'],
            ['authority' => $a, 'place' => $p, 'score' => 1.0, 'method' => 'spatial_name'],
        ], 'unlinked' => [], 'ambiguous' => []];

        $this->assertContains('pk_unique', self::failed($this->verdict($result, [$p])));
    }

    /** @test */
    public function a_link_beyond_the_radius_fails_within_radius(): void
    {
        $a = $this->authority('A1', 0.0);
        $p = $this->place('P1', 0.01); // ~1113 m away
        $result = ['linked' => [['authority' => $a, 'place' => $p, 'score' => 1.0, 'method' => 'spatial_name']], 'unlinked' => [], 'ambiguous' => []];

        $this->assertContains('within_radius', self::failed($this->verdict($result, [$p])));
    }

    /** @test */
    public function an_invalid_method_fails_method_valid(): void
    {
        $a = $this->authority('A1');
        $p = $this->place('P1');
        $result = ['linked' => [['authority' => $a, 'place' => $p, 'score' => 1.0, 'method' => 'bogus']], 'unlinked' => [], 'ambiguous' => []];

        $this->assertContains('method_valid', self::failed($this->verdict($result, [$p])));
    }

    /** @test */
    public function an_out_of_range_score_fails_score_in_range(): void
    {
        $a = $this->authority('A1');
        $p = $this->place('P1');
        $result = ['linked' => [['authority' => $a, 'place' => $p, 'score' => 1.5, 'method' => 'spatial_name']], 'unlinked' => [], 'ambiguous' => []];

        $this->assertContains('score_in_range', self::failed($this->verdict($result, [$p])));
    }

    /** @test */
    public function a_record_both_linked_and_ambiguous_fails_the_partition_checks(): void
    {
        $a = $this->authority('A1');
        $p = $this->place('P1');
        $result = [
            'linked'    => [['authority' => $a, 'place' => $p, 'score' => 1.0, 'method' => 'spatial_name']],
            'unlinked'  => [],
            'ambiguous' => [['authority' => $a, 'candidates' => [$p, $this->place('P2')]]],
        ];

        $failures = self::failed($this->verdict($result, [$p, $this->place('P2')]));
        $this->assertContains('ambiguous_excluded', $failures);
        $this->assertContains('fully_partitioned', $failures);
    }

    /** @test */
    public function a_link_to_an_unknown_place_fails_no_orphan_place_ref(): void
    {
        $a = $this->authority('A1');
        $p = $this->place('P1');
        // $places does NOT contain P1.
        $result = ['linked' => [['authority' => $a, 'place' => $p, 'score' => 1.0, 'method' => 'spatial_name']], 'unlinked' => [], 'ambiguous' => []];

        $this->assertContains('no_orphan_place_ref', self::failed($this->verdict($result, [$this->place('P9')])));
    }
}
