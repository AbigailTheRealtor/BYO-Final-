<?php

namespace App\Services\ListingPreferences;

use App\Models\ListingPreference;
use App\Models\ListingPreferenceEvent;
use App\Models\ListingPreferenceReason;
use App\Support\ListingPreferences\ListingPreferenceReasonCatalog;
use App\Support\ListingPreferences\ListingPreferenceReasonPolicy;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\ListingPreferences\ListingPreferenceSubjectRef;
use App\Support\ListingPreferences\SeekerRole;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagListingRef;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * THE one place a listing preference is written.
 *
 * No controller, Livewire component or Blade view touches the models. Every
 * surface — the property detail page now, search results, Virtual Drive and
 * recommendation surfaces later — calls this, so "what happens when a customer
 * presses Pass" has one answer rather than one per surface.
 *
 * THREE MUTATIONS, EACH ATOMIC
 * ----------------------------
 *   setState()      upsert the single current row, replace its reasons, append
 *                   one event
 *   updateReasons() replace the reasons of an existing current row, append one
 *                   event
 *   clear()         delete the current row (reasons cascade), append one event
 *                   whose to_state is NULL
 *
 * Each runs inside a transaction, because the current state, its reasons and
 * the history entry are one fact recorded in three tables. A partial write
 * would leave a state with another state's reasons, or a mutation with no audit
 * trail — and the events table is append-only, so a missing row cannot be
 * repaired later.
 *
 * ONE EVENT PER COMPLETED MUTATION, NOT PER CHIP. Selecting and deselecting
 * chips is browsing; the customer pressing Done is the decision. So the reason
 * tray sends one request with the final set, and that produces exactly one
 * event carrying the whole snapshot.
 *
 * REASONS NEVER SURVIVE A STATE CHANGE. "Too expensive" is not an answer to
 * "What do you like about this property?", so setState() replaces the reason
 * set outright rather than merging. A caller that passes no reasons with a new
 * state clears them, which is the honest reading of "the customer has not
 * answered the new question yet".
 *
 * EVERYTHING IS RESOLVED SERVER-SIDE. The subject key comes from
 * ListingPreferenceSubjectResolver, the context from
 * ListingPreferenceContextResolver, the seeker role from the caller's
 * authenticated account. Nothing here trusts a value shaped by a browser.
 *
 * IT NEVER TOUCHES THE LISTING. No MLS row, no listing meta, no status. Pass is
 * preference data about a listing, never a change to it.
 */
class ListingPreferenceWriter
{
    public function __construct(
        private readonly ListingPreferenceSubjectResolver $subjects,
        private readonly ListingPreferenceContextResolver $contexts,
    ) {
    }

    /**
     * Record Save, Maybe or Pass, replacing any current state for this subject.
     *
     * @param  array<int, mixed> $requestedReasons raw chip keys; validated here
     */
    public function setState(
        int $userId,
        SeekerRole $role,
        SmartTagListingRef $ref,
        ListingPreferenceState $state,
        array $requestedReasons = [],
        ?string $surface = null,
    ): ListingPreferenceOutcome {
        $subject = $this->requireSubject($ref);
        $context = $this->contexts->resolve($ref);

        $selection = ListingPreferenceReasonPolicy::project($requestedReasons, $state, $context);

        return DB::transaction(function () use ($userId, $role, $ref, $subject, $state, $selection, $surface, $context) {
            $existing = $this->currentFor($userId, $role, $subject);
            $from     = $existing === null ? null : ListingPreferenceState::tryFrom((string) $existing->state);

            $preference = ListingPreference::updateOrCreate(
                [
                    'user_id'     => $userId,
                    'seeker_role' => $role->value,
                    'subject_key' => $subject->subjectKey,
                ],
                [
                    // The acted-on reference is refreshed: the same subject may
                    // be reached through a different listing next time, and the
                    // audit of WHICH one is the event's job, not this row's.
                    'listing_type'  => $ref->type->value,
                    'listing_id'    => $ref->id,
                    'state'         => $state->value,
                    'state_set_at'  => now(),
                ]
            );

            $this->replaceReasons($preference, $selection->accepted);

            $this->recordEvent($userId, $role, $ref, $subject, $from, $state, $selection->accepted, $surface);

            return new ListingPreferenceOutcome(
                state:    $state,
                reasons:  $selection->accepted,
                rejected: $selection->rejected,
                context:  $context,
                subject:  $subject,
            );
        });
    }

    /**
     * Replace the reasons behind an EXISTING current state.
     *
     * Refuses when there is no current state: reasons explain a choice, and a
     * reason set with nothing to explain is not a thing this system stores.
     *
     * @param  array<int, mixed> $requestedReasons
     */
    public function updateReasons(
        int $userId,
        SeekerRole $role,
        SmartTagListingRef $ref,
        array $requestedReasons,
        ?string $surface = null,
    ): ListingPreferenceOutcome {
        $subject = $this->requireSubject($ref);
        $context = $this->contexts->resolve($ref);

        return DB::transaction(function () use ($userId, $role, $ref, $subject, $requestedReasons, $surface, $context) {
            $existing = $this->currentFor($userId, $role, $subject, lock: true);

            if ($existing === null) {
                throw new ListingPreferenceMissing(
                    'There is no current preference on this listing to attach reasons to.'
                );
            }

            $state = ListingPreferenceState::tryFrom((string) $existing->state);

            if ($state === null) {
                throw new RuntimeException('Stored preference state is not a recognised value.');
            }

            $selection = ListingPreferenceReasonPolicy::project($requestedReasons, $state, $context);

            $this->replaceReasons($existing, $selection->accepted);

            // The state did not move, so from and to are the same — this is a
            // reason revision, and the snapshot is what changed.
            $this->recordEvent($userId, $role, $ref, $subject, $state, $state, $selection->accepted, $surface);

            return new ListingPreferenceOutcome(
                state:    $state,
                reasons:  $selection->accepted,
                rejected: $selection->rejected,
                context:  $context,
                subject:  $subject,
            );
        });
    }

    /**
     * Undo: remove the current preference entirely, preserving the history.
     *
     * The current row is deleted and its reasons cascade with it — a reason
     * cannot outlive the choice it explains. The event records the prior state
     * in `from_state` and NULL in `to_state`, which after the Phase 2 migration
     * means exactly "no current preference after this transition". No fourth
     * state, no overloaded `pass`, and nothing removed from the timeline.
     *
     * Idempotent: clearing what is already clear writes no event, because no
     * transition happened.
     */
    public function clear(
        int $userId,
        SeekerRole $role,
        SmartTagListingRef $ref,
        ?string $surface = null,
    ): ListingPreferenceOutcome {
        $subject = $this->requireSubject($ref);
        $context = $this->contexts->resolve($ref);

        return DB::transaction(function () use ($userId, $role, $ref, $subject, $surface, $context) {
            $existing = $this->currentFor($userId, $role, $subject, lock: true);

            if ($existing === null) {
                return new ListingPreferenceOutcome(null, [], [], $context, $subject);
            }

            $from = ListingPreferenceState::tryFrom((string) $existing->state);

            // Reasons are removed explicitly as well as by cascade: the cascade
            // is the database's guarantee, this is the application's, and the
            // two together mean a clear cannot leave orphaned reasons on any
            // driver whose foreign keys happen to be disabled.
            ListingPreferenceReason::where('listing_preference_id', $existing->id)->delete();
            $existing->delete();

            $this->recordEvent($userId, $role, $ref, $subject, $from, null, [], $surface);

            return new ListingPreferenceOutcome(null, [], [], $context, $subject);
        });
    }

    private function requireSubject(SmartTagListingRef $ref): ListingPreferenceSubjectRef
    {
        $subject = $this->subjects->resolve($ref);

        if ($subject === null) {
            // A Bridge row with no listing key has no durable identity. Storing
            // a preference against an invented key would attach it to whatever
            // that key later means.
            throw new ListingPreferenceSubjectUnresolvable(
                'This listing has no durable identity, so a preference cannot be stored against it.'
            );
        }

        return $subject;
    }

    private function currentFor(
        int $userId,
        SeekerRole $role,
        ListingPreferenceSubjectRef $subject,
        bool $lock = false,
    ): ?ListingPreference {
        $query = ListingPreference::query()
            ->where('user_id', $userId)
            ->where('seeker_role', $role->value)
            ->where('subject_key', $subject->subjectKey);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * @param list<string> $reasonKeys already projected through the policy
     */
    private function replaceReasons(ListingPreference $preference, array $reasonKeys): void
    {
        ListingPreferenceReason::where('listing_preference_id', $preference->id)->delete();

        if ($reasonKeys === []) {
            return;
        }

        $now  = now();
        $rows = [];

        foreach ($reasonKeys as $key) {
            $definition = ListingPreferenceReasonCatalog::get($key);

            if ($definition === null) {
                continue;
            }

            $rows[] = [
                'listing_preference_id' => $preference->id,
                'reason_key'            => $key,
                // Denormalised deliberately: a stored reason must stay
                // interpretable after the catalog changes. This is what the
                // chip MEANT when it was chosen, not a cache of what it means.
                'dimension'             => $definition->dimension->value,
                'smart_tag_key'         => $definition->smartTagKey,
                'created_at'            => $now,
            ];
        }

        if ($rows !== []) {
            ListingPreferenceReason::insert($rows);
        }
    }

    /**
     * @param list<string> $reasonKeys
     */
    private function recordEvent(
        int $userId,
        SeekerRole $role,
        SmartTagListingRef $ref,
        ListingPreferenceSubjectRef $subject,
        ?ListingPreferenceState $from,
        ?ListingPreferenceState $to,
        array $reasonKeys,
        ?string $surface,
    ): void {
        ListingPreferenceEvent::create([
            'user_id'      => $userId,
            'seeker_role'  => $role->value,
            'listing_type' => $ref->type->value,
            'listing_id'   => $ref->id,
            'subject_key'  => $subject->subjectKey,
            'from_state'   => $from?->value,
            'to_state'     => $to?->value,
            'reasons_json' => $reasonKeys,
            'surface'      => $surface,
            'created_at'   => now(),
        ]);
    }
}
