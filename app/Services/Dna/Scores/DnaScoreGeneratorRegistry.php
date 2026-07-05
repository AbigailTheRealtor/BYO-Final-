<?php

namespace App\Services\Dna\Scores;

use App\Services\Dna\Scores\Contracts\DnaScoreGenerator;
use Illuminate\Contracts\Container\Container;

/**
 * DnaScoreGeneratorRegistry — the config-driven catalogue of DNA score
 * generators (§ Phase 13, addition 2). Reads config('dna_scores.generators')
 * and resolves each to a DnaScoreGenerator via the container.
 *
 * This is the single extension point: new generators (Property DNA, Audience
 * DNA, Investment DNA, …) become active by adding their class here — no change
 * to DnaScoreGenerationService, the job, the observers, or the command.
 *
 * GOVERNANCE: pure lookup; no persistence, no AI, no external calls.
 */
class DnaScoreGeneratorRegistry
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * The enabled generators, in configured order.
     *
     * @return array<int,DnaScoreGenerator>
     */
    public function enabled(): array
    {
        $generators = [];

        foreach ((array) config('dna_scores.generators', []) as $class) {
            $instance = $this->container->make($class);
            if ($instance instanceof DnaScoreGenerator) {
                $generators[] = $instance;
            }
        }

        return $generators;
    }
}
