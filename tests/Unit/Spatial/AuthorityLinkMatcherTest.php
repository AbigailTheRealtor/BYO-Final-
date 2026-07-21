<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\AuthorityLinkMatcher;
use App\Services\Spatial\AuthorityRecord;
use App\Services\Spatial\NormalizedPlaceRecord;
use Tests\TestCase;

/**
 * Batch 2D Part C1 — the SSOT §8.2 matcher: ST_DWithin(150 m) + similarity ≥ 0.6, with
 * link / unlinked / ambiguous classification (D3), link-not-merge, and no category gate (D4).
 * Pure; no DB, no network.
 */
class AuthorityLinkMatcherTest extends TestCase
{
    private const M_PER_DEG_LAT = 111194.9; // at the equator; the fixtures sit on the meridian

    private function authority(string $ref, string $name, float $lat, float $lon = 0.0): AuthorityRecord
    {
        return new AuthorityRecord('cms', $ref, $name, $lon, $lat, 1.0);
    }

    private function place(string $ref, string $name, float $lat, float $lon = 0.0): NormalizedPlaceRecord
    {
        return new NormalizedPlaceRecord('overture', $ref, null, 'hospital', $name, null, 0.95, 1, $lon, $lat, 'Point');
    }

    /** metres north → latitude offset */
    private function north(float $metres): float
    {
        return $metres / self::M_PER_DEG_LAT;
    }

    /** @test */
    public function a_single_in_radius_same_name_place_is_linked(): void
    {
        $result = (new AuthorityLinkMatcher())->match(
            [$this->authority('A1', 'Synthetic Hospital', 0.0)],
            [$this->place('P1', 'Synthetic Hospital', $this->north(50))],
        );

        $this->assertCount(1, $result['linked']);
        $this->assertCount(0, $result['unlinked']);
        $this->assertCount(0, $result['ambiguous']);
        $this->assertSame('A1', $result['linked'][0]['authority']->authority_ref);
        $this->assertSame('P1', $result['linked'][0]['place']->source_ref);
        $this->assertSame('spatial_name', $result['linked'][0]['method']);
        $this->assertSame(1.0, $result['linked'][0]['score']);
    }

    /** @test */
    public function an_in_radius_but_dissimilar_name_yields_no_link(): void
    {
        $result = (new AuthorityLinkMatcher())->match(
            [$this->authority('A1', 'Synthetic Hospital', 0.0)],
            [$this->place('P1', 'Downtown Auto Parts', $this->north(50))],
        );

        $this->assertCount(0, $result['linked']);
        $this->assertCount(1, $result['unlinked']);
        $this->assertSame('A1', $result['unlinked'][0]->authority_ref);
    }

    /** @test */
    public function a_same_name_place_out_of_radius_yields_no_link(): void
    {
        $result = (new AuthorityLinkMatcher())->match(
            [$this->authority('A1', 'Synthetic Hospital', 0.0)],
            [$this->place('P1', 'Synthetic Hospital', $this->north(500))],
        );

        $this->assertCount(0, $result['linked']);
        $this->assertCount(1, $result['unlinked']);
    }

    /** @test */
    public function two_qualifying_candidates_are_ambiguous_and_not_linked(): void
    {
        $result = (new AuthorityLinkMatcher())->match(
            [$this->authority('A1', 'Twin Center', 0.0)],
            [
                $this->place('P1', 'Twin Center', $this->north(40)),
                $this->place('P2', 'Twin Center', $this->north(60)),
            ],
        );

        $this->assertCount(0, $result['linked'], 'ambiguous records must never auto-link');
        $this->assertCount(1, $result['ambiguous']);
        $this->assertSame('A1', $result['ambiguous'][0]['authority']->authority_ref);
        $this->assertSame(['P1', 'P2'], array_map(fn ($p) => $p->source_ref, $result['ambiguous'][0]['candidates']));
    }

    /** @test */
    public function the_150m_radius_boundary_is_respected(): void
    {
        $matcher = new AuthorityLinkMatcher();

        // ~149 m → inside → link.
        $inside = $matcher->match(
            [$this->authority('A1', 'Edge Site', 0.0)],
            [$this->place('P1', 'Edge Site', 0.00134)],
        );
        $this->assertCount(1, $inside['linked'], 'a place at ~149 m must link');

        // ~151 m → outside → no link.
        $outside = $matcher->match(
            [$this->authority('A1', 'Edge Site', 0.0)],
            [$this->place('P1', 'Edge Site', 0.00136)],
        );
        $this->assertCount(0, $outside['linked'], 'a place at ~151 m must not link');
        $this->assertCount(1, $outside['unlinked']);
    }

    /** @test */
    public function custom_thresholds_are_honoured(): void
    {
        // A 60 m place does not link when the radius is tightened to 50 m.
        $matcher = new AuthorityLinkMatcher(null, 50.0, 0.60);
        $result = $matcher->match(
            [$this->authority('A1', 'Synthetic Hospital', 0.0)],
            [$this->place('P1', 'Synthetic Hospital', $this->north(60))],
        );
        $this->assertCount(0, $result['linked']);
        $this->assertSame(50.0, $matcher->radiusMeters());
        $this->assertSame(0.60, $matcher->similarityMin());
    }

    /** @test */
    public function matching_is_deterministic_and_mutates_no_input(): void
    {
        $authority = [$this->authority('A1', 'Synthetic Hospital', 0.0)];
        $places = [
            $this->place('P1', 'Synthetic Hospital', $this->north(50)),
            $this->place('P2', 'Grandview Auto Parts', $this->north(50)),
        ];
        $matcher = new AuthorityLinkMatcher();

        $r1 = $matcher->match($authority, $places);
        $r2 = $matcher->match($authority, $places);

        $this->assertSame(
            $r1['linked'][0]['place']->source_ref,
            $r2['linked'][0]['place']->source_ref,
        );
        // Inputs unchanged (link, not merge): same instances, same field values.
        $this->assertSame('Synthetic Hospital', $places[0]->name);
        $this->assertSame('A1', $authority[0]->authority_ref);
    }
}
