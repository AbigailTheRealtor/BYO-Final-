<?php

namespace App\Services\Location\Coordinates\Guards;

use Illuminate\Support\Facades\Log;

/**
 * One structured record per coordinate-resolution attempt.
 *
 * WHAT IT IS FOR
 * --------------
 * Before this provider can be enabled for real traffic, somebody has to be able
 * to answer questions that no amount of reading the code will settle: what
 * fraction of our addresses does the Census corpus actually contain? How often
 * does the geography check reject a match, and is that catching real errors or
 * rejecting good data? Are we anywhere near the caps? Is the breaker tripping?
 * Are we mostly serving from cache, or paying for every lookup?
 *
 * Each of those is a decision that will be made once the flag is turned on, and
 * each needs a number rather than an impression. That is the whole justification
 * for adding logging to code that is currently unreachable.
 *
 * WHAT IS DELIBERATELY NOT RECORDED
 * ---------------------------------
 * The address. Every event carries `address_hash` — an opaque digest of the
 * normalized lookup line — and never the line itself.
 *
 * The hash is enough for the questions above: identical addresses share a hash,
 * so cache behaviour, repeat lookups and per-address failure patterns are all
 * still visible. It is not enough to reconstruct where somebody lives from a
 * log aggregator, which is the point. A property address is the one field in
 * this system that identifies a real place a real person is connected to, and
 * logs are copied, shipped and retained far more casually than the database is.
 *
 * `state` is kept because a state is not identifying on its own and geographic
 * skew ("we reject every match in Alaska") is otherwise invisible. The ZIP is
 * not, because a ZIP plus a timestamp plus a listing is close enough to
 * identifying to be worth doing without.
 *
 * NO GOOGLE
 * ---------
 * Nothing here observes, wraps or reports on the Google pipeline. This records
 * the non-Google ladder only.
 */
final class CoordinateProviderTelemetry
{
    /** The provider answered with a usable coordinate. */
    public const OUTCOME_SUCCESS = 'success';

    /** The provider answered, and the answer was "no such address". */
    public const OUTCOME_NO_MATCH = 'no_match';

    /** The provider offered candidates that could not be told apart. */
    public const OUTCOME_AMBIGUOUS = 'ambiguous';

    /** A cap or an open circuit stopped the call before it was made. */
    public const OUTCOME_BLOCKED = 'blocked';

    /** The provider misbehaved. */
    public const OUTCOME_PROVIDER_FAILURE = 'provider_failure';

    /** Answered from cache; no request was made. */
    public const OUTCOME_CACHE_HIT = 'cache_hit';

    /** The match was rejected as not describing the requested place. */
    public const OUTCOME_REJECTED = 'rejected';

    /**
     * @param array<string, mixed> $context
     */
    public static function record(string $provider, string $outcome, array $context = []): void
    {
        Log::info('coordinate_provider', array_merge([
            'provider' => $provider,
            'outcome'  => $outcome,
        ], $context));
    }

    /**
     * An opaque, stable digest of a normalized address line.
     *
     * Stable across processes and releases so events can be correlated over
     * time, and one-way so they cannot be turned back into an address.
     */
    public static function addressHash(string $normalizedLine): string
    {
        return substr(hash('sha256', $normalizedLine), 0, 16);
    }
}
