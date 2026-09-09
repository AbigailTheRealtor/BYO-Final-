<?php

namespace Tests\Unit\LocationDna;

use App\Services\LocationDna\LocationDnaPoiTileCache;
use App\Services\LocationDna\LocationDnaVersionService;
use App\Services\LocationDna\Providers\CorpusSurface;
use Tests\TestCase;

/**
 * One definition of "the corpus surface", read by both things that depend on it.
 *
 * The tile cache and the row-level fetch-version stamp had the same blind spot for
 * the same reason — both keyed on `capabilityHash()`, which reads
 * config/location_providers.php and cannot see the corpus version pinned elsewhere.
 * Fixing them separately would have left two definitions that must agree forever.
 * This pins that they move together.
 */
class CorpusSurfaceTest extends TestCase
{
    private function setCorpus(bool $enabled, ?string $version): void
    {
        config([
            'overture_corpus_poi.enabled'        => $enabled,
            'overture_corpus_poi.corpus_version' => $version,
        ]);
    }

    /** @test */
    public function the_token_moves_with_the_pinned_version(): void
    {
        $this->setCorpus(true, 'overture-2026-06-17.0-fl');
        $a = CorpusSurface::token();

        $this->setCorpus(true, 'overture-2026-09-01.0-fl');
        $b = CorpusSurface::token();

        $this->assertNotSame($a, $b);
    }

    /** @test */
    public function the_token_moves_with_the_adapter_gate(): void
    {
        $this->setCorpus(false, 'overture-2026-06-17.0-fl');
        $off = CorpusSurface::token();

        $this->setCorpus(true, 'overture-2026-06-17.0-fl');
        $on = CorpusSurface::token();

        $this->assertNotSame($off, $on);
    }

    /** @test */
    public function an_unpinned_version_is_one_surface_however_it_is_spelled(): void
    {
        $this->setCorpus(false, null);
        $withNull = CorpusSurface::token();

        $this->setCorpus(false, '');
        $withEmpty = CorpusSurface::token();

        $this->assertSame($withNull, $withEmpty);
        $this->assertSame('', CorpusSurface::version());
    }

    /** @test */
    public function both_consumers_move_when_the_corpus_moves(): void
    {
        // The property that makes one definition worth having: re-pinning must
        // invalidate the raw-candidate cache AND restamp persisted rows. Either one
        // alone leaves the previous corpus's answers in play.
        config(['location_dna.poi.tile_precision' => 0.005]);

        $this->setCorpus(true, 'overture-2026-06-17.0-fl');
        $tileBefore    = (new LocationDnaPoiTileCache())->buildKey(['google_type' => 'supermarket', 'keyword' => ''], 27.95, -82.45);
        $versionBefore = (new LocationDnaVersionService())->fetchVersion();

        $this->setCorpus(true, 'overture-2026-09-01.0-fl');
        $tileAfter    = (new LocationDnaPoiTileCache())->buildKey(['google_type' => 'supermarket', 'keyword' => ''], 27.95, -82.45);
        $versionAfter = (new LocationDnaVersionService())->fetchVersion();

        $this->assertNotSame($tileBefore, $tileAfter, 'The tile key did not move with the corpus version.');
        $this->assertNotSame($versionBefore, $versionAfter, 'The fetch version did not move with the corpus version.');
    }

    /** @test */
    public function the_corpus_does_not_leak_into_the_scoring_version(): void
    {
        // Independence is the whole point of having two stamps: a re-pin requires a
        // refetch, but it is not a scoring change and must not force a re-rank of
        // everything else.
        $this->setCorpus(true, 'overture-2026-06-17.0-fl');
        $before = (new LocationDnaVersionService())->scoringVersion();

        $this->setCorpus(true, 'overture-2026-09-01.0-fl');
        $after = (new LocationDnaVersionService())->scoringVersion();

        $this->assertSame($before, $after, 'A corpus re-pin masqueraded as a scoring change.');
    }
}
