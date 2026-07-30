<?php

namespace App\Services\LocationDna\Provenance;

/**
 * ProvenanceTransition — whether a change of origin is permitted, and by whom.
 *
 * G1e inert provenance model. INERT: this class DECIDES, it never performs. No persistence, no
 * mirror repair, no document merge, no command application.
 *
 * THE THREE RULES THAT DO THE WORK
 * --------------------------------
 * 1. An explicit owner action may always establish `OwnerAuthored` or `OwnerCleared`. That is what
 *    "the owner may always state their own intent" means, and it is the only route into an
 *    authoritative kind.
 * 2. An automatic system transition may never overwrite an authoritative value, and may never
 *    establish one. §5.4 rule 5 and §8.2 rule 2 both point here: an import never overwrites an
 *    authored dimension, and a recovered value never becomes authored on its own.
 * 3. Migration repair may move `LegacyFallback` to `LegacyRepaired` and nothing else. D-G1-6's
 *    approved rule is that lazy repair preserves provenance and may not convert inherited values
 *    into authored ones, so repair has exactly one legal edge.
 *
 * There is deliberately NO force or bypass path. A caller that believes it needs one is describing
 * an owner action, and should say so with {@see ProvenanceActor::ExplicitOwner}.
 *
 * WHERE THE ARCHITECTURE IS SILENT, THIS DENIES
 * ---------------------------------------------
 * Nothing in v1.2 authorises restoring a value from the retained snapshot, promoting an unknown
 * origin, or letting a derived value claim authorship. Each is denied here with a comment saying it
 * is denied by default rather than by an explicit prohibition — which is the honest distinction.
 */
final class ProvenanceTransition
{
    private function __construct(
        public readonly LocationDnaProvenanceKind $from,
        public readonly LocationDnaProvenanceKind $to,
        public readonly ProvenanceActor $actor,
    ) {
    }

    public static function of(
        LocationDnaProvenanceKind $from,
        LocationDnaProvenanceKind $to,
        ProvenanceActor $actor,
    ): self {
        return new self($from, $to, $actor);
    }

    /**
     * Is this transition permitted?
     *
     * Total: every (from, to, actor) triple gets an answer, and the answer defaults to false.
     */
    public function isAllowed(): bool
    {
        return match ($this->actor) {
            ProvenanceActor::ExplicitOwner   => $this->explicitOwnerAllowed(),
            ProvenanceActor::MigrationRepair => $this->migrationRepairAllowed(),
            ProvenanceActor::AutomaticSystem => $this->automaticSystemAllowed(),
        };
    }

    /** The reason a transition is refused, for a caller that wants to surface it. */
    public function refusalReason(): ?string
    {
        if ($this->isAllowed()) {
            return null;
        }

        if ($this->from->blocksFallbackResurrection() && ! $this->actor->isExplicitOwnerAction()) {
            return 'an explicit owner clear may not be automatically resurrected';
        }

        if ($this->from->isAuthoritative() && ! $this->actor->isExplicitOwnerAction()) {
            return 'an authoritative value may not be automatically overwritten';
        }

        if ($this->to->isOwnerStated() && ! $this->actor->isExplicitOwnerAction()) {
            return 'only an explicit owner action may establish owner-stated provenance';
        }

        if ($this->from === LocationDnaProvenanceKind::SnapshotRetained) {
            return 'the retained snapshot may not be an automatic restoration source';
        }

        if ($this->from === LocationDnaProvenanceKind::Unknown) {
            return 'an unknown origin may not be automatically promoted';
        }

        return 'the transition is not in the permitted set';
    }

    /**
     * @throws LocationDnaProvenanceException when refused
     */
    public function assertAllowed(): void
    {
        if (! $this->isAllowed()) {
            throw LocationDnaProvenanceException::forbiddenTransition(
                "{$this->from->value} -> {$this->to->value} by {$this->actor->value}: {$this->refusalReason()}",
            );
        }
    }

    /**
     * Explicit owner: may establish either owner-stated kind, from any origin.
     *
     * Including from `SnapshotRetained` and `Unknown` — an owner looking at a value and choosing to
     * author it is a legitimate act regardless of where it came from. What is forbidden is the
     * system doing that on the owner's behalf.
     *
     * An owner may NOT establish a non-owner kind: an owner action that produced `Derived` or
     * `LegacyRepaired` would be mislabelling its own authorship.
     */
    private function explicitOwnerAllowed(): bool
    {
        return $this->to->isOwnerStated();
    }

    /** Migration repair: exactly one legal edge, LegacyFallback -> LegacyRepaired. */
    private function migrationRepairAllowed(): bool
    {
        return $this->from === LocationDnaProvenanceKind::LegacyFallback
            && $this->to === LocationDnaProvenanceKind::LegacyRepaired;
    }

    /**
     * Automatic system: may only replace a non-authoritative value with another non-owner-stated
     * kind.
     *
     * Consequently denied, each for a stated reason:
     *   - anything out of `OwnerCleared` or `OwnerAuthored` — authoritative, not automatically
     *     overwritable
     *   - anything into `OwnerAuthored` or `OwnerCleared` — only the owner may state intent
     *   - anything out of `SnapshotRetained` — forbidden as a restoration source
     *   - anything out of `Unknown` — default-safe: an unreadable origin is not promotable
     */
    private function automaticSystemAllowed(): bool
    {
        if ($this->to->isOwnerStated()) {
            return false;
        }

        return $this->from->authority()->mayBeOverwrittenAutomatically();
    }
}
