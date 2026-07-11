<?php

namespace App\Services\LocationDna;

/**
 * PoiCandidate — the ranking engine's provider-agnostic input contract.
 *
 * WHY THIS EXISTS
 * ---------------
 * `LocationDnaRankingEngine::rankCandidates()` currently reaches into raw Google Places
 * response objects: `$place['geometry']['location']['lat']`, `$place['types']`,
 * `$place['rating']`, `$place['user_ratings_total']`, `$place['name']`. Five reads, one
 * vendor. Swapping providers today means teaching every consumer a second JSON dialect.
 *
 * This value object is the seam. It names the five signals the engine actually scores on,
 * and `fromGooglePlace()` is the only place in the codebase that has to know Google spells
 * a coordinate `geometry.location.lat`.
 *
 * WHAT ROUTES THROUGH IT
 * ----------------------
 * `LocationDnaRankingEngine::rankCandidates()` now consumes this type exclusively, and all
 * three production call sites in `LocationDnaPoiDistanceService` adapt through
 * `fromGooglePlaces()` before ranking. The engine holds no provider-shape knowledge.
 *
 * What has NOT happened: the production *lookup* path still calls Google inline via raw Guzzle
 * rather than through `PoiLookupAdapterInterface`. Routing it is a separate batch, kept apart
 * so that a ranking regression can only ever be attributable to one of the two.
 * `PoiCandidateGoldenMasterParityTest` proved the adaptation lossless before anything depended
 * on it: the five scoring accessors alone reproduce all 995 frozen rows.
 *
 * COERCION IS COPIED, NOT INVENTED
 * --------------------------------
 * Every cast in `fromGooglePlace()` mirrors the engine's existing read, character for
 * character, including the parts that look sloppy:
 *
 *   - A missing coordinate becomes `0.0`, not null and not an exception. Null Island is a
 *     real place to the haversine, and the engine has always scored it. Preserved.
 *   - `rating` distinguishes absent from present: `isset()`, so an explicit null and a
 *     missing key both yield `null`. 151 of the 995 frozen candidates have no rating key.
 *   - `user_ratings_total` collapses absent AND null to `0`, because `(int) null === 0`.
 *
 * The one place this object is deliberately MORE tolerant than the engine was: a non-array
 * `types` becomes `[]` here, where the engine used to raise a TypeError passing it to
 * `in_array()`. No provider has ever sent one. The engine is now normalised onto this type, so
 * that normalisation has shipped: a malformed `types` scores as "no types" rather than
 * throwing. Accepted as a narrow consequence of adopting the value object.
 *
 * ADDRESS IS NOT AN ENGINE SIGNAL
 * -------------------------------
 * `address()` is carried for the persistence layer, which reads `vicinity` off the array
 * the engine merges through. It takes no part in scoring. Note that the two existing
 * readers disagree on its empty case — `LocationDnaPoiDistanceService` coerces a missing
 * vicinity to `null`, `GooglePlacesPoiAdapter` coerces it to `''`. This object reports the
 * truth (`null` when absent) and leaves each consumer's coercion where it already lives.
 *
 * @see LocationDnaRankingEngine::rankCandidates()
 * @see \Tests\Unit\Services\LocationDna\PoiCandidateGoldenMasterParityTest
 */
final class PoiCandidate
{
    /**
     * @param  list<mixed>  $types  Provider type tags, preserved verbatim — the engine
     *                              compares them with `in_array(..., strict: true)`, so
     *                              coercing members here would silently change matching.
     * @param  array<string, mixed> $raw The untouched provider payload.
     */
    private function __construct(
        private readonly string $name,
        private readonly float $latitude,
        private readonly float $longitude,
        private readonly array $types,
        private readonly ?float $rating,
        private readonly int $reviewCount,
        private readonly ?string $address,
        private readonly array $raw,
    ) {}

    /**
     * Adapt one raw Google Places result into a candidate.
     *
     * @param  array<string, mixed> $place A single element of a Nearby Search `results` array.
     */
    public static function fromGooglePlace(array $place): self
    {
        $types = $place['types'] ?? [];

        return new self(
            name:        (string) ($place['name'] ?? ''),
            latitude:    (float) ($place['geometry']['location']['lat'] ?? 0),
            longitude:   (float) ($place['geometry']['location']['lng'] ?? 0),
            types:       is_array($types) ? $types : [],
            rating:      isset($place['rating']) ? (float) $place['rating'] : null,
            reviewCount: (int) ($place['user_ratings_total'] ?? 0),
            address:     isset($place['vicinity']) ? (string) $place['vicinity'] : null,
            raw:         $place,
        );
    }

    /**
     * Adapt a whole Google Places `results` array, preserving order.
     *
     * Order is load-bearing upstream of ranking: the engine normalises the distance
     * component by the maximum across the set it is given, so a reordered or resized set
     * scores differently (erratum E-48).
     *
     * @param  list<array<string, mixed>> $places
     * @return list<self>
     */
    public static function fromGooglePlaces(array $places): array
    {
        return array_map(static fn (array $place): self => self::fromGooglePlace($place), array_values($places));
    }

    public function name(): string
    {
        return $this->name;
    }

    public function latitude(): float
    {
        return $this->latitude;
    }

    public function longitude(): float
    {
        return $this->longitude;
    }

    /** @return list<mixed> */
    public function types(): array
    {
        return $this->types;
    }

    /** Null when the provider supplied no rating — never 0.0, which is a real rating. */
    public function rating(): ?float
    {
        return $this->rating;
    }

    /** Zero when the provider supplied no review count, matching the engine's existing cast. */
    public function reviewCount(): int
    {
        return $this->reviewCount;
    }

    /** Null when absent. Not a scoring signal; carried for persistence. */
    public function address(): ?string
    {
        return $this->address;
    }

    /**
     * The untouched provider payload.
     *
     * LOAD-BEARING, not incidental. `rankCandidates()` returns
     * `array_merge($candidate->raw(), ['_ranking' => ...])`, and the persistence layer reads
     * non-scoring keys back off that merge: `_row_id` to re-associate a scored result with its
     * database row, `vicinity` to persist the POI's address. Narrowing this to the scoring
     * signals would leave ranking byte-identical and silently corrupt persistence — rows
     * skipped, addresses written NULL. `RankingEnginePersistencePassthroughTest` and
     * `RecomputeRankingsFromCacheTest` both fail if it is narrowed.
     *
     * New code must not reach through this to read a scoring signal — reach for a named
     * accessor, or add one.
     *
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }

    /**
     * Reproject onto the five scoring signals, and nothing else — no `vicinity`, no `_row_id`,
     * no `place_id`.
     *
     * TEST-ONLY, and deliberately retained. It exists so `PoiCandidateGoldenMasterParityTest`
     * can prove those five accessors are *jointly sufficient* to reproduce every frozen ranking
     * row: it round-trips each candidate through this projection, re-adapts it, and asserts the
     * corpus digest is unmoved. If the engine ever scored on a sixth signal, that digest would
     * change. Deleting this method would delete that proof.
     *
     * It is NOT a substitute for `raw()` in production. Feeding a reprojection to the engine
     * scores identically while stripping `_row_id` and `vicinity`, which makes
     * `recomputeRankingsFromCache()` skip every row and the nearby-search path persist NULL
     * addresses — silently, with the golden master still green. Both persistence tests exist to
     * catch exactly that substitution.
     *
     * @return array<string, mixed>
     */
    public function toRankingArray(): array
    {
        return [
            'name'               => $this->name,
            'geometry'           => ['location' => ['lat' => $this->latitude, 'lng' => $this->longitude]],
            'types'              => $this->types,
            'rating'             => $this->rating,
            'user_ratings_total' => $this->reviewCount,
        ];
    }
}
