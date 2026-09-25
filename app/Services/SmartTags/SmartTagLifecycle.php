<?php

namespace App\Services\SmartTags;

use App\Models\BridgeProperty;
use App\Models\User;
use App\Support\SmartTags\OwnerSmartTagPanel;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagWiring;
use App\Support\SmartTags\SmartTagVersion;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * THE seam between the application and Smart Tags. Phase 2's whole surface.
 *
 * Every automatic call site — Bridge single-record lookup, Seller publish,
 * Landlord publish, quick-import publish, draft purge, the backfill command —
 * goes through this class and nothing else. No call site constructs the
 * derivation service, the evidence writer, the resolver, the projector or the
 * purger; a guard test asserts it. One seam means one place where the gates are
 * checked, one place that catches, and one place that logs.
 *
 * THE CONTRACT, in one sentence: Smart Tags are secondary derived data, and the
 * listing or property save is primary. Nothing here may fail, roll back, or even
 * slow down the operation that called it.
 *
 * So every entry point:
 *
 *   • checks the activation gates FIRST and returns without touching the
 *     database when they are closed;
 *   • catches Throwable — Error as well as Exception, because a TypeError in a
 *     deriver is exactly as unacceptable as a failed query;
 *   • never rethrows, and returns null rather than a partial result;
 *   • logs one structured line, with no listing prose in it.
 *
 * TRANSACTIONS. Phase 1's derivation opens its own transaction and its writer
 * and projector open nested ones, which Laravel implements as savepoints. That
 * is safe ONLY while no enclosing application transaction is open, because a
 * rollback to a savepoint inside somebody else's transaction still leaves their
 * transaction marked and may take their work with it. Every call site therefore
 * calls this class AFTER its own transaction has committed — the draft purge
 * calls it after DB::transaction() returns rather than inside the closure — and
 * a test pins that placement.
 */
class SmartTagLifecycle
{
    public function __construct(
        private readonly SmartTagDerivationService $derivation,
        private readonly SmartTagAssignmentPurger $purger,
        private readonly ManualSmartTagWriter $manual,
    ) {
    }

    /* ─────────────────────────────────────────────────────────────────────
     * The call-site API. Static, and structurally incapable of throwing.
     *
     * The instance methods below already catch everything they do. These three
     * additionally cover what an instance method cannot: RESOLVING the lifecycle
     * out of the container. A missing binding, a provider that has not booted, a
     * constructor fault in a dependency — each of those throws at `app()`, before
     * any instance catch exists, and would take the listing save or the Bridge
     * upsert with it.
     *
     * That is not hypothetical defensiveness. The seams have uneven protection of
     * their own: the Livewire publish methods catch `\Exception`, so an `\Error`
     * escapes; the bulk MLS importer catches `\Throwable` but converts it into a
     * FAILED IMPORT; and the single-record Bridge seam, the draft purge and the
     * console importer catch nothing at all. Relying on each of those to be right
     * is how the "Smart Tags can never fail a save" guarantee stops being true.
     *
     * One shim, six call sites, no duplicated try/catch.
     * ───────────────────────────────────────────────────────────────────── */

    public static function tryDeriveBridge(BridgeProperty $property, string $entryPoint = SmartTagTelemetry::ENTRY_BRIDGE_LOOKUP): void
    {
        try {
            app(self::class)->deriveForBridgeSilently($property, $entryPoint);
        } catch (Throwable $e) {
            self::reportShimFailure($entryPoint, $e);
        }
    }

    public static function tryDeriveNative(Model $listing, string $entryPoint): void
    {
        try {
            app(self::class)->deriveForNativeSilently($listing, $entryPoint);
        } catch (Throwable $e) {
            self::reportShimFailure($entryPoint, $e);
        }
    }

    /**
     * @param class-string|string $modelClass
     * @param array<int|string>   $ids
     */
    public static function tryPurge(string $modelClass, array $ids): void
    {
        try {
            app(self::class)->purgeSilently($modelClass, $ids);
        } catch (Throwable $e) {
            self::reportShimFailure(SmartTagTelemetry::ENTRY_PURGE, $e);
        }
    }

    /**
     * Persist a listing owner's manual Smart Tag selections. Never throws.
     *
     * The Seller/Landlord "Property Features" picker's write, and the first
     * production caller ManualSmartTagWriter has ever had. Called at PUBLISH,
     * after the listing's own save has committed and after derivation, so the
     * structured answers the writer prunes against are the ones the owner just
     * submitted rather than the previous version's.
     *
     * NEVER ON A DRAFT SAVE, for the reason derivation is not: `SAVE_AS_NEW_DRAFT`
     * makes every draft save a new row, so a draft's evidence would belong to a
     * version nobody reads. The owner's ticks survive a draft as ordinary listing
     * meta ({@see \App\Support\SmartTags\OwnerSmartTagSelection}) and become
     * evidence when the listing becomes a listing.
     */
    public static function trySaveOwnerSelections(Model $listing, array $keys, ?User $actor, string $entryPoint): ?ManualSelectionResult
    {
        try {
            return app(self::class)->saveOwnerSelectionsSilently($listing, $keys, $actor, $entryPoint);
        } catch (Throwable $e) {
            self::reportShimFailure($entryPoint, $e);

            return null;
        }
    }

    /**
     * What the Seller/Landlord picker renders. Never throws, and writes nothing.
     *
     * Withheld entirely when the activation gate is closed, so the control and
     * the write agree about whether the feature exists — a rendered checkbox
     * whose save silently does nothing is worse than no checkbox, the same rule
     * {@see \App\Support\ListingPreferences\ListingPreferenceAvailability}
     * applies to its own surface.
     *
     * @param string[] $selected
     */
    public static function ownerPanel(SmartTagListingType $type, ?string $propertyType, ?int $listingId, array $selected): OwnerSmartTagPanel
    {
        try {
            if (! SmartTagWiring::enabledFor($type)) {
                return OwnerSmartTagPanel::unavailable(OwnerSmartTagPanel::REASON_DISABLED);
            }

            return app(OwnerSmartTagPanelBuilder::class)->build($type, $propertyType, $listingId, $selected);
        } catch (Throwable $e) {
            self::reportShimFailure(SmartTagTelemetry::ownerTagsEntryPoint($type), $e);

            // A half-built picker would offer a set of tags nobody projected.
            return OwnerSmartTagPanel::unavailable(OwnerSmartTagPanel::REASON_ERROR);
        }
    }

    /**
     * How much of the Bridge inventory carries governed Smart Tags. READ ONLY.
     *
     * DELIBERATELY UNGATED, unlike every other entry point here: it writes nothing,
     * and the question it answers — "is there enough coverage to switch anything
     * on?" — has to be askable while the gates are still closed, which is exactly
     * when it matters. It is also the one entry point allowed to THROW: its only
     * caller is an operator command, where a swallowed fault would print a report
     * of zeros that reads as "no coverage" rather than "the audit failed".
     *
     * @param string[] $statuses
     * @return array<string, mixed>
     */
    public static function bridgeCoverage(?string $provider, array $statuses, bool $simulate, int $batchSize): array
    {
        return app(Coverage\BridgeSmartTagCoverageAuditor::class)->audit($provider, $statuses, $simulate, $batchSize);
    }

    /**
     * Last resort. Even telemetry is wrapped: the logger is itself resolved from
     * the container, and a shim that throws while reporting that something threw
     * would defeat the entire point of the shim.
     */
    private static function reportShimFailure(string $entryPoint, Throwable $e): void
    {
        try {
            SmartTagTelemetry::record(SmartTagTelemetry::FAILED, $entryPoint, null, error: $e);
        } catch (Throwable) {
            // Nothing further is safe to attempt here.
        }
    }

    /**
     * Derive one Bridge property's Smart Tags. Never throws.
     *
     * Needs the master gate AND the Bridge gate. Callers pass single records
     * only: the high-volume import paths opt out at the call site rather than
     * being rationed here, because a ceiling inside this method would still have
     * paid for the model hydration and the state lookup.
     */
    public function deriveForBridgeSilently(BridgeProperty $property, string $entryPoint = SmartTagTelemetry::ENTRY_BRIDGE_LOOKUP): ?DerivationOutcome
    {
        if (! SmartTagWiring::enabledFor(SmartTagListingType::Bridge)) {
            return null;
        }

        return $this->guard(
            $entryPoint,
            fn () => $this->derivation->deriveBridge($property),
            fn () => $this->refFor($property),
        );
    }

    /**
     * Derive one native Seller/Landlord Offer Listing's Smart Tags. Never throws.
     *
     * Needs the master gate only. The listing must already be persisted with its
     * workflow stamp, property type and every governed meta value written — this
     * reads the database, not the caller's in-memory form state, so a call made
     * before saveAllMetadata() would derive from the previous save.
     *
     * A Hire Agent row, an archived listing or a listing whose property type is
     * not one of the seven contexts is refused by the derivation service itself
     * and comes back as a skip, not an error.
     */
    public function deriveForNativeSilently(Model $listing, string $entryPoint): ?DerivationOutcome
    {
        $type = SmartTagListingType::forModelClass(get_class($listing));

        if ($type === null || ! $type->isNative() || ! SmartTagWiring::enabledFor($type)) {
            return null;
        }

        return $this->guard(
            $entryPoint,
            fn () => $this->derivation->deriveNative($listing),
            fn () => $this->refFor($listing),
        );
    }

    /**
     * Remove the Smart Tag rows of listings that have just been deleted. Never throws.
     *
     * Called with the model CLASS and the ids, because the caller deletes through
     * the query builder and has no models left to hand over — which is also why
     * no model event can do this job.
     *
     * A class Smart Tags do not attach to (Buyer, Tenant, anything else) is
     * skipped silently: the shared purge serves all four roles and both products,
     * and refusing loudly there would turn an ordinary Buyer draft deletion into
     * an error report about a feature that has nothing to do with it.
     *
     * The gates are checked here too. With Smart Tags off there are no rows this
     * branch could have written, so there is nothing to clean up; rows written
     * while the gate was open and orphaned after it closed are inert (nothing
     * reads them) and are removed by the next purge or backfill once it reopens.
     *
     * @param class-string|string $modelClass
     * @param array<int|string>   $ids
     */
    public function purgeSilently(string $modelClass, array $ids): void
    {
        $type = SmartTagListingType::forModelClass($modelClass);

        if ($type === null || $ids === [] || ! SmartTagWiring::enabledFor($type)) {
            return;
        }

        foreach ($ids as $id) {
            $id = (int) $id;

            if ($id <= 0) {
                continue;
            }

            $ref = new SmartTagListingRef($type, $id);

            try {
                $counts = $this->purger->forListing($ref);

                SmartTagTelemetry::record(
                    SmartTagTelemetry::PURGED,
                    SmartTagTelemetry::ENTRY_PURGE,
                    $ref,
                    extra: [
                        'evidence_removed'    => $counts['evidence'],
                        'assignments_removed' => $counts['assignments'],
                        'states_removed'      => $counts['states'],
                    ],
                );
            } catch (Throwable $e) {
                // One listing's purge failing must not stop the others, and must
                // not reach the caller: the listing rows are already gone and the
                // deletion the user asked for has succeeded.
                SmartTagTelemetry::record(
                    SmartTagTelemetry::FAILED,
                    SmartTagTelemetry::ENTRY_PURGE,
                    $ref,
                    error: $e,
                );
            }
        }
    }

    /**
     * Hand one listing's manual selections to the writer. Never throws.
     *
     * The gate is checked here as well as at the picker: the two are asked at
     * different moments, and a gate that closed between render and publish must
     * stop the write, not merely have stopped the render.
     *
     * A REFUSAL IS NOT A FAILURE. The writer refuses an intruder, a Hire Agent
     * row, an archived listing and a listing with no supported property type, and
     * each of those is the system working. Only a throw is logged as `failed`.
     *
     * @param array<int, mixed> $keys
     */
    public function saveOwnerSelectionsSilently(Model $listing, array $keys, ?User $actor, string $entryPoint): ?ManualSelectionResult
    {
        $type = SmartTagListingType::forModelClass(get_class($listing));

        if ($type === null || ! $type->isNative() || ! SmartTagWiring::enabledFor($type)) {
            return null;
        }

        $ref = $this->refFor($listing);

        if ($ref === null) {
            return null;
        }

        $startedAt = microtime(true);

        try {
            $result = $this->manual->replaceSelections($ref, $keys, $actor);

            SmartTagTelemetry::record(
                $result->saved ? SmartTagTelemetry::OWNER_TAGS_SAVED : SmartTagTelemetry::OWNER_TAGS_REFUSED,
                $entryPoint,
                $ref,
                null,
                (microtime(true) - $startedAt) * 1000,
                extra: [
                    // Counts and a refusal reason from a closed list. No tag key,
                    // because a key is a property characteristic and the taxonomy
                    // version already explains what the counts mean.
                    'refusal'        => $result->refusal,
                    'selected'       => count($result->selected),
                    'added'          => count($result->added),
                    'removed'        => count($result->removed),
                    'rejected'       => count($result->rejected),
                    'tagger_version' => SmartTagVersion::taggerVersion(),
                ],
            );

            return $result;
        } catch (Throwable $e) {
            SmartTagTelemetry::record(
                SmartTagTelemetry::FAILED,
                $entryPoint,
                $ref,
                null,
                (microtime(true) - $startedAt) * 1000,
                $e,
            );

            return null;
        }
    }

    /**
     * Run one derivation, time it, classify it, log it, and swallow anything it throws.
     *
     * @param callable(): DerivationOutcome   $run
     * @param callable(): ?SmartTagListingRef $ref
     */
    private function guard(string $entryPoint, callable $run, callable $ref): ?DerivationOutcome
    {
        $listing = null;
        $startedAt = microtime(true);

        try {
            $listing = $ref();
            $outcome = $run();

            SmartTagTelemetry::record(
                self::outcomeName($outcome),
                $entryPoint,
                $listing,
                $outcome,
                (microtime(true) - $startedAt) * 1000,
                extra: [
                    // Abbreviated by the telemetry class. It is here so a run can
                    // be attributed to a taxonomy version after the fact — the
                    // one thing that explains why everything suddenly re-derived.
                    'tagger_version' => SmartTagVersion::taggerVersion(),
                ],
            );

            return $outcome;
        } catch (Throwable $e) {
            SmartTagTelemetry::record(
                SmartTagTelemetry::FAILED,
                $entryPoint,
                $listing,
                null,
                (microtime(true) - $startedAt) * 1000,
                $e,
            );

            return null;
        }
    }

    /**
     * Which of the closed set of outcomes this derivation was.
     *
     * A conflict is reported in preference to a plain "tagged" because it is the
     * one successful outcome somebody may want to look at: two sources disagreed
     * about the same property and the resolver had to pick.
     */
    private static function outcomeName(DerivationOutcome $outcome): string
    {
        if (! $outcome->derived) {
            return match ($outcome->skippedReason) {
                DerivationOutcome::NOT_AN_OFFER_LISTING => SmartTagTelemetry::NOT_OFFER_LISTING,
                DerivationOutcome::ARCHIVED             => SmartTagTelemetry::ARCHIVED,
                DerivationOutcome::NO_CONTEXT           => SmartTagTelemetry::NO_CONTEXT,
                default                                 => SmartTagTelemetry::SKIPPED_UNCHANGED,
            };
        }

        if (in_array(DerivationOutcome::DESCRIPTION_SUPPRESSED, $outcome->notes, true)) {
            return SmartTagTelemetry::DESCRIPTION_SUPPRESSED;
        }

        if (! $outcome->structuredDerived && ! $outcome->descriptionParsed) {
            return SmartTagTelemetry::SKIPPED_UNCHANGED;
        }

        $resolution = $outcome->resolution;

        if ($resolution !== null) {
            foreach ($resolution->assignments as $assignment) {
                if ($assignment->hasConflict) {
                    return SmartTagTelemetry::CONFLICT;
                }
            }

            if ($resolution->droppedForConflict !== []) {
                return SmartTagTelemetry::CONFLICT;
            }
        }

        return SmartTagTelemetry::TAGGED;
    }

    private function refFor(Model $model): ?SmartTagListingRef
    {
        try {
            return SmartTagListingRef::fromModel($model);
        } catch (Throwable) {
            // An unsaved model or one Smart Tags do not attach to. The caller's
            // own guards should have caught it; the log line simply loses its id.
            return null;
        }
    }
}
