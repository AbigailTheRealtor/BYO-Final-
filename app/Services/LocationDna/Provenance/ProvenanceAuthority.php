<?php

namespace App\Services\LocationDna\Provenance;

/**
 * ProvenanceAuthority — how much weight a value's origin carries.
 *
 * G1e inert provenance model. INERT: nothing in this namespace is wired into any workflow,
 * model, controller, view, trait, persistence path, mirror repair, snapshot reader, Bridge,
 * CriteriaHashService or PublicGeometryProjection.
 *
 * WHY AN ENUM RATHER THAN SCATTERED BOOLEANS
 * ------------------------------------------
 * Authority is one question with four answers, not four independent flags. A boolean pair like
 * `isAuthoritative` + `mayBeOverwritten` admits impossible combinations; a single enum does not.
 *
 * `ForbiddenAsRestorationSource` is deliberately distinct from `NonAuthoritative`. A
 * non-authoritative value may still legitimately be *shown* and may be promoted by an explicit
 * owner action. A forbidden-as-restoration-source value may never be the automatic origin of a
 * restored canonical value at all — the retained snapshot is the case that needs that stronger
 * statement (G1b F-G1B-7, D-G1-7).
 */
enum ProvenanceAuthority: string
{
    /** The value speaks for the owner. It may not be overwritten automatically. */
    case Authoritative = 'authoritative';

    /** Visible and usable, but it does not speak for the owner and may be superseded. */
    case NonAuthoritative = 'non_authoritative';

    /**
     * Authoritative only while a stated condition holds.
     *
     * Used where the governing documents grant standing that a later policy could withdraw, so
     * the conditionality is recorded rather than being flattened into a plain yes or no.
     */
    case ConditionallyAuthoritative = 'conditionally_authoritative';

    /** May never be an automatic source for restoring a canonical value. */
    case ForbiddenAsRestorationSource = 'forbidden_as_restoration_source';

    /** True only for the unqualified authoritative case. */
    public function isAuthoritative(): bool
    {
        return $this === self::Authoritative;
    }

    /**
     * True when this authority permits being used to restore a canonical value automatically.
     *
     * Both `NonAuthoritative` and `ConditionallyAuthoritative` return false: automatic
     * restoration is an owner-intent question, and where the architecture is silent the default
     * is no automatic restoration.
     */
    public function mayBeAutomaticRestorationSource(): bool
    {
        return false;
    }

    /** True when an automatic system transition may overwrite a value of this authority. */
    public function mayBeOverwrittenAutomatically(): bool
    {
        return $this === self::NonAuthoritative;
    }
}
