<?php

namespace App\Services\ListingPreferences;

use App\Models\ListingPreferenceEvent;
use App\Support\ListingPreferences\ListingPreferenceReasonCatalog;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\ListingPreferences\SeekerRole;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The customer's own timeline, worded for the customer.
 *
 * ONE HISTORY TABLE, AND THIS READS IT. `listing_preference_events` is the
 * append-only record Phase 1 built and Phase 2 started writing; nothing here
 * creates a second one, and nothing here writes at all. The model itself
 * refuses updates and deletes, so what is presented cannot be edited into a
 * more flattering shape after the fact.
 *
 * SCOPED TO ONE ACCOUNT AND ONE ROLE, by construction — every query binds both,
 * exactly as the current-state reader does. There is no method that returns an
 * event without them.
 *
 * NOTHING INTERNAL REACHES THE PAGE.
 * ----------------------------------
 * The stored row carries `subject_key` (`mls:stellar_bridge:STELLAR-123`), a `listing_type`,
 * a numeric `listing_id`, and a NULL `to_state` for a withdrawal. None of that
 * is language. {@see present()} turns a row into a sentence a person can read —
 * "Preference removed", "Changed from Maybe to Saved" — and carries the listing
 * REFERENCE separately so the page can hydrate a property card from it. The
 * subject key and the database id are never part of the presented shape.
 *
 * A NULL `to_state` IS A WITHDRAWAL, never a fourth state and never a synonym
 * for Pass. `ListingPreferenceEvent::wasCleared()` is the one reading of that
 * distinction and this class defers to it rather than comparing strings.
 */
class ListingPreferenceHistoryReader
{
    /**
     * One page of the customer's own events, newest first.
     *
     * @return LengthAwarePaginator<ListingPreferenceEvent>
     */
    public function page(
        int $userId,
        SeekerRole $role,
        int $perPage = 20,
        string $pageName = 'page',
    ): LengthAwarePaginator {
        return ListingPreferenceEvent::query()
            ->where('user_id', $userId)
            ->where('seeker_role', $role->value)
            // The table's own (user_id, seeker_role, created_at) index.
            // `id` breaks ties so two events in the same second keep the order
            // they were written in rather than an arbitrary one.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], $pageName);
    }

    /**
     * The listing references on a page of events, for batch hydration.
     *
     * @param  iterable<ListingPreferenceEvent> $events
     * @return list<SmartTagListingRef>
     */
    public function referencesFor(iterable $events): array
    {
        $refs = [];
        $seen = [];

        foreach ($events as $event) {
            $type = SmartTagListingType::tryFrom((string) $event->listing_type);

            if ($type === null || (int) $event->listing_id <= 0) {
                continue;
            }

            $key = $type->value . ':' . (int) $event->listing_id;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $refs[]     = new SmartTagListingRef($type, (int) $event->listing_id);
        }

        return $refs;
    }

    /**
     * One event, as a person reads it.
     *
     * @return array{
     *     ref: ?SmartTagListingRef,
     *     summary: string,
     *     to_label: ?string,
     *     from_label: ?string,
     *     was_cleared: bool,
     *     reasons: list<string>,
     *     at: ?\Illuminate\Support\Carbon,
     *     surface: ?string
     * }
     */
    public function present(ListingPreferenceEvent $event): array
    {
        $type = SmartTagListingType::tryFrom((string) $event->listing_type);
        $ref  = $type !== null && (int) $event->listing_id > 0
            ? new SmartTagListingRef($type, (int) $event->listing_id)
            : null;

        $from = $this->label($event->from_state);
        $to   = $this->label($event->to_state);

        return [
            'ref'         => $ref,
            'summary'     => $this->summary($event, $from, $to),
            'from_label'  => $from,
            'to_label'    => $to,
            'was_cleared' => $event->wasCleared(),
            'reasons'     => $this->reasonLabels($event),
            'at'          => $event->created_at,
            'surface'     => $this->surfaceLabel($event->surface),
        ];
    }

    /**
     * The sentence.
     *
     * A withdrawal says so in words rather than showing an empty state or the
     * word "null"; a first choice reads as a choice rather than as a change
     * from nothing.
     */
    private function summary(ListingPreferenceEvent $event, ?string $from, ?string $to): string
    {
        if ($event->wasCleared()) {
            return 'Preference removed';
        }

        if ($to === null) {
            // Defensive: a non-cleared row with no destination is a shape this
            // application does not write. Say nothing rather than invent.
            return 'Preference updated';
        }

        if ($from === null) {
            return "Marked {$to}";
        }

        if ($from === $to) {
            return "Reasons updated for {$to}";
        }

        return "Changed from {$from} to {$to}";
    }

    /**
     * The CUSTOMER's word for a state.
     *
     * The stored values are `save` / `maybe` / `pass`; the customer's words are
     * Saved, Maybe and Passed. An enum value must never reach the page.
     */
    private function label(mixed $state): ?string
    {
        if (! is_string($state) || $state === '') {
            return null;
        }

        $case = ListingPreferenceState::tryFrom($state);

        return $case === null ? null : self::stateLabel($case);
    }

    /** Shared with the views, so one vocabulary serves the whole surface. */
    public static function stateLabel(ListingPreferenceState $state): string
    {
        return match ($state) {
            ListingPreferenceState::Save  => 'Saved',
            ListingPreferenceState::Maybe => 'Maybe',
            ListingPreferenceState::Pass  => 'Passed',
        };
    }

    /**
     * Reason LABELS from the governed catalog, never the stored keys.
     *
     * A key the catalog no longer publishes is dropped rather than printed:
     * `updated_kitchen` is not language, and a retired reason should not
     * reappear in a customer's history because it is still in an old row.
     *
     * @return list<string>
     */
    private function reasonLabels(ListingPreferenceEvent $event): array
    {
        $stored = $event->reasons_json;

        if (! is_array($stored)) {
            return [];
        }

        $catalog = ListingPreferenceReasonCatalog::all();
        $labels  = [];

        foreach ($stored as $key) {
            if (! is_string($key)) {
                continue;
            }

            $reason = $catalog[$key] ?? null;

            if ($reason !== null) {
                $labels[] = $reason->label;
            }
        }

        return $labels;
    }

    /**
     * Where the choice was made, in words — and only where that helps.
     *
     * An unrecognised or absent surface produces null and the page shows
     * nothing, rather than printing a stored token like `virtual_drive`.
     */
    private function surfaceLabel(mixed $surface): ?string
    {
        return match ($surface) {
            ListingPreferenceEvent::SURFACE_DETAIL        => 'Listing page',
            ListingPreferenceEvent::SURFACE_RESULTS       => 'Search results',
            ListingPreferenceEvent::SURFACE_ACCOUNT       => 'Your preferences',
            ListingPreferenceEvent::SURFACE_VIRTUAL_DRIVE => 'Virtual Drive',
            ListingPreferenceEvent::SURFACE_EXPLORE       => 'Explore',
            default                                       => null,
        };
    }
}
