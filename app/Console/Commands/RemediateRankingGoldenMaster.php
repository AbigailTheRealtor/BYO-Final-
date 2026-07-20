<?php

namespace App\Console\Commands;

use App\Services\LocationDna\LocationDnaRankingEngine;
use App\Services\LocationDna\PoiCandidate;
use Illuminate\Console\Command;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part B.
 *
 * OFFLINE remediation of the ranking golden-master fixture. Removes ALL repository-stored
 * Google-derived content that could reasonably identify or reproduce the original places, and
 * replaces it with deterministic synthetic content — then re-runs the ranking engine to
 * regenerate every `expected` block and re-baseline the `golden_hash_sha256`.
 *
 * WHAT IS REMOVED (owner-approved, Part B)
 * ----------------------------------------
 *   - business names            → deterministic synthetic labels (`Synthetic POI NNNN`)
 *   - `rating` / `user_ratings_total` → dropped entirely
 *   - real source + candidate latitude/longitude → **synthetic coordinates** (contract below)
 *   - real `listing_id`         → deterministic synthetic listing ordinal (1..N by first appearance)
 * The fixture had no `place_id`, `vicinity`, or address fields to remove.
 *
 * WHAT IS KEPT
 * ------------
 *   - `types` — generic taxonomy/scoring tokens the ranking profiles match against (Decision 1).
 *     They originated from the prior fixture but contain no names, coordinates, ratings, review
 *     counts, addresses, or place IDs.
 *   - `listing_type` — a generic role token (seller_agent / landlord_agent / seller), not an identifier.
 *   - `category` — a canonical taxonomy key the engine needs to select a ranking profile.
 *   - group structure and counts: 103 groups / 995 rows / 20 categories / 6 listings.
 *
 * SYNTHETIC COORDINATE CONTRACT (v1) — a fixed, documented layout
 * --------------------------------------------------------------
 * Coordinates are a pure function of ARRAY POSITION. The original coordinate values are never
 * read, so the result cannot be a rounded / shifted / hashed / offset transform of real data.
 *
 *   - Every group's source is the fixed point  (SYNTH_SOURCE_LAT, SYNTH_SOURCE_LNG) = (30.0, -90.0).
 *   - The candidate at 0-based index `i` in a group sits due north of the source at
 *         lat = round(30.0 + (i + 1) * 0.01, 6),  lng = -90.0
 *     i.e. candidates are ~0.69 mi apart, monotonically farther by index. This gives the engine
 *     deterministic, well-separated distances spanning its normalised distance range — enough to
 *     exercise scoring — without preserving (or leaking) the original spatial geometry.
 *
 * DETERMINISTIC & IDEMPOTENT
 * --------------------------
 * Names, coordinates, and listing ordinals derive only from position and first-appearance order,
 * so re-running on an already-remediated fixture is byte-identical and yields the same digest.
 * No timestamps, no randomness, no read of any original coordinate/name/id value during synthesis.
 * The digest is computed over the engine's shaped OUTPUT with the golden-master test's canonical
 * flags, so fixture pretty-printing does not affect it.
 *
 * HARD constraints: refuses production; opens no DB; makes no network call.
 *
 * @see \Tests\Unit\Services\LocationDna\LocationDnaRankingEngineGoldenMasterTest
 * @see \Tests\Unit\Services\LocationDna\RankingGoldenMasterRemediationTest
 */
class RemediateRankingGoldenMaster extends Command
{
    protected $signature = 'ldna:remediate-golden-master
        {--fixture= : Path to the golden-master fixture (defaults to tests/Fixtures/LocationDna/ranking-golden-master.json)}
        {--dry-run : Compute and report the new digest without writing the file}';

    protected $description = 'OFFLINE: strip ALL Google-derived content (names/ratings/reviews/coordinates/ids) from the ranking golden master and re-baseline its digest (refuses production)';

    /** Must stay identical to LocationDnaRankingEngineGoldenMasterTest::CANONICAL_FLAGS. */
    private const CANONICAL_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /** Synthetic coordinate contract (v1) — see class docblock. Public so the test is the same source of truth. */
    public const SYNTH_SOURCE_LAT = 30.0;
    public const SYNTH_SOURCE_LNG = -90.0;
    public const SYNTH_LAT_STEP   = 0.01;

    /** The synthetic latitude for a candidate at 0-based index $i. lng is always SYNTH_SOURCE_LNG. */
    public static function syntheticCandidateLat(int $i): float
    {
        return round(self::SYNTH_SOURCE_LAT + ($i + 1) * self::SYNTH_LAT_STEP, 6);
    }

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('[Batch 2D Part B] ldna:remediate-golden-master is an OFFLINE authoring tool and REFUSES to run in production.');

            return self::FAILURE;
        }

        $path = (string) ($this->option('fixture')
            ?: base_path('tests/Fixtures/LocationDna/ranking-golden-master.json'));

        if (! is_file($path)) {
            $this->error("Golden-master fixture not found: {$path}");

            return self::FAILURE;
        }

        $fixture = json_decode((string) file_get_contents($path), true);
        if (! is_array($fixture) || ! isset($fixture['groups']) || ! is_array($fixture['groups']) || $fixture['groups'] === []) {
            $this->error('Golden-master fixture is not valid or has no `groups`.');

            return self::FAILURE;
        }

        // Map each distinct (listing_type, listing_id) to a synthetic ordinal, by first appearance.
        // Idempotent: on an already-synthetic fixture the ordinals map back to themselves.
        $listingOrdinals = [];
        foreach ($fixture['groups'] as $group) {
            $lk = ($group['listing_type'] ?? '') . '|' . ($group['listing_id'] ?? '');
            if (! array_key_exists($lk, $listingOrdinals)) {
                $listingOrdinals[$lk] = count($listingOrdinals) + 1;
            }
        }

        $engine    = new LocationDnaRankingEngine();
        $counter   = 0;
        $allShaped = [];
        $strippedRatings = 0;
        $strippedReviews = 0;
        $renamed         = 0;
        $coordsSynthesized = 0;

        foreach ($fixture['groups'] as $g => $group) {
            $candidates = [];

            foreach (array_values($group['candidates']) as $i => $candidate) {
                if (array_key_exists('rating', $candidate) && $candidate['rating'] !== null) {
                    $strippedRatings++;
                }
                if (array_key_exists('user_ratings_total', $candidate)) {
                    $strippedReviews++;
                }

                // De-Google: synthetic name; drop rating/review; overwrite coordinates from POSITION
                // (the original lat/lng are never read here).
                $candidate['name']     = sprintf('Synthetic POI %04d', ++$counter);
                $candidate['geometry'] = ['location' => [
                    'lat' => self::syntheticCandidateLat($i),
                    'lng' => self::SYNTH_SOURCE_LNG,
                ]];
                unset($candidate['rating'], $candidate['user_ratings_total']);

                $renamed++;
                $coordsSynthesized++;
                $candidates[] = $candidate;
            }

            // Synthetic source + listing identity (independent of originals).
            $lk       = ($group['listing_type'] ?? '') . '|' . ($group['listing_id'] ?? '');
            $ordinal  = $listingOrdinals[$lk];
            $category = (string) $group['category'];

            $fixture['groups'][$g]['source_lat']  = self::SYNTH_SOURCE_LAT;
            $fixture['groups'][$g]['source_lng']  = self::SYNTH_SOURCE_LNG;
            $fixture['groups'][$g]['listing_id']  = $ordinal;
            $fixture['groups'][$g]['key']         = ($group['listing_type'] ?? '') . '|' . $ordinal . '|' . $category;
            $fixture['groups'][$g]['candidates']  = $candidates;

            // Regenerate `expected` over the synthetic inputs, in the exact shape the golden-master
            // test freezes: ['rank', 'name'] + the engine's `_ranking` block.
            $ranked = $engine->rankCandidates(
                $category,
                PoiCandidate::fromGooglePlaces($candidates),
                self::SYNTH_SOURCE_LAT,
                self::SYNTH_SOURCE_LNG,
            );
            $expected = $this->shape($ranked);

            $fixture['groups'][$g]['expected'] = $expected;
            $allShaped[] = $expected;
        }

        $coordsSynthesized += count($fixture['groups']); // + one synthetic source per group

        // Re-baseline the digest over all shaped outputs, in group order.
        $newHash = hash('sha256', json_encode($allShaped, self::CANONICAL_FLAGS));
        $oldHash = (string) ($fixture['_meta']['golden_hash_sha256'] ?? '(none)');

        // Update `_meta`. Structural counts (groups / scored_rows / etc.) are untouched.
        $fixture['_meta']['golden_hash_sha256'] = $newHash;
        $fixture['_meta']['generated_from']     = 'Fully synthetic. Regenerated by ldna:remediate-golden-master: '
            . 'all Google-derived names, ratings, reviews, coordinates, and listing ids removed; '
            . 'expected + digest regenerated by the engine over synthetic inputs.';
        unset($fixture['_meta']['note_historical_divergence']);
        $fixture['_meta']['license'] = 'Fully de-Googled. No business names, ratings, review counts, '
            . 'real coordinates, addresses, or place IDs. Coordinates are synthetic (contract below); '
            . 'types are generic taxonomy/scoring tokens carried from the prior fixture.';
        $fixture['_meta']['remediation'] = [
            'batch'     => 'phase-2-batch-2d-part-b',
            'removed'   => ['business_names', 'rating', 'user_ratings_total', 'source_coordinates', 'candidate_coordinates', 'listing_id'],
            'preserved' => ['types (generic tokens)', 'listing_type (generic role)', 'category', 'group_structure', 'row_counts'],
            'names'     => 'deterministic synthetic labels assigned by position (Synthetic POI NNNN)',
            'listing_ids' => 'deterministic synthetic ordinals (1..N) by first appearance',
            'coordinate_contract' => sprintf(
                'v1 fixed synthetic layout: every source = (%.1f, %.1f); candidate i sits at '
                . '(round(%.1f + (i+1)*%.2f, 6), %.1f). Generated purely from array position; original '
                . 'coordinates are never read (not an offset/rounding/hash of real values).',
                self::SYNTH_SOURCE_LAT, self::SYNTH_SOURCE_LNG,
                self::SYNTH_SOURCE_LAT, self::SYNTH_LAT_STEP, self::SYNTH_SOURCE_LNG,
            ),
            'types_provenance' => 'Type tokens carried from the prior fixture; generic Google Places type '
                . 'vocabulary used only for profile matching — no names, coordinates, ratings, reviews, addresses, or place IDs.',
            'rating_coverage_moved_to' => 'tests/Fixtures/Spatial/Gate1/synthetic-gate1-scenarios.json',
        ];

        $this->info('[Batch 2D Part B] Ranking golden-master remediation (fully synthetic)');
        $this->line("  fixture              : {$path}");
        $this->line("  candidates renamed   : {$renamed}");
        $this->line("  ratings removed      : {$strippedRatings}");
        $this->line("  reviews removed      : {$strippedReviews}");
        $this->line("  coordinates synthesized : {$coordsSynthesized} (candidate + source points)");
        $this->line('  synthetic listings   : ' . count($listingOrdinals));
        $this->line("  old digest           : {$oldHash}");
        $this->line("  new digest           : {$newHash}");

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn('  --dry-run: fixture NOT written. Re-run without --dry-run to persist.');

            return self::SUCCESS;
        }

        $json = json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $this->error('Failed to encode the remediated fixture.');

            return self::FAILURE;
        }

        file_put_contents($path, $json . "\n");

        $this->newLine();
        $this->info('  Remediated fixture written. Update PoiBaselineDiffHarnessTest::FROZEN_DIGEST to the new digest.');

        return self::SUCCESS;
    }

    /**
     * Re-shape one engine result exactly as the golden-master fixture stores it. Key order is
     * load-bearing — it must match LocationDnaRankingEngineGoldenMasterTest::shape() byte for byte.
     *
     * @param  list<array<string, mixed>>  $ranked
     * @return list<array<string, mixed>>
     */
    private function shape(array $ranked): array
    {
        $rows = [];

        foreach ($ranked as $index => $candidate) {
            $rows[] = ['rank' => $index + 1, 'name' => $candidate['name']] + $candidate['_ranking'];
        }

        return $rows;
    }
}
