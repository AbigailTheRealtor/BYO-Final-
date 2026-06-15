<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * AgentAiPermissionException
 *
 * Thrown by AgentAiPermissionGuard when the requesting agent does not have
 * permission to access the requested listing or profile context.
 *
 * GOVERNANCE: Never expose internal listing IDs or user IDs in the exception
 * message when it is surfaced to an HTTP response.
 */
class AgentAiPermissionException extends RuntimeException
{
    public function __construct(
        string $message = 'Agent does not have permission to access this context.',
        private readonly string $reason = 'ownership_mismatch',
        int $code = 403,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getHttpStatus(): int
    {
        return $this->getCode() ?: 403;
    }
}
