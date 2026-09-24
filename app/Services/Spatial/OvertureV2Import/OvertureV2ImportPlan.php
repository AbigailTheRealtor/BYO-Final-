<?php

namespace App\Services\Spatial\OvertureV2Import;

/**
 * A fully validated import: everything the importer will write, decided before any connection is
 * opened. Built only by {@see OvertureV2ImportGate}. Immutable.
 *
 * `places` holds ONLY materialization candidates — base rows (`corpus`) and admitted rescues
 * (`rescued`) — in source file order. Diagnostic and refused supplementary rows are counted, never
 * carried: they cannot reach the database from here.
 */
final class OvertureV2ImportPlan
{
    /**
     * @param list<array<string, mixed>>               $places      insert-ready place rows
     * @param array<string, list<array<string, mixed>>> $memberships source_ref => membership rows
     * @param array<string, mixed>                     $manifest    the decoded extraction manifest
     * @param array<string, int>                       $baseByCategory
     */
    public function __construct(
        public readonly OvertureV2ImportContract $contract,
        public readonly array $manifest,
        public readonly string $manifestSha256,
        public readonly string $baseSha256,
        public readonly string $supplementarySha256,
        public readonly string $registryMatchPrecedenceVersion,
        public readonly string $registryNormalizerVersion,
        public readonly array $places,
        public readonly array $memberships,
        public readonly int $baseRows,
        public readonly int $supplementaryRows,
        public readonly int $diagnosticRows,
        public readonly int $rescueAdmittedRows,
        public readonly int $rescueRefusedRows,
        public readonly int $membershipCount,
        public readonly array $baseByCategory,
    ) {
    }

    public function corpusVersion(): string
    {
        return $this->contract->corpusVersion;
    }

    public function matcherAnalysisRows(): int
    {
        return $this->baseRows + $this->supplementaryRows;
    }
}
