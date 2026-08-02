<?php

namespace App\Services\LocationDna\Persistence;

use App\Services\LocationDna\Capability\LocationDnaCapability;
use App\Services\LocationDna\Capability\LocationDnaCapabilitySet;
use App\Services\LocationDna\Contract\DimensionCommand;
use App\Services\LocationDna\Contract\DimensionCommandApplier;
use App\Services\LocationDna\Contract\LocationDnaContractException;
use App\Services\LocationDna\Contract\LocationDnaDocument;
use App\Services\LocationDna\Contract\LocationDnaHydrator;
use App\Services\LocationDna\Contract\LocationDnaRevisionToken;
use App\Services\LocationDna\Contract\LocationDnaSerializer;
use App\Services\LocationDna\Provenance\LocationDnaProvenanceKind;
use App\Services\LocationDna\Provenance\ProvenanceActor;
use App\Services\LocationDna\Provenance\ProvenanceTransition;
use Illuminate\Support\Facades\DB;

/**
 * G1f-1 — the canonical writer.
 *
 * D-G1-5 names `LocationDnaPersistenceService` the sole canonical writer. G1f-1 introduces it and
 * migrates ONE workflow (`BuyerAgentAuction`); the other eight write sites are untouched, and the
 * §21 direct-writer guard records which remain.
 *
 * THE BINDING CONSTRAINT, EXPRESSED STRUCTURALLY
 * ----------------------------------------------
 * D-G1F-1 approved proceeding without persisted provenance ONLY under §10.2's condition: persist
 * solely dimensions carried by an explicit {@see DimensionCommand}, and perform no legacy repair.
 * This service cannot violate it, because it has no way to:
 *
 * - it never reads a legacy mirror — {@see LocationDnaWritableRecord} exposes no mirror reader;
 * - it never rewrites the whole hydrated document — {@see DimensionCommandApplier} touches only
 *   commanded dimensions and returns a new document;
 * - an empty batch returns before anything is read or written.
 *
 * So an inherited, imported, derived or legacy value cannot be promoted to owner-authored merely
 * because a different dimension was saved. That is the §10.3 hazard, closed by construction rather
 * than by bookkeeping.
 *
 * PROVENANCE IS TRANSIENT
 * -----------------------
 * The actor is accepted to VALIDATE the transition and nothing else. {@see ProvenanceTransition}
 * decides whether this actor may establish the owner-stated kind a command implies; the answer is
 * used and discarded. **No provenance is written, and no provenance column or table exists.**
 *
 * WHAT IT DOES NOT DO
 * -------------------
 * No HTTP request, no global `Auth`, no Livewire state, no arbitrary string dimensions, no raw
 * request arrays, no mirror repair, no provenance write, no snapshot read or write, no
 * `CriteriaHashService`, no Bridge, no public projection. Capabilities arrive already resolved so
 * the service never decides authorisation, only enforces it.
 */
final class LocationDnaPersistenceService
{
    public function __construct(
        private readonly LocationDnaHydrator $hydrator = new LocationDnaHydrator(),
        private readonly DimensionCommandApplier $applier = new DimensionCommandApplier(),
        private readonly LocationDnaSerializer $serializer = new LocationDnaSerializer(),
        private readonly LocationDnaRevisionToken $token = new LocationDnaRevisionToken(),
        private readonly LegacyMirrorProjection $mirrors = new LegacyMirrorProjection(),
    ) {
    }

    /**
     * Apply a validated batch atomically. Never partially applies.
     *
     * @param  list<DimensionCommand>  $commands  empty is legal and applies nothing
     */
    public function apply(
        LocationDnaWritableRecord $record,
        array $commands,
        LocationDnaCapabilitySet $capabilities,
        ProvenanceActor $actor,
    ): PatchResult {
        // ── 1. An empty batch states nothing. Return before reading anything at all. ──────
        // This is the no-op stop condition: no canonical write, no mirror write, no repair,
        // and no blob created for a legacy-only record.
        if ($commands === []) {
            return PatchResult::noOp();
        }

        // ── 2. Authorisation, before any read. A denial is never a skipped write. ────────
        if ($capabilities->denies(LocationDnaCapability::EditDocument)) {
            return PatchResult::rejected('capability_denied', 'The context does not permit editing the document.');
        }

        foreach ($commands as $command) {
            if (! $command instanceof DimensionCommand) {
                return PatchResult::rejected('invalid_value', 'A batch may contain only DimensionCommand instances.');
            }

            $permitted = $command->isClear()
                ? $capabilities->mayClear($command->dimension)
                : $capabilities->maySet($command->dimension);

            if (! $permitted) {
                return PatchResult::rejected(
                    'capability_denied',
                    "Dimension `{$command->dimension->value}` may not be mutated in this context.",
                );
            }

            // ── 3. Provenance transition validation — transient, never persisted. ────────
            $target = $command->isClear()
                ? LocationDnaProvenanceKind::OwnerCleared
                : LocationDnaProvenanceKind::OwnerAuthored;

            if (! ProvenanceTransition::of(LocationDnaProvenanceKind::Unknown, $target, $actor)->isAllowed()) {
                return PatchResult::rejected(
                    'provenance_forbidden',
                    "Actor `{$actor->value}` may not establish `{$target->value}`.",
                );
            }
        }

        // ── 4. Read and interpret current canonical state. ───────────────────────────────
        $hydration = $this->hydrator->hydrate($record->readCanonical());

        if ($hydration->isMalformed()) {
            // Never overwrite bytes we could not interpret, and never convert them to a clear.
            return PatchResult::rejected('unavailable', 'The stored document could not be interpreted.');
        }

        if ($hydration->isUnsupportedVersion()) {
            // §5.5: refuse to interpret, fail loudly, read-only.
            return PatchResult::rejected('unknown_schema_version', 'The stored document is a newer schema version.');
        }

        $current = $hydration->document() ?? LocationDnaDocument::emptyDocument();

        // ── 5. Apply, purely. The input document is not mutated. ─────────────────────────
        try {
            $next = $this->applier->apply($current, $commands);
        } catch (LocationDnaContractException $e) {
            return PatchResult::rejected('invalid_value', $e->getMessage());
        }

        $before = $this->token->forDocument($current);
        $after  = $this->token->forDocument($next);

        // ── 6. Canonical meaning unchanged ⇒ write nothing. ──────────────────────────────
        // Decided by the revision token, so "unchanged" means unchanged in MEANING, not in
        // byte layout. This keeps a re-save of identical values from rewriting storage.
        if ($before === $after) {
            return PatchResult::noOp($current, $before);
        }

        $json           = $this->serializer->toJson($next);
        $mirrorsToWrite = $this->mirrors->project($next);

        // ── 7. Canonical first, mirrors second, one transaction. ─────────────────────────
        // Reaffirmed constraint 2. If any mirror write fails the canonical write rolls back
        // with it, so blob and mirror can never be left disagreeing by a partial save.
        DB::transaction(function () use ($record, $json, $mirrorsToWrite): void {
            $record->writeCanonical($json);

            foreach ($mirrorsToWrite as $key => $value) {
                $record->writeMirror($key, $value);
            }
        });

        return PatchResult::changed($next, $after);
    }
}
