<?php

namespace App\Services\Dna\Scores\Contracts;

use App\Models\DnaScore;

/**
 * DnaScoreGenerator — the uniform contract every DNA score producer implements
 * so the generation pipeline (DnaScoreGenerationService) can orchestrate any of
 * them without knowing their internals (§ Phase 13, future-proofing addition 2).
 *
 * Today's implementers:
 *   - ScalarScoresGenerator        (wraps the config'd SymmetricScoreService set)
 *   - LocationLifestyleBridgeGenerator
 *
 * FUTURE implementers plug in by name in config/dna_scores.php with ZERO change
 * to orchestration: Property DNA, Location Preference DNA, Audience DNA,
 * Investment DNA, Marketing DNA, Domain DNA, Behavioral DNA, …
 *
 * GOVERNANCE: implementers write ONLY to dna_scores and must be idempotent
 * (updateOrCreate on the dna_scores unique key).
 */
interface DnaScoreGenerator
{
    /** Stable identifier for logging/audit (e.g. 'scalar_scores'). */
    public function key(): string;

    /**
     * Generate (and persist) this generator's dna_scores rows for one listing.
     *
     * @param array{generated_by?:string,only_stale?:bool} $options
     *        - generated_by: origin tag (system|ai|user|imported); default 'system'.
     *        - only_stale:   when true, skip scores whose persisted generator_version
     *                        already matches what this generator would write
     *                        (algorithm-version incremental regeneration).
     * @return array<int,DnaScore> the rows written this call (empty when nothing applied)
     */
    public function generate(string $listingType, int $listingId, array $options = []): array;
}
