<?php

namespace App\Services\ListingPreferences;

use App\Models\ListingPreference;
use App\Support\ListingPreferences\ListingPreferenceReasonCatalog;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\ListingPreferences\SeekerRole;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * One customer's own Saved / Maybe / Passed rows, for their management area.
 *
 * SCOPED BY CONSTRUCTION, NOT BY DISCIPLINE. Every query here is bound to a
 * `user_id` AND a `seeker_role` the caller resolved from the session — there is
 * no method that reads a preference without both. A management page cannot
 * accidentally widen to another account, because there is no query to widen.
 *
 * THE ROLE IS PART OF THE SCOPE, not a filter bolted on. `seeker_role` is
 * stored rather than derived precisely so a Pass on a rental never suppresses a
 * purchase; the same separation has to hold when the customer reviews the list,
 * or the two markets would appear merged.
 *
 * READ-ONLY. Changing a preference from this page goes through the same
 * {@see ListingPreferenceWriter} every other surface uses. This class never
 * writes, and it never touches a listing row — hydration is
 * {@see ListingPreferenceListingHydrator}'s job, deliberately separate so a
 * listing that has gone away cannot break the customer's own list.
 */
class ListingPreferenceListReader
{
    /**
     * The listing types a preference may be stored against.
     *
     * Produced by SmartTagListingType and nothing else; a row carrying anything
     * unrecognised is skipped rather than guessed at.
     */
    public function __construct()
    {
    }

    /**
     * One page of the customer's rows in one state, newest decision first.
     *
     * @return LengthAwarePaginator<ListingPreference>
     */
    public function page(
        int $userId,
        SeekerRole $role,
        ListingPreferenceState $state,
        int $perPage = 12,
        string $pageName = 'page',
    ): LengthAwarePaginator {
        return ListingPreference::query()
            ->with('reasons')
            ->where('user_id', $userId)
            ->where('seeker_role', $role->value)
            ->where('state', $state->value)
            // `state_set_at` is when the customer decided; `updated_at` moves
            // when they only edited reasons. The list is about decisions.
            ->orderByDesc('state_set_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], $pageName);
    }

    /**
     * How many rows the customer has in each state.
     *
     * ONE query for all three, because the tabs all show a count and three
     * counts must not be three round trips. States with no rows are present as
     * zero: a tab reading "Saved" with no number beside it is ambiguous in a
     * way "Saved (0)" is not.
     *
     * @return array<string,int> keyed by state value
     */
    public function counts(int $userId, SeekerRole $role): array
    {
        $counts = [];
        foreach (ListingPreferenceState::cases() as $state) {
            $counts[$state->value] = 0;
        }

        $rows = ListingPreference::query()
            ->selectRaw('state, COUNT(*) as aggregate')
            ->where('user_id', $userId)
            ->where('seeker_role', $role->value)
            ->groupBy('state')
            ->pluck('aggregate', 'state');

        foreach ($rows as $state => $count) {
            if (array_key_exists((string) $state, $counts)) {
                $counts[(string) $state] = (int) $count;
            }
        }

        return $counts;
    }

    /**
     * The customer's CURRENT reasons for each row, as catalog labels.
     *
     * Read from the eager-loaded `reasons` relation, so a page of rows costs no
     * further query. Keys become LABELS here, never in a template, and a key the
     * catalog no longer publishes is dropped rather than printed — a retired
     * reason should not reappear because an old row still carries it.
     *
     * @param  iterable<ListingPreference> $preferences
     * @return array<string, list<string>> keyed "<listing_type>:<listing_id>"
     */
    public function reasonLabelsFor(iterable $preferences): array
    {
        $out = [];

        foreach ($preferences as $preference) {
            $labels = [];

            foreach ($preference->reasons as $reason) {
                $definition = ListingPreferenceReasonCatalog::get((string) $reason->reason_key);

                if ($definition !== null) {
                    $labels[] = $definition->label;
                }
            }

            $out[$preference->listing_type . ':' . $preference->listing_id] = $labels;
        }

        return $out;
    }

    /**
     * The listing references on one page, ready for batch hydration.
     *
     * A row whose `listing_type` is not a type this application produces is
     * omitted rather than guessed at — the same fail-closed rule the rest of
     * the subsystem follows.
     *
     * @param  iterable<ListingPreference> $preferences
     * @return list<SmartTagListingRef>
     */
    public function referencesFor(iterable $preferences): array
    {
        $refs = [];

        foreach ($preferences as $preference) {
            $type = SmartTagListingType::tryFrom((string) $preference->listing_type);

            if ($type === null || (int) $preference->listing_id <= 0) {
                continue;
            }

            $refs[] = new SmartTagListingRef($type, (int) $preference->listing_id);
        }

        return $refs;
    }
}
