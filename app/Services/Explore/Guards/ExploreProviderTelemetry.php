<?php

namespace App\Services\Explore\Guards;

use Illuminate\Support\Facades\Log;

/**
 * One structured log line per Explore provider decision.
 *
 * WHY THIS EXISTS
 * ---------------
 * A provider bill is the worst possible way to learn about a runaway request
 * path, and it is the way this application learned once already. The budget
 * makes a runaway impossible; this makes it VISIBLE — the difference between
 * "we were protected" and "we know we were protected, and by how much".
 *
 * Shaped after {@see \App\Services\Location\Coordinates\Guards\CoordinateProviderTelemetry}:
 * `Log::info()` with an event name and a flat context array, named outcome
 * constants so a query can group by them, and no second logging channel to
 * configure. Deliberately not a metrics platform — the existing log stream
 * already answers every question in the brief.
 *
 * WHAT NEVER APPEARS IN A LINE
 * ----------------------------
 * No MLS record, no address, no listing content, no credential, no raw IP.
 * The actor is the already-hashed key the budget counts against, so a log line
 * can be grouped by caller without identifying one. A ListingKey is an opaque
 * MLS identifier and is permitted — it is how a support question about one
 * property gets answered — but nothing describing the property travels with it.
 */
final class ExploreProviderTelemetry
{
    /** The event name every line carries, so the stream is greppable. */
    public const EVENT = 'explore_provider';

    /** A provider pass ran and consumed budget. */
    public const OUTCOME_FETCHED = 'fetched';

    /** The tile's fetch cache was warm; nothing was sent and nothing was spent. */
    public const OUTCOME_CACHE_HIT = 'cache_hit';

    /** A ceiling refused the call BEFORE anything was sent. */
    public const OUTCOME_BUDGET_BLOCKED = 'budget_blocked';

    /** The provider was reached and did not answer usefully. Budget was spent. */
    public const OUTCOME_PROVIDER_FAILURE = 'provider_failure';

    /** A pagination ceiling stopped the pass short. */
    public const OUTCOME_PARTIAL = 'partial';

    /** Discovery is switched off; no provider path was entered at all. */
    public const OUTCOME_DISABLED = 'disabled';

    /**
     * @param array<string,mixed> $context
     */
    public static function record(string $outcome, array $context = []): void
    {
        Log::info(self::EVENT, array_merge([
            'provider' => ExploreProviderBudget::PROVIDER,
            'outcome'  => $outcome,
        ], $context));
    }
}
