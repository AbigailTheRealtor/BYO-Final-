<?php

namespace Tests\Unit\LocationDna;

use App\Services\LocationDna\LocationDnaPoiTileCache;
use Tests\TestCase;

/**
 * The tile cache key must change when the CORPUS changes, not only when the
 * PROVIDER REGISTRY changes.
 *
 * THE BUG THIS PINS. `LocationProviderRegistry::capabilityHash()` hashes
 * `config/location_providers.php` alone. The corpus version the Overture adapter
 * reads is pinned in `config/overture_corpus_poi.php` — a different file, because
 * it is a different decision. So activating a second corpus import (the whole
 * point of pinning a version rather than following the ledger) left every tile key
 * byte-identical, and the previous corpus's candidates kept being served under the
 * new pin for the remainder of the tile TTL — 7 days by default.
 *
 * That failure is silent in the worst way: the operator re-pins precisely IN ORDER
 * to verify the new import, and what they verify is the old one. No error, no diff,
 * nothing on the page naming a corpus.
 *
 * These tests are deterministic — no database, no network, no clock. They compare
 * keys the class actually produces rather than asserting on its internals, so the
 * mixing strategy can change as long as the identity property holds.
 */
class PoiTileCacheCorpusIdentityTest extends TestCase
{
    /** A stable descriptor and coordinate, so only config varies between keys. */
    private const META = ['google_type' => 'supermarket', 'keyword' => 'grocery'];
    private const LAT  = 27.9506;
    private const LNG  = -82.4572;

    protected function setUp(): void
    {
        parent::setUp();

        // Tile caching on, at a fixed precision: the key is only interesting when
        // the cache is enabled, and a precision from the environment would make
        // these assertions depend on it.
        config(['location_dna.poi.tile_precision' => 0.005]);
    }

    /** Build a key under a given corpus configuration. */
    private function keyFor(bool $enabled, ?string $version): string
    {
        config([
            'overture_corpus_poi.enabled'        => $enabled,
            'overture_corpus_poi.corpus_version' => $version,
        ]);

        return (new LocationDnaPoiTileCache())->buildKey(self::META, self::LAT, self::LNG);
    }

    /** @test */
    public function a_new_corpus_version_produces_a_different_tile_key(): void
    {
        $v1 = $this->keyFor(true, 'overture-2026-06-17.0-fl');
        $v2 = $this->keyFor(true, 'overture-2026-09-01.0-fl');

        $this->assertNotSame(
            $v1,
            $v2,
            'Re-pinning the corpus version left the tile key unchanged: the previous '
            . "corpus's cached candidates would be served under the new pin."
        );
    }

    /** @test */
    public function the_same_corpus_version_produces_a_stable_tile_key(): void
    {
        $first  = $this->keyFor(true, 'overture-2026-06-17.0-fl');
        $second = $this->keyFor(true, 'overture-2026-06-17.0-fl');

        // The complement of the test above, and the one that stops it being passed
        // by simply salting every key: identical configuration must still hit.
        $this->assertSame($first, $second, 'The tile key is not stable under identical configuration.');
    }

    /** @test */
    public function flipping_the_adapter_gate_produces_a_different_tile_key(): void
    {
        $off = $this->keyFor(false, 'overture-2026-06-17.0-fl');
        $on  = $this->keyFor(true, 'overture-2026-06-17.0-fl');

        // `overture_corpus_poi.enabled` is the adapter's own gate and lives outside
        // location_providers.php, so the capability hash cannot see it — yet it
        // decides whether the corpus or Google answered the request being cached.
        $this->assertNotSame($off, $on, 'The adapter gate does not participate in the tile key.');
    }

    /** @test */
    public function an_unpinned_version_is_one_surface_however_it_is_expressed(): void
    {
        // null and '' both mean "nothing pinned" — the adapter reports itself
        // unavailable either way. Splitting the cache between them would double the
        // fetches for two spellings of the same state.
        $this->assertSame(
            $this->keyFor(false, null),
            $this->keyFor(false, ''),
            'A null and an empty corpus version hash differently.'
        );
    }

    /** @test */
    public function the_registry_capability_surface_still_participates(): void
    {
        config([
            'overture_corpus_poi.enabled'        => false,
            'overture_corpus_poi.corpus_version' => null,
        ]);

        $before = (new LocationDnaPoiTileCache())->buildKey(self::META, self::LAT, self::LNG);

        config(['location_providers.providers.google_places.enabled' => false]);

        $after = (new LocationDnaPoiTileCache())->buildKey(self::META, self::LAT, self::LNG);

        // Adding the corpus token must not have displaced the capability token.
        $this->assertNotSame($before, $after, 'The provider registry no longer participates in the tile key.');
    }

    /** @test */
    public function category_and_coordinate_still_separate_tiles(): void
    {
        config([
            'overture_corpus_poi.enabled'        => false,
            'overture_corpus_poi.corpus_version' => null,
        ]);

        $cache = new LocationDnaPoiTileCache();

        $base = $cache->buildKey(self::META, self::LAT, self::LNG);

        $this->assertNotSame(
            $base,
            $cache->buildKey(['google_type' => 'pharmacy', 'keyword' => 'pharmacy'], self::LAT, self::LNG),
            'Two categories share a tile key.'
        );

        $this->assertNotSame(
            $base,
            $cache->buildKey(self::META, 28.9506, self::LNG),
            'Two distant coordinates share a tile key.'
        );
    }
}
