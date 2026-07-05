<?php

namespace App\Services\Dna\Scores\Generators;

use App\Models\DnaScore;
use App\Services\Dna\Scores\Contracts\DnaScoreGenerator;
use App\Services\Dna\Scores\Contracts\SymmetricScoreService;
use App\Services\Dna\Scores\SymmetricScoreDnaGenerator;
use Illuminate\Contracts\Container\Container;

/**
 * ScalarScoresGenerator — the DnaScoreGenerator adapter for the family of
 * scalar SymmetricScoreServices listed in config('dna_scores.scalar_scores')
 * (Pet-Friendliness, Lock-and-Leave, Waterfront-Lifestyle, …).
 *
 * It resolves each configured service and persists it through the existing,
 * unchanged SymmetricScoreDnaGenerator. Adding a new scalar score is a one-line
 * config edit — no orchestration change (§ Phase 13, addition 2).
 *
 * GOVERNANCE: no AI, no external calls; writes only to dna_scores via the
 * generic generator's idempotent updateOrCreate.
 */
class ScalarScoresGenerator implements DnaScoreGenerator
{
    private SymmetricScoreDnaGenerator $generator;
    private Container $container;

    public function __construct(SymmetricScoreDnaGenerator $generator, Container $container)
    {
        $this->generator = $generator;
        $this->container = $container;
    }

    public function key(): string
    {
        return 'scalar_scores';
    }

    public function generate(string $listingType, int $listingId, array $options = []): array
    {
        $provenance = ['generated_by' => $options['generated_by'] ?? 'system'];
        $onlyStale  = (bool) ($options['only_stale'] ?? false);

        $rows = [];

        foreach ((array) config('dna_scores.scalar_scores', []) as $class) {
            $service = $this->container->make($class);
            if (! $service instanceof SymmetricScoreService) {
                continue;
            }

            if ($onlyStale && $this->isCurrent($listingType, $listingId, $service)) {
                continue; // persisted score already at this generator version
            }

            $row = $this->generator->generateForListing($service, $listingType, $listingId, $provenance);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * True when a persisted row for this score already carries the version this
     * service would write — so a bulk (algorithm-version) pass may skip it.
     * A given (listing, score_key) has exactly one side, so score_key alone keys it.
     */
    private function isCurrent(string $listingType, int $listingId, SymmetricScoreService $service): bool
    {
        $stored = DnaScore::where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->where('score_key', $service->scoreKey())
            ->value('generator_version');

        return $stored !== null && $stored === $service->version();
    }
}
