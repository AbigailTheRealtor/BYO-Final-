<?php

namespace App\Services\LocationDna\Contract;

/**
 * MalformedDocumentException — the input cannot be interpreted as a canonical document.
 *
 * G1c contract core. INERT.
 *
 * §5.4 S3: "A corrupt blob is an error to surface, never silently an empty record."
 * This exception is that surfacing. D-G1-1's approved clarification requires malformed
 * values to be rejected or quarantined rather than silently merged.
 */
final class MalformedDocumentException extends LocationDnaContractException
{
    public function __construct(string $message, ?string $dimension = null)
    {
        parent::__construct(ContractViolation::MalformedDocument, $message, $dimension);
    }
}
