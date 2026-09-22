<?php

namespace App\Services\SmartTags\Seeker;

use App\Models\SmartTagSeekerPreference;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagContextResolver;
use App\Support\SmartTags\SmartTagSeekerPreferenceGate;
use App\Support\SmartTags\SmartTagSeekerSubjectType;
use App\Support\SmartTags\SmartTagSelectionPolicy;
use App\Support\SmartTags\SmartTagSelectionResult;
use App\Support\SmartTags\SmartTagTaxonomy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * THE write path for seeker Smart Tag preferences. There is no other.
 *
 * WHY THE GATE IS HERE AND NOT IN VALIDATION. The submitted value is an array of
 * strings from a form; a `in:` rule would have to enumerate 186 keys per context
 * and would go stale the day a tag is added. Worse, hiding an option in Blade is
 * a rendering decision, not a write boundary — a hand-crafted POST bypasses it
 * entirely. So every key is re-projected through SmartTagSelectionPolicy at the
 * write, with SURFACE_SEEKER, against the STORED property type. It is an
 * INTERSECTION: a key survives by being named in the taxonomy, applicable to
 * this context and seeker-selectable — never by escaping a deny-list.
 *
 * THE CONTEXT COMES FROM THE STORED RECORD, never from the request. One request
 * could otherwise flip the criteria to Commercial and submit commercial-only
 * keys in the same message — the same reasoning
 * CompatibilityPreferencePolicy::propertyTypeForProjection() already applies.
 *
 * A NULL CONTEXT REFUSES EVERYTHING. A criteria record whose property type is
 * unset or unrecognised has no context, so no tag is applicable and nothing is
 * written. That is the fail-closed direction: an unreadable property type must
 * never mean "allow anything".
 *
 * REPLACE SEMANTICS. `replaceSelections()` is the whole set for that record:
 * keys absent from the submission are deleted, which is how deselection works.
 * Unknown is the absence of a row — there is no "not wanted" state here, because
 * a seeker declining to tick "pool" is not a statement that they refuse one.
 */
class SmartTagSeekerPreferenceWriter
{
    /**
     * Replace this seeker record's entire tag selection — a Buyer/Tenant criteria
     * record or a Buyer/Tenant Offer Listing.
     *
     * @param array<int, mixed> $requested raw values from the request
     */
    public function replaceSelections(
        object $subject,
        array $requested,
        ?int $actingUserId,
    ): SmartTagSeekerPreferenceResult {
        // The gate is re-asserted HERE, not only at the call sites. A controller
        // that forgets the check, or a future call site added by someone who has
        // not read this file, must still be unable to write. Refusing is not the
        // same as replacing with nothing: nothing stored is touched, so turning
        // the feature off never deletes a selection a customer made while it was
        // on.
        if (! SmartTagSeekerPreferenceGate::writesEnabled()) {
            return SmartTagSeekerPreferenceResult::refused(
                SmartTagSeekerPreferenceResult::REFUSED_FEATURE_DISABLED
            );
        }

        $type = SmartTagSeekerSubjectType::forModel($subject);

        if ($type === null) {
            return SmartTagSeekerPreferenceResult::refused(
                SmartTagSeekerPreferenceResult::REFUSED_UNSUPPORTED_SUBJECT
            );
        }

        $subjectId = (int) ($subject->id ?? 0);
        if ($subjectId <= 0) {
            return SmartTagSeekerPreferenceResult::refused(
                SmartTagSeekerPreferenceResult::REFUSED_UNSAVED_SUBJECT
            );
        }

        // Ownership: auth()->check() first, because a guest's null id and a null
        // user_id both cast to 0 and would otherwise match each other.
        $ownerId = $subject->user_id ?? null;
        if ($actingUserId === null || $ownerId === null || (int) $ownerId !== $actingUserId) {
            return SmartTagSeekerPreferenceResult::refused(
                SmartTagSeekerPreferenceResult::REFUSED_NOT_OWNER
            );
        }

        $context = $this->contextFor($type, $subject);

        if ($context === null || ! in_array($context, $type->possibleContexts(), true)) {
            return SmartTagSeekerPreferenceResult::refused(
                SmartTagSeekerPreferenceResult::REFUSED_NO_CONTEXT
            );
        }

        $projection = SmartTagSelectionPolicy::project(
            $requested,
            $context,
            SmartTagTaxonomy::SURFACE_SEEKER,
        );

        $this->persist($type, $subjectId, $actingUserId, $context, $projection->accepted);

        return SmartTagSeekerPreferenceResult::applied($projection, $context);
    }

    /**
     * @param string[] $accepted
     */
    private function persist(
        SmartTagSeekerSubjectType $type,
        int $subjectId,
        int $userId,
        SmartTagContext $context,
        array $accepted,
    ): void {
        DB::transaction(function () use ($type, $subjectId, $userId, $context, $accepted) {
            $existing = SmartTagSeekerPreference::query()
                ->where('subject_type', $type->value)
                ->where('subject_id', $subjectId)
                ->pluck('tag_key')
                ->all();

            $remove = array_diff($existing, $accepted);
            if ($remove !== []) {
                SmartTagSeekerPreference::query()
                    ->where('subject_type', $type->value)
                    ->where('subject_id', $subjectId)
                    ->whereIn('tag_key', $remove)
                    ->delete();
            }

            foreach ($accepted as $key) {
                // updateOrCreate against the unique index: re-submitting an
                // unchanged selection must be a no-op, not a duplicate.
                SmartTagSeekerPreference::query()->updateOrCreate(
                    [
                        'subject_type' => $type->value,
                        'subject_id'   => $subjectId,
                        'tag_key'      => $key,
                    ],
                    [
                        'user_id'     => $userId,
                        'seeker_role' => $type->role()->value,
                        'context'     => $context->value,
                    ],
                );
            }
        });
    }

    private function contextFor(SmartTagSeekerSubjectType $type, object $subject): ?SmartTagContext
    {
        $propertyType = null;

        // Every seeker subject — criteria record or Offer Listing — stores
        // property_type as EAV meta, read through the model's own accessor exactly
        // as every other consumer reads it. The SUBJECT TYPE chooses the
        // vocabulary, because the four forms spell property types differently.
        if (isset($subject->get->property_type)) {
            $propertyType = $subject->get->property_type;
        }

        return SmartTagContextResolver::forSeekerSubject(
            $type,
            is_string($propertyType) ? $propertyType : null,
        );
    }

    /**
     * Remove every selection for a criteria record, used when that record is
     * deleted.
     *
     * DELIBERATELY NOT GATED — see SmartTagSeekerPreferenceGate::purgeAlwaysAllowed().
     * Rows written while the feature was on must still be cleaned up when the
     * criteria record is deleted later with the feature off, or switching the
     * flag off mints orphans. Scoped to one (subject_type, subject_id) pair, so
     * it can never reach another record's selections.
     */
    public function purge(SmartTagSeekerSubjectType $type, int $subjectId): int
    {
        if ($subjectId <= 0) {
            return 0;
        }

        return SmartTagSeekerPreference::query()
            ->where('subject_type', $type->value)
            ->where('subject_id', $subjectId)
            ->delete();
    }

    /**
     * Purge the preferences of rows a caller has just deleted, by model CLASS.
     * Never throws.
     *
     * For deletion paths that fire no model events — the shared Offer Listing
     * draft purge in {@see \App\Http\Livewire\Concerns\BelongsToListingWorkflow}
     * is a query-builder mass delete, so the observer cannot see it. The class
     * is resolved with {@see SmartTagSeekerSubjectType::forModelClass()}, not
     * forModel(): the rows are gone, and removing a deleted row's preferences is
     * correct whichever product it belonged to. A class that is not a seeker
     * subject (Seller, Landlord) is a no-op.
     *
     * NOT GATED, like {@see purge()}. A failure is logged with no subject data
     * beyond the type and a count and swallowed: a draft the user deleted must
     * never reappear because its preference rows could not be cleaned up, and
     * orphaned preference rows are inert.
     *
     * @param class-string|string $modelClass
     * @param array<int, mixed>   $ids
     */
    public static function tryPurgeDeleted(string $modelClass, array $ids): void
    {
        try {
            $type = SmartTagSeekerSubjectType::forModelClass($modelClass);

            if ($type === null) {
                return;
            }

            $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));

            if ($ids === []) {
                return;
            }

            SmartTagSeekerPreference::query()
                ->where('subject_type', $type->value)
                ->whereIn('subject_id', $ids)
                ->delete();
        } catch (\Throwable $e) {
            Log::warning('smart_tag_seeker_preferences purge failed', [
                'subject_type' => isset($type) ? $type->value : null,
                'id_count'     => count($ids),
                'exception'    => $e::class,
            ]);
        }
    }
}
