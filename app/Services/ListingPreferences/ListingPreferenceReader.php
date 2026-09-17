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

    public function contextFor(SmartTagListingRef $ref): ?SmartTagContext
    {
        return $this->contexts->resolve($ref);
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
