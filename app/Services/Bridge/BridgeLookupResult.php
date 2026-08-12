<?php

namespace App\Services\Bridge;

use App\Services\Property\PropertyCandidate;

/**
 * The outcome of a single-record Bridge lookup, with "we found nothing" and
 * "we could not ask" kept apart.
 *
 * WHY THIS TYPE EXISTS
 * --------------------
 * {@see BridgeListingLookupService::findByMlsNumber()} returns `?PropertyCandidate`,
 * and null means four different things: no such listing, no credentials
 * configured, the API refused us, or the request never completed. For the Match
 * Check page that ambiguity is tolerable — it reports a generic failure either
 * way. For a Seller typing the MLS number printed on their own listing it is
 * not: telling them their property does not exist, when the truth is that our
 * connection to the MLS is down, is a false statement about their property, and
 * the obvious user response (re-check the number, give up) is the wrong one.
 *
 * So this type carries the distinction the null could not. It is deliberately a
 * plain outcome object rather than an exception: a listing that is genuinely
 * absent is not an error, and modelling it as one would make the ordinary case
 * the exceptional path.
 *
 * WHAT IT DOES NOT CARRY
 * ----------------------
 * No status codes, no provider messages, no URLs, no configuration values. The
 * failure reason is one of a fixed set of {@see BridgeApiService} constants, and
 * the message a user sees is chosen by the UI from that constant — never
 * assembled from anything the provider said. Diagnostics belong in the log,
 * which BridgeApiService already writes.
 */
final class BridgeLookupResult
{
    /** A listing was found. */
    public const OUTCOME_FOUND = 'found';

    /** The lookup completed and the listing genuinely does not exist in the dataset. */
    public const OUTCOME_NOT_FOUND = 'not_found';

    /** The lookup could not be performed — configuration, auth, or provider failure. */
    public const OUTCOME_UNAVAILABLE = 'unavailable';

    /** Nothing usable was submitted; no lookup was attempted. */
    public const OUTCOME_INVALID_INPUT = 'invalid_input';

    private function __construct(
        public readonly string $outcome,
        public readonly ?PropertyCandidate $candidate = null,
        /** One of the BridgeApiService::FAILURE_* constants, only when unavailable. */
        public readonly ?string $failureReason = null,
    ) {}

    public static function found(PropertyCandidate $candidate): self
    {
        return new self(self::OUTCOME_FOUND, $candidate);
    }

    public static function notFound(): self
    {
        return new self(self::OUTCOME_NOT_FOUND);
    }

    public static function unavailable(string $failureReason): self
    {
        return new self(self::OUTCOME_UNAVAILABLE, null, $failureReason);
    }

    public static function invalidInput(): self
    {
        return new self(self::OUTCOME_INVALID_INPUT);
    }

    public function isFound(): bool
    {
        return $this->outcome === self::OUTCOME_FOUND;
    }

    public function isNotFound(): bool
    {
        return $this->outcome === self::OUTCOME_NOT_FOUND;
    }

    public function isUnavailable(): bool
    {
        return $this->outcome === self::OUTCOME_UNAVAILABLE;
    }

    public function isInvalidInput(): bool
    {
        return $this->outcome === self::OUTCOME_INVALID_INPUT;
    }
}
