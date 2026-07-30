<?php

namespace App\Services\LocationDna\Capability;

/**
 * LocationDnaAccessContext — immutable, already-resolved facts about one access.
 *
 * G1d inert capability resolver. INERT.
 *
 * WHAT MAY NOT BE PASSED, AND WHY
 * -------------------------------
 * No Eloquent model, HTTP request, Livewire component, authenticated user object, session,
 * policy or gate. v1.2 §6 requires the domain core to be free of framework and transport types;
 * settled decisions 3 and 4 put canonical semantics on the server with a non-authoritative
 * client; §6.1 keeps `user_id` out of the envelope because the principal is resolved
 * server-side. This object therefore carries only enum-typed facts a caller has already
 * established, and the resolver never inspects the environment.
 *
 * AUTHENTICATION IS A FACT, NOT AN AUTHORISATION
 * ----------------------------------------------
 * `authenticated` is recorded because it is sometimes true and worth knowing, but D4 decided
 * that authentication alone does not authorise geometry. It therefore appears here as an input
 * and is never, on its own, a reason for a grant.
 */
final class LocationDnaAccessContext
{
    private function __construct(
        public readonly LocationDnaSurface $surface,
        public readonly LocationDnaViewerRelationship $viewer,
        public readonly LocationDnaPurpose $purpose,
        public readonly bool $authenticated,
        public readonly bool $recordIsLegacyOnly,
    ) {
    }

    /**
     * Build a context from already-resolved facts.
     *
     * @throws LocationDnaCapabilityException on a contradictory combination
     */
    public static function of(
        LocationDnaSurface $surface,
        LocationDnaViewerRelationship $viewer,
        LocationDnaPurpose $purpose,
        bool $authenticated = false,
        bool $recordIsLegacyOnly = false,
    ): self {
        // Owner or counterparty status implies an authenticated principal; claiming otherwise is a
        // caller bug worth surfacing rather than silently denying.
        if (($viewer->isOwner() || $viewer === LocationDnaViewerRelationship::Counterparty) && ! $authenticated) {
            throw LocationDnaCapabilityException::contradictoryContext(
                "viewer `{$viewer->value}` cannot be unauthenticated",
            );
        }

        // A fully public surface cannot simultaneously be an owner-private one.
        if ($surface === LocationDnaSurface::PublicListingDisplay && $viewer->isOwner()) {
            throw LocationDnaCapabilityException::contradictoryContext(
                'the public listing surface cannot carry owner relationship; use an owner-private surface',
            );
        }

        if ($surface === LocationDnaSurface::OwnerPrivateEdit && ! $viewer->isOwner()) {
            throw LocationDnaCapabilityException::contradictoryContext(
                'the owner-private edit surface requires owner relationship',
            );
        }

        return new self($surface, $viewer, $purpose, $authenticated, $recordIsLegacyOnly);
    }

    /**
     * A deliberately unresolvable context.
     *
     * Used when a caller could not establish the facts. Resolves to a fully denied set.
     */
    public static function unknown(): self
    {
        return new self(
            LocationDnaSurface::Unknown,
            LocationDnaViewerRelationship::Unknown,
            LocationDnaPurpose::Unknown,
            false,
            false,
        );
    }

    /** True when any axis is unresolved — the default-deny trigger. */
    public function isIncomplete(): bool
    {
        return $this->surface === LocationDnaSurface::Unknown
            || $this->viewer === LocationDnaViewerRelationship::Unknown
            || $this->purpose === LocationDnaPurpose::Unknown;
    }

    /** A stable identity for equivalent contexts, so resolution is provably deterministic. */
    public function signature(): string
    {
        return implode('|', [
            $this->surface->value,
            $this->viewer->value,
            $this->purpose->value,
            $this->authenticated ? 'auth' : 'anon',
            $this->recordIsLegacyOnly ? 'legacy_only' : 'canonical',
        ]);
    }
}
