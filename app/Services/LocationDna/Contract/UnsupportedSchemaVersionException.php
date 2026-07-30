<?php

namespace App\Services\LocationDna\Contract;

/**
 * UnsupportedSchemaVersionException — the record is newer than this reader.
 *
 * G1c contract core. INERT.
 *
 * §5.5: a version above the supported maximum must be refused — "Do not guess, do not
 * downgrade, do not write." A newer writer may have used semantics this reader lacks, and
 * guessing risks recording a clear that was never intended (L5).
 */
final class UnsupportedSchemaVersionException extends LocationDnaContractException
{
    public function __construct(public readonly int $foundVersion, public readonly int $supportedVersion)
    {
        parent::__construct(
            ContractViolation::UnsupportedSchemaVersion,
            "schema_version {$foundVersion} is newer than the supported version {$supportedVersion}; "
            .'the record is read-only and must not be rewritten.',
        );
    }
}
