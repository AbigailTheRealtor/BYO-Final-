<?php

namespace App\Services\LocationDna\Contract;

use RuntimeException;

/**
 * LocationDnaContractException — base for every contract-core rejection.
 *
 * G1c contract core. INERT.
 *
 * Carries a {@see ContractViolation} so a caller can branch on precise domain meaning
 * rather than parsing a message. Two subclasses exist because those two cases have
 * genuinely different caller responses (§5.5 demands read-only degradation for an
 * unsupported version, while a malformed document is quarantined); every other violation
 * is expressed through the reason code rather than a new class, to keep the hierarchy
 * small.
 */
class LocationDnaContractException extends RuntimeException
{
    public function __construct(
        private readonly ContractViolation $violation,
        string $message,
        private readonly ?string $dimension = null,
    ) {
        parent::__construct($message);
    }

    public function violation(): ContractViolation
    {
        return $this->violation;
    }

    /** The canonical dimension key at fault, when the violation is dimension-scoped. */
    public function dimension(): ?string
    {
        return $this->dimension;
    }

    public static function invalidDimensionValue(string $dimension, string $why): self
    {
        return new self(ContractViolation::InvalidDimensionValue, "Dimension `{$dimension}`: {$why}", $dimension);
    }

    public static function invalidGeometry(string $dimension, string $why): self
    {
        return new self(ContractViolation::InvalidGeometry, "Dimension `{$dimension}`: {$why}", $dimension);
    }

    public static function authoredNull(string $dimension): self
    {
        return new self(
            ContractViolation::AuthoredNull,
            "Dimension `{$dimension}`: null is not a valid authored value; use a clear operation to withdraw it.",
            $dimension,
        );
    }

    public static function invalidOperation(string $why): self
    {
        return new self(ContractViolation::InvalidOperation, $why);
    }
}
