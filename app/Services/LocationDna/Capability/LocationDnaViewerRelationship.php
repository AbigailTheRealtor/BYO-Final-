<?php

namespace App\Services\LocationDna\Capability;

/**
 * LocationDnaViewerRelationship — the already-resolved relationship of the viewer to the record.
 *
 * G1d inert capability resolver. INERT.
 *
 * These are FACTS the caller has already established, not questions the resolver asks. The
 * resolver never inspects a user object, a session, a policy or a gate — v1.2 settled decisions
 * 3 and 4 put canonical semantics on the server and make the client non-authoritative, and
 * §6.1 keeps `user_id` out of the envelope entirely.
 *
 * `Anonymous` and `AuthenticatedNonOwner` are distinct cases carrying the same geometry answer.
 * Keeping them separate is the point: D4 decided that authentication alone does not authorise
 * geometry, so the two must be expressible as different facts with the same outcome rather than
 * collapsed into one "not owner" flag that invites a future shortcut.
 */
enum LocationDnaViewerRelationship: string
{
    /** No authenticated principal. */
    case Anonymous = 'anonymous';

    /** Authenticated, but not the owner and not a counterparty. */
    case AuthenticatedNonOwner = 'authenticated_non_owner';

    /** The record owner. */
    case Owner = 'owner';

    /** A transaction counterparty — e.g. the agent on an accepted bid. */
    case Counterparty = 'counterparty';

    /** No human viewer: an internal service acting outside any request cycle. */
    case InternalService = 'internal_service';

    /** Unresolved or unrecognised. Always fully denied. */
    case Unknown = 'unknown';

    public static function fromNameOrUnknown(?string $name): self
    {
        if ($name === null) {
            return self::Unknown;
        }

        return self::tryFrom(strtolower(trim($name))) ?? self::Unknown;
    }

    /** True only for the record owner. Never true merely because someone authenticated. */
    public function isOwner(): bool
    {
        return $this === self::Owner;
    }
}
