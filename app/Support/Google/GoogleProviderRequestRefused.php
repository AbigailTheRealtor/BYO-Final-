<?php

namespace App\Support\Google;

use RuntimeException;

/**
 * An outbound Google request that was refused BEFORE it was sent.
 *
 * Raised — as a rejected promise, so a synchronous `request()` throws it — by
 * {@see GoogleProviderAdmissionMiddleware} when a budgeted Google API family may
 * not be called right now: its switch is off, no credential is configured, a
 * request ceiling is reached, or admission itself could not be decided.
 *
 * It is NOT a provider answer. Nothing reached Google, so a caller must never read
 * it as "Google found nothing here". That is the whole reason it is a distinct type:
 * callers that used to swallow every failure into `[]` can tell a refusal apart and
 * report "unavailable right now" instead of an empty result.
 */
final class GoogleProviderRequestRefused extends RuntimeException
{
    public function __construct(
        public readonly string $family,
        public readonly string $reason,
    ) {
        parent::__construct("google_provider_request_refused: {$family} ({$reason})");
    }

    /** True when a request ceiling — not a switch or a missing credential — refused it. */
    public function isBudgetExhausted(): bool
    {
        return str_contains($this->reason, '_cap_reached');
    }
}
