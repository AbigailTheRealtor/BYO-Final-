<?php

namespace App\Services\ListingPreferences;

use App\Models\ListingPreference;
use App\Support\ListingPreferences\ListingPreferenceReasonCatalog;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\ListingPreferences\SeekerRole;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagListingRef;

/**
 * Reads a customer's current preference, and the chips they may be offered.
 *
 * Read-only counterpart to {@see ListingPreferenceWriter}, kept separate so a
 * render can never accidentally write: the component asks this, the controller
 * asks the writer, and neither can do the other's job.
 *
 * The offerable chips come from {@see ListingPreferenceReasonCatalog} filtered
 * by state and by the listing's resolved context — never from a list held in a
 * Blade template or a JavaScript file. A surface that hard-coded its own chips
 * would be a second vocabulary, and the Fair Housing exclusions that travel
 * with the catalog would not travel with it.
 */
class ListingPreferenceReader
{
    public function __construct(
        private readonly ListingPreferenceSubjectResolver $subjects,
        private readonly ListingPreferenceContextResolver $contexts,
    ) {
    }

    /**
     * The current state and reasons for one viewer and one listing.
     *
     * @return array{state: ?string, reasons: list<string>}
     */
    public function current(int $userId, SeekerRole $role, SmartTagListingRef $ref): array
    {
        $subject = $this->subjects->resolve($ref);

        if ($subject === null) {
            return ['state' => null, 'reasons' => []];
        }

        $preference = ListingPreference::query()
            ->with('reasons')
            ->where('user_id', $userId)
            ->where('seeker_role', $role->value)
            ->where('subject_key', $subject->subjectKey)
            ->first();

        if ($preference === null) {
            return ['state' => null, 'reasons' => []];
        }

        return [
            'state'   => (string) $preference->state,
            'reasons' => $preference->reasons->pluck('reason_key')->values()->all(),
        ];
    }

    /**
     * The current state and reasons for one viewer across MANY listings.
     *
     * THE POINT IS THE QUERY COUNT, not convenience. `current()` costs a
     * subject resolution plus a preference read per listing; a results page of
     * 150 cards calling it per card is 300+ queries for one screen. This is
     * bounded instead: one subject query per listing TYPE (through the resolver
     * that already batches), then ONE preference query for the whole page with
     * its reasons eager-loaded. Adding a card does not add a query.
     *
     * Every requested ref is present in the result, including listings the
     * customer has expressed nothing about and listings with no durable
     * subject — both as the same empty shape `current()` returns, so a caller
     * cannot accidentally treat "absent" as "unknown" and re-read per card.
     *
     * @param  list<SmartTagListingRef> $refs
     * @return array<string, array{state: ?string, reasons: list<string>}> keyed "<type>:<id>"
     */
    public function currentMany(int $userId, SeekerRole $role, array $refs): array
    {
        $empty = ['state' => null, 'reasons' => []];

        /** @var array<string, array{state: ?string, reasons: list<string>}> $out */
        $out = [];

        foreach ($refs as $ref) {
            $out["{$ref->type->value}:{$ref->id}"] = $empty;
        }

        if ($out === []) {
            return [];
        }

        $subjects = $this->subjects->resolveMany($refs);

        if ($subjects === []) {
            return $out;
        }

        $subjectKeys = [];
        foreach ($subjects as $subject) {
            $subjectKeys[$subject->subjectKey] = $subject->subjectKey;
        }

        // ONE query for the page. `with('reasons')` adds the single eager-load
        // query rather than one per row.
        $preferences = ListingPreference::query()
            ->with('reasons')
            ->where('user_id', $userId)
            ->where('seeker_role', $role->value)
            ->whereIn('subject_key', array_values($subjectKeys))
            ->get()
            ->keyBy('subject_key');

        foreach ($subjects as $refKey => $subject) {
            $preference = $preferences->get($subject->subjectKey);

            if ($preference === null) {
                continue;
            }

            // Two refs can share one subject — a Bridge row and the BidYourOffer
            // listing imported from it are the same property — and both
            // deliberately receive the SAME state. That is the canonicalisation
            // working, not a duplicate.
            $out[$refKey] = [
                'state'   => (string) $preference->state,
                'reasons' => $preference->reasons->pluck('reason_key')->values()->all(),
            ];
        }

        return $out;
    }

    public function contextFor(SmartTagListingRef $ref): ?SmartTagContext
    {
        return $this->contexts->resolve($ref);
    }

    /**
     * Contexts for many listings, one query per listing type.
     *
     * Delegates to the resolver's own batched form for the same reason
     * {@see currentMany()} exists. A ref whose context cannot be resolved is
     * present with a null value, never missing.
     *
     * @param  list<SmartTagListingRef> $refs
     * @return array<string, ?SmartTagContext> keyed "<type>:<id>"
     */
    public function contextForMany(array $refs): array
    {
        return $this->contexts->resolveMany($refs);
    }

    /**
     * The chips offerable for each state, in display order.
     *
     * Built once per render and handed to the view, so the tray switches state
     * without another request and without the browser deciding what is
     * eligible.
     *
     * @return array<string, array{prompt: string, chips: list<array{key: string, label: string}>}>
     */
    public function offerableChips(?SmartTagContext $context): array
    {
        $out = [];

        foreach (ListingPreferenceState::cases() as $state) {
            $chips = [];

            foreach (ListingPreferenceReasonCatalog::forState($state, $context) as $key => $reason) {
                $chips[] = ['key' => $key, 'label' => $reason->label];
            }

            $out[$state->value] = [
                'prompt' => $state->prompt(),
                'chips'  => $chips,
            ];
        }

        return $out;
    }
}
