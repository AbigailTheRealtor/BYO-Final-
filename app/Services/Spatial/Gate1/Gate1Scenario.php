<?php

namespace App\Services\Spatial\Gate1;

use InvalidArgumentException;

/**
 * Gate1Scenario — one synthetic (listing × category) rank-sanity scenario.
 *
 * PART OF: Phase 2 Batch 2D Part B — Hybrid Gate 1 Harness, Option D (synthetic benchmark).
 *
 * WHAT IT IS
 * ----------
 * A single query the Gate 1 harness poses to the ranking engine: a source point, a canonical
 * category, and a labelled candidate set. Every candidate carries a `legitimate` flag — whether
 * it is a defensible result for THIS category — so the harness can measure "embarrassments"
 * (SSOT §9.3: a ranked "grocery store" that is a gas station) without any ground truth derived
 * from Google.
 *
 * LICENSE-CLEAN BY CONSTRUCTION
 * -----------------------------
 * Names, types, ratings, and review counts here are SYNTHETIC. Nothing in a Gate1Scenario is
 * derived from Google Places content. That is the whole point of Option D: it replaces the
 * compliance-blocked 844-row Google-labelled ground truth with an authored, redistributable set.
 *
 * SHAPE ADAPTATION
 * ----------------
 * `rawCandidates()` emits the engine's raw input shape (`geometry.location.lat/lng`, `types`,
 * and — only when present — `rating` / `user_ratings_total`). The evaluator-only label keys
 * (`legitimate`, `true_category`) never reach the engine, so they cannot influence a score.
 *
 * @see \App\Services\Spatial\Gate1\Gate1RankSanityEvaluator
 * @see \App\Services\Spatial\Gate1\Gate1ScenarioSet
 */
final class Gate1Scenario
{
    /**
     * @param  list<array<string, mixed>>  $candidates  Validated candidate rows (see fromArray()).
     */
    private function __construct(
        private readonly string $key,
        private readonly string $category,
        private readonly float $sourceLat,
        private readonly float $sourceLng,
        private readonly array $candidates,
    ) {}

    /**
     * Build a scenario from its fixture array, failing closed on anything malformed.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['key', 'category', 'source_lat', 'source_lng', 'candidates'] as $required) {
            if (! array_key_exists($required, $data)) {
                throw new InvalidArgumentException("Gate1Scenario is missing required key '{$required}'.");
            }
        }

        if (! is_array($data['candidates']) || $data['candidates'] === []) {
            throw new InvalidArgumentException("Gate1Scenario '{$data['key']}' has no candidates.");
        }

        $candidates = [];
        $names      = [];

        foreach ($data['candidates'] as $i => $candidate) {
            if (! is_array($candidate)) {
                throw new InvalidArgumentException("Gate1Scenario '{$data['key']}' candidate #{$i} is not an object.");
            }

            foreach (['name', 'lat', 'lng', 'types', 'legitimate'] as $required) {
                if (! array_key_exists($required, $candidate)) {
                    throw new InvalidArgumentException(
                        "Gate1Scenario '{$data['key']}' candidate #{$i} is missing '{$required}'."
                    );
                }
            }

            $name = (string) $candidate['name'];

            // Names are the identity the harness matches ranked output against (SSOT §9.3 /
            // PoiBaselineDiffHarness identity). Duplicates within a scenario would make that
            // mapping ambiguous, so reject them rather than silently collapse.
            if (isset($names[$name])) {
                throw new InvalidArgumentException(
                    "Gate1Scenario '{$data['key']}' has a duplicate candidate name '{$name}'."
                );
            }
            $names[$name] = true;

            if (! is_array($candidate['types'])) {
                throw new InvalidArgumentException(
                    "Gate1Scenario '{$data['key']}' candidate '{$name}' has non-array types."
                );
            }

            $candidates[] = [
                'name'          => $name,
                'lat'           => (float) $candidate['lat'],
                'lng'           => (float) $candidate['lng'],
                'types'         => array_values($candidate['types']),
                // rating / review count are OPTIONAL and synthetic — present only where a scenario
                // deliberately exercises the rating-scoring branches (Decision 2, Part B).
                'rating'        => array_key_exists('rating', $candidate) && $candidate['rating'] !== null
                    ? (float) $candidate['rating']
                    : null,
                'review_count'  => array_key_exists('user_ratings_total', $candidate)
                    ? (int) $candidate['user_ratings_total']
                    : null,
                'legitimate'    => (bool) $candidate['legitimate'],
                'true_category' => isset($candidate['true_category']) ? (string) $candidate['true_category'] : null,
            ];
        }

        return new self(
            key:       (string) $data['key'],
            category:  (string) $data['category'],
            sourceLat: (float) $data['source_lat'],
            sourceLng: (float) $data['source_lng'],
            candidates: $candidates,
        );
    }

    public function key(): string
    {
        return $this->key;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function sourceLat(): float
    {
        return $this->sourceLat;
    }

    public function sourceLng(): float
    {
        return $this->sourceLng;
    }

    public function candidateCount(): int
    {
        return count($this->candidates);
    }

    /**
     * The candidates in the engine's raw input shape, order preserved (order is load-bearing —
     * the engine normalises distance by the set maximum, erratum E-48). Only the five scoring
     * signals are emitted; the `legitimate` / `true_category` labels are withheld.
     *
     * @return list<array<string, mixed>>
     */
    public function rawCandidates(): array
    {
        return array_map(static function (array $candidate): array {
            $raw = [
                'name'     => $candidate['name'],
                'geometry' => ['location' => ['lat' => $candidate['lat'], 'lng' => $candidate['lng']]],
                'types'    => $candidate['types'],
            ];

            // Include rating / review count ONLY when the scenario supplied them, so a
            // rating-free scenario reproduces the engine's "no rating" / "no reviews" branch
            // exactly as a real rating-free corpus row would.
            if ($candidate['rating'] !== null) {
                $raw['rating'] = $candidate['rating'];
            }
            if ($candidate['review_count'] !== null) {
                $raw['user_ratings_total'] = $candidate['review_count'];
            }

            return $raw;
        }, $this->candidates);
    }

    /**
     * Map of candidate name → whether it is a legitimate result for this scenario's category.
     *
     * @return array<string, bool>
     */
    public function legitimacyByName(): array
    {
        $map = [];
        foreach ($this->candidates as $candidate) {
            $map[$candidate['name']] = $candidate['legitimate'];
        }

        return $map;
    }
}
