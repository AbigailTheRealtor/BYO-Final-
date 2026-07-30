<?php

namespace App\Services\LocationDna\Provenance;

/**
 * LocationDnaProvenanceKind — where a dimension value came from, and with what standing.
 *
 * G1e inert provenance model. INERT: defined only. Nothing reads or writes a database, repairs a
 * mirror, merges a document, applies a command or resolves a capability.
 *
 * WHAT PROVENANCE IS NOT
 * ----------------------
 * v1.2 §10 is emphatic: "Provenance answers exactly one question: which provider produced this
 * value? It is not a state machine, and it never encodes authored/cleared/absent." G1e observes
 * that boundary in the direction that matters — provenance here classifies ORIGIN and standing, and
 * it is never the mechanism by which presence is decided. Presence remains structural in the G1c
 * document (`array_key_exists`, settled decision 5), and this enum is not consulted to answer it.
 *
 * Settled decision 6 forbids `dimension_meta` — a parallel per-dimension structure duplicating
 * presence/absence. §3's clarification draws the line precisely: provenance must never answer the
 * absence question. `OwnerCleared` therefore records *that an explicit clear was the origin of the
 * current state*; it is not, and must not become, the thing a reader consults to learn whether the
 * dimension is present. If a future design starts asking this enum "is it absent?", it has become
 * `dimension_meta` and must be rejected.
 *
 * THE DISTINCTIONS THIS ENUM EXISTS TO KEEP
 * -----------------------------------------
 * Five collapses would each lose real information, and each is separately prohibited:
 *
 *   absent vs cleared                   — §5.2; absence carries no provenance at all
 *   authored vs repaired                — §5.4 rule 5; storage state is not authorship
 *   inherited vs derived                — a copied value and a computed value differ in origin
 *   legacy fallback vs legacy repaired  — one is read-through, the other is written canonical
 *   snapshot vs authored canonical      — G1b F-G1B-7; retention is not authority
 */
enum LocationDnaProvenanceKind: string
{
    /**
     * The owner explicitly set this value. Authoritative.
     *
     * The only kind that speaks for the owner's positive intent.
     */
    case OwnerAuthored = 'owner_authored';

    /**
     * The owner explicitly cleared this dimension. Authoritative.
     *
     * §5.4 S4: an intentional clear is "a durable statement of user intent". This kind is what
     * blocks a mirror, an inherited value, a derived value or a snapshot from resurrecting it.
     */
    case OwnerCleared = 'owner_cleared';

    /**
     * Read through from a legacy discrete mirror. Non-authoritative.
     *
     * §5.4 S5: the value is "inherited, not authored", and it "does not become authored until the
     * user next writes that dimension". Visible now does not mean authored.
     */
    case LegacyFallback = 'legacy_fallback';

    /**
     * A legacy mirror value copied into canonical storage by lazy repair. Non-authoritative.
     *
     * The distinction that matters: repair changes *storage state*, not *authorship*. D-G1-6's
     * approved rule is that lazy repair "must preserve provenance and may not convert inherited
     * values into authored values", so a repaired value sits in the canonical document while still
     * being distinguishable from one the owner wrote.
     */
    case LegacyRepaired = 'legacy_repaired';

    /**
     * Copied from a parent workflow, template, cloned listing or prior document.
     * Non-authoritative until explicitly re-authored.
     *
     * §16.4's cloning rule cares about exactly this: a copied presence set must not silently become
     * the copy's own authored intent.
     */
    case Inherited = 'inherited';

    /**
     * Computed by the system — normalisation, a geometry-derived label, a calculated projection.
     * Non-authoritative.
     */
    case Derived = 'derived';

    /**
     * Supplied by an external import, e.g. MLS facts. Non-authoritative.
     *
     * §8.2 rule 2 already fixes the precedence: "an import never overwrites an authored dimension".
     * Marked conditionally authoritative rather than plainly non-authoritative because §8.2 gives an
     * import genuine standing over an ABSENT dimension while denying it any over an authored one —
     * a conditionality worth recording rather than flattening.
     */
    case Imported = 'imported';

    /**
     * Originating in the retained accepted-bid location snapshot. Forbidden as a restoration source.
     *
     * G1b F-G1B-7 established that the retained snapshot holds full unprojected geometry and notes
     * with zero production readers, and D-G1-7 approved keeping it under a sunset and a reader guard
     * with no reader authorised. It is therefore representable as an origin and never usable as one
     * automatically.
     */
    case SnapshotRetained = 'snapshot_retained';

    /**
     * Origin unknown or unreadable. Default-safe: never authoritative, never promotable.
     *
     * Reached by a malformed record or by data predating provenance tracking. §7.2's deny-by-default
     * reasoning applies: an unrecognised input resolves to the safe answer, never the permissive one.
     */
    case Unknown = 'unknown';

    /** The standing this kind carries. Total: every kind has exactly one authority. */
    public function authority(): ProvenanceAuthority
    {
        return match ($this) {
            self::OwnerAuthored, self::OwnerCleared => ProvenanceAuthority::Authoritative,

            self::LegacyFallback, self::LegacyRepaired,
            self::Inherited, self::Derived           => ProvenanceAuthority::NonAuthoritative,

            self::Imported                          => ProvenanceAuthority::ConditionallyAuthoritative,

            self::SnapshotRetained                  => ProvenanceAuthority::ForbiddenAsRestorationSource,

            // Default-safe: an unknown origin is treated as forbidden rather than merely weak, so a
            // parse failure can never become a restoration source.
            self::Unknown                           => ProvenanceAuthority::ForbiddenAsRestorationSource,
        };
    }

    public function isAuthoritative(): bool
    {
        return $this->authority()->isAuthoritative();
    }

    /** True only for the owner's explicit clear — the kind that blocks resurrection. */
    public function blocksFallbackResurrection(): bool
    {
        return $this === self::OwnerCleared;
    }

    /** True when the value physically sits in the canonical document rather than being read through. */
    public function isCanonicalStorage(): bool
    {
        return match ($this) {
            self::OwnerAuthored, self::OwnerCleared, self::LegacyRepaired,
            self::Inherited, self::Derived, self::Imported => true,
            self::LegacyFallback, self::SnapshotRetained, self::Unknown => false,
        };
    }

    /** True only for a value the owner personally stated — authored or cleared. */
    public function isOwnerStated(): bool
    {
        return $this === self::OwnerAuthored || $this === self::OwnerCleared;
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * Parse a stored kind, mapping anything unrecognised to {@see self::Unknown}.
     *
     * Returns Unknown rather than throwing so an unreadable value flows into the default-safe path
     * instead of an error a caller might mishandle into a permissive default.
     */
    public static function fromNameOrUnknown(?string $name): self
    {
        if ($name === null) {
            return self::Unknown;
        }

        return self::tryFrom(strtolower(trim($name))) ?? self::Unknown;
    }
}
